<?php

declare(strict_types=1);

namespace App\Modules\SLA\Domain\Services;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SlaEngine
{
    public function __construct(private BusinessTimeCalculator $clock, private PolicyMatcher $matcher) {}

    public function sync(Ticket $ticket, ?int $actor = null): void
    {
        DB::transaction(function () use ($ticket, $actor) {
            DB::table('tickets')->where('id', $ticket->id)->lockForUpdate()->first();
            $run = DB::table('ticket_sla_runs')->where('ticket_id', $ticket->id)->latest('cycle')->first();
            $status = DB::table('ticket_statuses')->where('id', $ticket->status_id)->value('code');
            $terminal = in_array($status, ['CLOSED', 'CANCELLED', 'REJECTED', 'DUPLICATE']);
            $reopened = $run && $run->ended_at && !$terminal && $ticket->wasChanged('status_id');
            if (!$run || $reopened) {
                if ($terminal) {
                    return;
                }
                if ($run) {
                    $version = DB::table('sla_policy_versions')->find($run->policy_version_id);
                    $version->config = json_decode($version->config, true);
                } else {
                    $version = $this->matcher->match($this->matcher->candidates($ticket->organization_id), $this->matcher->ticketScope($ticket), $ticket->created_at->toIso8601String());
                }
                if (!$version) {
                    return;
                }
                $runId = DB::table('ticket_sla_runs')->insertGetId(['ticket_id' => $ticket->id,
                    'organization_id' => $ticket->organization_id, 'policy_version_id' => $version->id,
                    'cycle' => ($run?->cycle ?? 0) + 1, 'created_at' => now()]);
                foreach ($version->config['targets'] as $target) {
                    DB::table('ticket_sla_instances')->insert(['run_id' => $runId, 'ticket_id' => $ticket->id,
                        'organization_id' => $ticket->organization_id, 'metric' => $target['metric'],
                        'target_minutes' => $target['minutes'], 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
                }
                $run = DB::table('ticket_sla_runs')->find($runId);
            }
            if ($run->ended_at) {
                return;
            }
            $config = $this->config($run->id);
            $assigned = $ticket->assigned_user_id || $ticket->assigned_team_id;
            $accepted = (bool) $ticket->started_at;
            $working = in_array($status, ['IN_PROGRESS', 'RESOLVED', 'CLOSED']);
            $resolved = in_array($status, ['RESOLVED', 'CLOSED']);
            foreach (DB::table('ticket_sla_instances')->where('run_id', $run->id)->lockForUpdate()->get() as $instance) {
                if (in_array($instance->status, ['COMPLETED', 'EXCLUDED'])) {
                    continue;
                }
                $target = collect($config['targets'])->firstWhere('metric', $instance->metric);
                $calendar = $this->calendar($config, $target);
                $trigger = $target['start'];
                $start = match ($trigger) {
                    'CREATED' => $run->cycle > 1 ? Time::parse($run->created_at) : Time::instance($ticket->created_at),
                    'ASSIGNED' => $assigned ? Time::now() : null,
                    'ACCEPTED' => $accepted ? Time::now() : null,
                    'RESOLVED' => $resolved ? Time::now() : null,
                    default => null,
                };
                if ($instance->status === 'PENDING' && $start) {
                    $instance->started_at = $start;
                    $instance->due_at = $this->clock->addSeconds($start, $instance->target_minutes * 60, $calendar);
                    $instance->status = 'RUNNING';
                    $instance->next_escalation_at = $this->next($instance, $config, $calendar);
                    $this->save($instance);
                    $this->audit($instance->id, 'STARTED', $actor, ['due_at' => (string) $instance->due_at]);
                }
                $complete = match ($instance->metric) {
                    'ASSIGNMENT' => $assigned,
                    'ACCEPTANCE' => $accepted,
                    'FIRST_RESPONSE' => (bool) $ticket->first_response_at,
                    'WORK_START' => $working,
                    'RESOLUTION' => $resolved,
                    'CLOSURE' => $status === 'CLOSED',
                    'UPDATE' => $resolved,
                    default => false,
                };
                if ($complete && $instance->status !== 'PENDING') {
                    $this->resume($instance, $calendar, $config, $actor);
                    $instance->completed_at = Time::now();
                    if ($instance->metric !== 'UPDATE' && Time::parse($instance->due_at) < Time::now()) {
                        $instance->breached_at ??= $instance->due_at;
                    }
                    $instance->status = 'COMPLETED';
                    $instance->next_escalation_at = null;
                    $this->save($instance);
                    $this->audit($instance->id, 'COMPLETED', $actor);
                } elseif ($terminal) {
                    $instance->status = 'EXCLUDED';
                    $instance->next_escalation_at = null;
                    $this->save($instance);
                    $this->audit($instance->id, 'EXCLUDED', $actor, ['status' => $status]);
                } elseif (in_array($status, $config['pause_statuses'] ?? []) && $instance->status !== 'PENDING') {
                    if ($instance->status !== 'PAUSED') {
                        $reason = trim((string) request()->input('sla_pause_reason', request()->input('reason', '')));
                        if ($reason === '') {
                            throw ValidationException::withMessages(['sla_pause_reason' => 'SLA pauzasi uchun sabab majburiy.']);
                        }
                        $instance->remaining_seconds = $this->clock->secondsBetween(Time::now(), Time::parse($instance->due_at), $calendar);
                        $instance->paused_at = Time::now();
                        $instance->status = 'PAUSED';
                        $instance->next_escalation_at = null;
                        $this->save($instance);
                        $this->audit($instance->id, 'PAUSED', $actor, ['reason' => $reason]);
                    }
                } else {
                    $this->resume($instance, $calendar, $config, $actor);
                }
            }
            if ($terminal) {
                DB::table('ticket_sla_runs')->where('id', $run->id)->update(['ended_at' => now()]);
            }
            if ($ticket->wasChanged(['priority_id', 'category_id', 'assigned_user_id'])) {
                $first = DB::table('ticket_sla_instances')->where('run_id', $run->id)->value('id');
                if ($first) {
                    $this->audit($first, 'TICKET_CHANGED', $actor, ['changes' => array_intersect_key($ticket->getChanges(), array_flip(['priority_id', 'category_id', 'assigned_user_id'])), 'policy_retained' => true]);
                }
            }
        }, 3);
    }

    public function config(int $runId): array
    {
        $json = DB::table('ticket_sla_runs as r')->join('sla_policy_versions as v', 'v.id', '=', 'r.policy_version_id')->where('r.id', $runId)->value('v.config');
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function calendar(array $config, array $target): array
    {
        return ($target['calendar_mode'] ?? 'BUSINESS') === '24X7' ? ['is_24x7' => true, 'timezone' => 'UTC'] : $config['calendar'];
    }

    private function resume(object $i, array $calendar, array $config, ?int $actor): void
    {
        if ($i->status !== 'PAUSED') {
            return;
        }
        $paused = Time::now()->getTimestamp() - Time::parse($i->paused_at)->getTimestamp();
        $i->paused_seconds += max(0, $paused);
        // Preserve an already overdue deadline and its breach; a pause cannot cure it.
        $i->due_at = $i->remaining_seconds >= 0
            ? $this->clock->addSeconds(Time::now(), $i->remaining_seconds, $calendar)
            : Time::now()->addSeconds($i->remaining_seconds);
        $i->paused_at = null;
        $i->remaining_seconds = null;
        $i->status = $i->breached_at ? 'BREACHED' : 'RUNNING';
        $i->next_escalation_at = $this->next($i, $config, $calendar);
        $this->save($i);
        $this->audit($i->id, 'RESUMED', $actor, ['paused_seconds' => $paused]);
    }

    public function next(object $i, array $config, array $calendar): ?Time
    {
        if ($i->metric === 'UPDATE') {
            return Time::parse($i->due_at);
        }
        $rule = $config['escalations'][$i->escalation_index] ?? null;
        if (!$rule) {
            return !$i->breached_at ? Time::parse($i->due_at) : null;
        }
        $budget = ($i->target_minutes + $i->extension_minutes) * 60;
        $thresholdSeconds = (int) ceil($budget * $rule['threshold'] / 100);
        // Find a threshold relative to the current deadline, including previous pauses.
        $remaining = $this->clock->secondsBetween(Time::now(), Time::parse($i->due_at), $calendar);
        $until = $remaining - $budget + $thresholdSeconds;
        $next = $until <= 0 ? Time::now() : $this->clock->addSeconds(Time::now(), $until, $calendar);
        return !$i->breached_at ? $next->min(Time::parse($i->due_at)) : $next;
    }

    /**
     * Taymerning hozirgi holati: qolgan ish vaqti (soniya), sarflangan foiz va
     * risk darajasi. Monitoring paneli va ticket kartochkasi shundan foydalanadi.
     *
     * Foiz ish kalendari bo'yicha hisoblanadi — tunda yoki dam olish kunida
     * ochiq turgan zayavka bekorga "muddat tugayapti" deb ko'rsatilmaydi.
     */
    public function progress(object $instance, array $config): array
    {
        $budget = max(1, ((int) $instance->target_minutes + (int) $instance->extension_minutes) * 60);
        if (!$instance->due_at || in_array($instance->status, ['PENDING', 'EXCLUDED'], true)) {
            return ['remaining_seconds' => null, 'percent' => null, 'level' => 'PENDING'];
        }
        if ($instance->status === 'PAUSED') {
            $remaining = (int) $instance->remaining_seconds;
        } else {
            $target = collect($config['targets'])->firstWhere('metric', $instance->metric);
            $remaining = $this->clock->secondsBetween(Time::now(), Time::parse($instance->due_at), $this->calendar($config, $target));
        }
        $percent = (int) round(($budget - $remaining) / $budget * 100);
        $level = match (true) {
            $instance->breached_at !== null || $remaining <= 0 => 'BREACHED',
            $percent >= 90 => 'HIGH',
            $percent >= 75 => 'WARNING',
            default => 'OK',
        };
        return ['remaining_seconds' => $remaining, 'percent' => max(0, $percent), 'level' => $level];
    }

    public function tick(): int
    {
        $count = 0;
        DB::table('ticket_sla_instances')->whereIn('status', ['RUNNING', 'BREACHED'])
            ->where('next_escalation_at', '<=', now())->select('id')->orderBy('id')->chunkById(100, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$count) {
                        $i = DB::table('ticket_sla_instances')->where('id', $row->id)->lockForUpdate()->first();
                        if (!in_array($i->status, ['RUNNING', 'BREACHED']) || !$i->next_escalation_at || Time::parse($i->next_escalation_at)->isFuture()) {
                            return;
                        }
                        $config = $this->config($i->run_id);
                        $target = collect($config['targets'])->firstWhere('metric', $i->metric);
                        $calendar = $this->calendar($config, $target);
                        if ($i->metric !== 'UPDATE' && Time::parse($i->due_at) <= Time::now() && !$i->breached_at) {
                            $i->breached_at = $i->due_at;
                            $i->status = 'BREACHED';
                            $this->audit($i->id, 'BREACHED', null);
                        }
                        $rule = $i->metric === 'UPDATE' ? ['threshold' => 100, 'assignee' => true, 'user_ids' => [], 'channels' => ['IN_APP']]
                            : ($config['escalations'][$i->escalation_index] ?? null);
                        $budget = ($i->target_minutes + $i->extension_minutes) * 60;
                        $remaining = $this->clock->secondsBetween(Time::now(), Time::parse($i->due_at), $calendar);
                        if ($rule && ($i->metric === 'UPDATE' || $budget - $remaining >= (int) ceil($budget * $rule['threshold'] / 100))) {
                            DB::table('sla_escalation_outbox')->insertOrIgnore(['instance_id' => $i->id,
                                'step' => $i->escalation_index, 'rule' => json_encode($rule), 'created_at' => now()]);
                            $this->audit($i->id, $i->metric === 'UPDATE' ? 'UPDATE_REMINDER' : 'ESCALATED', null, ['threshold' => $rule['threshold']]);
                            $i->escalation_index++;
                        }
                        if ($i->metric === 'UPDATE') {
                            $i->due_at = $this->clock->addSeconds(Time::now(), $i->target_minutes * 60, $calendar);
                        }
                        $i->next_escalation_at = $this->next($i, $config, $calendar);
                        $this->save($i);
                        $count++;
                    }, 3);
                }
            });
        return $count;
    }

    public function save(object $instance): void
    {
        $values = (array) $instance;
        unset($values['id']);
        $values['updated_at'] = now();
        DB::table('ticket_sla_instances')->where('id', $instance->id)->update($values);
    }

    public function audit(int $instanceId, string $type, ?int $actor = null, array $data = []): void
    {
        DB::table('sla_instance_events')->insert(['instance_id' => $instanceId, 'event_type' => $type,
            'actor_id' => $actor, 'data' => json_encode($data), 'created_at' => now()]);
    }
}
