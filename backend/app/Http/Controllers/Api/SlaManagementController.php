<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\SLA\Domain\Services\{BusinessTimeCalculator, PolicyMatcher, SlaConfiguration, SlaEngine};
use App\Support\CurrentOrg;
use Carbon\CarbonImmutable as Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SlaManagementController extends Controller
{
    public function __construct(private SlaConfiguration $configuration, private PolicyMatcher $matcher, private SlaEngine $engine) {}

    private function manage(Request $r): void
    {
        abort_unless($r->user()?->hasPermission('sla.manage'), 403);
    }

    private function policy(Request $r, int $id): object
    {
        return DB::table('sla_policies')->where('organization_id', CurrentOrg::id($r))->whereNull('deleted_at')->find($id) ?? abort(404);
    }

    public function options(Request $r)
    {
        $org = CurrentOrg::id($r);
        $owned = fn ($table, $columns = ['id', 'name']) => DB::table($table)->where('organization_id', $org)->whereNull('deleted_at')->orderBy('name')->get($columns);
        return response()->json(['data' => ['categories' => $owned('categories', ['id', 'name', 'parent_id']),
            'services' => $owned('services'), 'departments' => $owned('departments'),
            'users' => DB::table('users')->where('organization_id', $org)->whereNull('deleted_at')->orderBy('username')->get(['id', 'username as name']),
            'priorities' => DB::table('ticket_priorities')->orderBy('weight')->get(['id', 'name', 'code']),
            'sources' => DB::table('ticket_sources')->get(['id', 'name']),
            'timezones' => DB::table('timezones')->where('is_active', true)->get(['id', 'name']),
            'calendars' => $owned('business_calendars', ['id', 'name', 'timezone_id', 'is_24x7'])]]);
    }

    public function index(Request $r)
    {
        $q = DB::table('sla_policies')->where('organization_id', CurrentOrg::id($r))->whereNull('deleted_at');
        if ($r->filled('search')) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->string('search').'%')->orWhere('code', 'like', '%'.$r->string('search').'%'));
        }
        if ($r->filled('status')) {
            $q->where('publication_status', $r->string('status'));
        }
        $rows = $q->orderByDesc('id')->paginate(min(100, max(1, $r->integer('per_page', 15))));
        $rows->getCollection()->transform(function ($p) {
            $p->draft_config = $p->draft_config ? json_decode($p->draft_config, true) : null;
            return $p;
        });
        return response()->json($rows);
    }

    public function save(Request $r, ?int $id = null)
    {
        $this->manage($r);
        $publish = $r->boolean('publish');
        $org = CurrentOrg::id($r);
        $config = $this->configuration->validate($r->all(), $org, $publish);
        return DB::transaction(function () use ($r, $id, $publish, $org, $config) {
            // Serialize publication/code checks for this organization.
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $existing = $id ? $this->policy($r, $id) : null;
            $duplicate = DB::table('sla_policies')->where('organization_id', $org)->where('code', $config['code'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['code' => 'Ushbu SLA kodi band.']);
            }
            $values = ['code' => $config['code'], 'name' => $config['name'], 'calendar_id' => $config['calendar_id'] ?? null,
                'draft_config' => json_encode($config), 'effective_from' => $config['effective_from'], 'effective_to' => $config['effective_to'] ?? null,
                'updated_by' => $r->user()->id, 'updated_at' => now()];
            if ($existing) {
                DB::table('sla_policies')->where('id', $id)->update($values);
            } else {
                $id = DB::table('sla_policies')->insertGetId($values + ['organization_id' => $org, 'public_id' => (string) Str::uuid(),
                    'is_active' => false, 'publication_status' => 'DRAFT', 'created_at' => now(), 'created_by' => $r->user()->id]);
            }
            if ($publish) {
                $number = (int) DB::table('sla_policy_versions')->where('sla_policy_id', $id)->max('number') + 1;
                $version = DB::table('sla_policy_versions')->insertGetId(['sla_policy_id' => $id, 'organization_id' => $org,
                    'number' => $number, 'config' => json_encode($config), 'published_by' => $r->user()->id, 'published_at' => now()]);
                DB::table('sla_policies')->where('id', $id)->update(['published_version_id' => $version,
                    'publication_status' => 'ACTIVE', 'is_active' => true, 'version' => $number]);
            }
            $this->audit($r, $publish ? 'SLA_PUBLISHED' : 'SLA_DRAFT_SAVED', $id);
            return response()->json(['data' => $this->policy($r, $id)], $existing ? 200 : 201);
        }, 3);
    }

    public function archive(Request $r, int $id)
    {
        $this->manage($r);
        $this->policy($r, $id);
        DB::transaction(function () use ($r, $id) {
            DB::table('sla_policies')->where('id', $id)->update(['publication_status' => 'ARCHIVED', 'is_active' => false, 'updated_at' => now()]);
            $this->audit($r, 'SLA_ARCHIVED', $id);
        });
        return response()->json(['message' => 'Arxivlandi']);
    }

    public function preview(Request $r)
    {
        $this->manage($r);
        $config = $this->configuration->validate($r->all(), CurrentOrg::id($r), true);
        $r->validate(['sample_at' => 'nullable|date', 'sample_scope' => 'nullable|array']);
        $at = Time::parse($r->input('sample_at', 'now'));
        $sample = $r->input('sample_scope', $config['scope']);
        $versions = $this->matcher->candidates(CurrentOrg::id($r));
        $matched = $this->matcher->match($versions, $sample, $at->toIso8601String());
        $conflicts = array_values(array_map(fn ($v) => ['id' => $v->sla_policy_id, 'name' => $v->config['name']], array_filter($versions,
            fn ($v) => array_filter($v->config['scope']) == array_filter($config['scope']) && (int) $v->sla_policy_id !== $r->integer('id'))));
        $clock = app(BusinessTimeCalculator::class);
        return response()->json(['data' => ['matched_policy' => $matched?->config['name'], 'conflicts' => $conflicts,
            'sample_at' => $at->toIso8601String(), 'deadlines' => array_map(fn ($target) => ['metric' => $target['metric'],
                'start' => $target['start'], 'due_at' => $clock->addSeconds($at, $target['minutes'] * 60, $this->engine->calendar($config, $target))->toIso8601String()], $config['targets'])]]);
    }

    public function monitoring(Request $r)
    {
        $this->manage($r);
        // "Hozir tekshirish kerak" bloki: faqat yurayotgan taymerlar bo'yicha
        // risk darajasi. Ish kalendari hisobga olingani uchun foiz tunda
        // sun'iy o'smaydi.
        $progress = $this->progress($r);
        $summary = ['breached' => 0, 'high' => 0, 'warning' => 0];
        foreach ($progress as $row) {
            match ($row['level']) {
                'BREACHED' => $summary['breached']++,
                'HIGH' => $summary['high']++,
                'WARNING' => $summary['warning']++,
                default => null,
            };
        }

        $q = $this->monitorQuery($r)->select('i.*', 't.ticket_no', 't.subject', 'u.username as assignee', 'c.name as category');
        if ($r->filled('level')) {
            $level = $r->string('level')->toString();
            $q->whereIn('i.id', array_keys(array_filter($progress, fn ($row) => $row['level'] === $level)) ?: [0]);
        }
        $rows = $q->orderBy('i.due_at')->paginate(min(100, max(1, $r->integer('per_page', 20))));
        $rows->getCollection()->transform(function ($row) use ($progress) {
            $state = $progress[$row->id] ?? ['remaining_seconds' => null, 'percent' => null, 'level' => $row->status];
            // array_replace: `remaining_seconds` ustuni jadvalda ham bor —
            // hisoblangan qiymat bazadagi (faqat pauzada to'ldiriladigan) ustunni almashtiradi.
            return (object) array_replace((array) $row, $state);
        });

        return response()->json($rows->toArray() + ['summary' => $summary + ['total' => array_sum($summary)]]);
    }

    /** Yurayotgan taymerlarning qolgan vaqti va foizi: [instance_id => progress]. */
    private function progress(Request $r): array
    {
        $rows = $this->monitorQuery($r)->whereIn('i.status', ['RUNNING', 'BREACHED', 'PAUSED'])->where('i.metric', '!=', 'UPDATE')
            ->join('ticket_sla_runs as run', 'run.id', '=', 'i.run_id')
            ->join('sla_policy_versions as v', 'v.id', '=', 'run.policy_version_id')
            ->select('i.*', 'v.id as version_id', 'v.config')->get();
        $configs = [];
        $progress = [];
        foreach ($rows as $row) {
            $configs[$row->version_id] ??= json_decode($row->config, true, 512, JSON_THROW_ON_ERROR);
            $progress[$row->id] = $this->engine->progress($row, $configs[$row->version_id]);
        }
        return $progress;
    }

    private function monitorQuery(Request $r)
    {
        $q = DB::table('ticket_sla_instances as i')->join('tickets as t', 't.id', '=', 'i.ticket_id')
            ->leftJoin('users as u', 'u.id', '=', 't.assigned_user_id')->leftJoin('categories as c', 'c.id', '=', 't.category_id')
            ->where('i.organization_id', CurrentOrg::id($r))->whereNull('t.deleted_at');
        foreach (['category_id', 'priority_id', 'department_id', 'assigned_user_id'] as $field) {
            if ($r->filled($field)) $q->where('t.'.$field, $r->integer($field));
        }
        foreach (['status', 'metric'] as $field) {
            if ($r->filled($field)) $q->where('i.'.$field, $r->string($field));
        }
        if ($r->filled('from')) $q->where('i.created_at', '>=', $r->date('from')->startOfDay());
        if ($r->filled('to')) $q->where('i.created_at', '<=', $r->date('to')->endOfDay());
        if ($r->boolean('breached')) $q->whereNotNull('i.breached_at');
        return $q;
    }

    public function reports(Request $r)
    {
        $this->manage($r);
        $rows = $this->monitorQuery($r)->where('i.metric', '!=', 'UPDATE')->select('i.*', 't.category_id', 't.assigned_user_id', 't.department_id')->get();
        $completed = $rows->where('status', 'COMPLETED');
        $stats = function ($group) {
            $done = $group->where('status', 'COMPLETED');
            $times = $done->map(fn ($i) => max(0, Time::parse($i->completed_at)->getTimestamp() - Time::parse($i->started_at)->getTimestamp() - $i->paused_seconds) / 60)->sort()->values();
            $percentile = fn ($p) => $times->isEmpty() ? null : round($times[max(0, (int) ceil($times->count() * $p) - 1)], 1);
            return ['total' => $group->count(), 'completed' => $done->count(), 'breached' => $group->whereNotNull('breached_at')->count(),
                'compliance' => $done->count() ? round($done->whereNull('breached_at')->count() / $done->count() * 100, 1) : null,
                'average_minutes' => $times->isEmpty() ? null : round($times->avg(), 1), 'median_minutes' => $percentile(.5), 'p90_minutes' => $percentile(.9), 'p95_minutes' => $percentile(.95)];
        };
        return response()->json(['data' => $stats($rows) + ['active_policies' => DB::table('sla_policies')->where('organization_id', CurrentOrg::id($r))->where('publication_status', 'ACTIVE')->whereNull('deleted_at')->count(),
            'running' => $rows->where('status', 'RUNNING')->count(), 'paused' => $rows->where('status', 'PAUSED')->count(),
            'metrics' => $rows->groupBy('metric')->map($stats), 'categories' => $rows->groupBy('category_id')->map($stats),
            'employees' => $rows->groupBy('assigned_user_id')->map($stats), 'departments' => $rows->groupBy('department_id')->map($stats)]]);
    }

    public function export(Request $r)
    {
        $this->manage($r);
        $this->audit($r, 'SLA_EXPORTED', null);
        $query = $this->monitorQuery($r)->select('i.*', 't.ticket_no', 'u.username as assignee')->orderBy('i.id');
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ticket', 'Metric', 'Status', 'Assignee', 'Started', 'Deadline', 'Completed', 'Breached'], ',', '"', '');
            foreach ($query->cursor() as $row) {
                $values = [$row->ticket_no, $row->metric, $row->status, $row->assignee, $row->started_at, $row->due_at, $row->completed_at, $row->breached_at];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, $values), ',', '"', '');
            }
            fclose($out);
        }, 'sla-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function ticketAccess(Request $r, int $id, bool $write = false): object
    {
        $ticket = DB::table('tickets')->where('organization_id', CurrentOrg::id($r))->whereNull('deleted_at')->find($id) ?? abort(404);
        $user = $r->user();
        abort_unless($user->hasPermission('sla.manage') || (int) $ticket->assigned_user_id === (int) $user->id || (!$write && (int) $ticket->requester_user_id === (int) $user->id), 403);
        return $ticket;
    }

    public function ticket(Request $r, int $id)
    {
        $ticket = $this->ticketAccess($r, $id);
        $runs = DB::table('ticket_sla_runs as r')->join('sla_policy_versions as v', 'v.id', '=', 'r.policy_version_id')->where('r.ticket_id', $id)->orderByDesc('r.cycle')->select('r.*', 'v.number', 'v.config')->get();
        $instances = DB::table('ticket_sla_instances')->where('ticket_id', $id)->get();
        $events = DB::table('sla_instance_events')->whereIn('instance_id', $instances->pluck('id'))->orderByDesc('id')->limit(200)->get();
        $extensions = DB::table('ticket_sla_extensions')->whereIn('instance_id', $instances->pluck('id'))->orderByDesc('id')->get();
        $latestConfig = $runs->first() ? json_decode($runs->first()->config, true) : [];
        return response()->json(['data' => ['can_operate' => $r->user()->hasPermission('sla.manage') || (int) $ticket->assigned_user_id === (int) $r->user()->id,
            'approvers' => DB::table('users')->where('organization_id', CurrentOrg::id($r))->whereNull('deleted_at')->whereIn('id', $latestConfig['extension']['approver_ids'] ?? [])->get(['id', 'username as name']),
            'runs' => $runs->map(function ($run) { $run->config = json_decode($run->config, true); return $run; }),
            'instances' => $instances, 'events' => $events, 'extensions' => $extensions]]);
    }

    public function extension(Request $r, int $id)
    {
        $data = $r->validate(['minutes' => 'required|integer|min:1', 'reason_code' => 'required|string|max:64', 'reason' => 'required|string|min:5|max:5000', 'approver_id' => 'required|integer']);
        return DB::transaction(function () use ($r, $id, $data) {
            $i = DB::table('ticket_sla_instances')->where('organization_id', CurrentOrg::id($r))->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            $this->ticketAccess($r, $i->ticket_id, true);
            abort_unless(in_array($i->status, ['RUNNING', 'BREACHED', 'PAUSED']) && $i->metric !== 'UPDATE', 422, 'Bu taymer uzaytirilmaydi.');
            $rule = $this->engine->config($i->run_id)['extension'];
            abort_unless($rule['enabled'] && in_array($data['approver_id'], $rule['approver_ids']) && $data['approver_id'] !== $r->user()->id, 422, 'Tasdiqlovchi yoki uzaytirish qoidasi mos emas.');
            abort_if($i->extension_minutes + $data['minutes'] > $rule['max_minutes'], 422, 'Maksimal uzaytirish vaqti oshib ketdi.');
            abort_if(DB::table('ticket_sla_extensions')->where('instance_id', $id)->where('status', 'PENDING')->exists(), 409, 'Tasdiqlash kutilayotgan so‘rov bor.');
            $extension = DB::table('ticket_sla_extensions')->insertGetId($data + ['instance_id' => $id, 'requested_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->engine->audit($id, 'EXTENSION_REQUESTED', $r->user()->id, ['extension_id' => $extension] + $data);
            return response()->json(['data' => ['id' => $extension]], 201);
        }, 3);
    }

    public function pauseTicket(Request $r, int $id)
    {
        $this->ticketAccess($r, $id, true);
        $data = $r->validate(['status' => 'required|in:WAITING_USER,WAITING_VENDOR', 'reason' => 'required|string|min:5|max:2000']);
        return DB::transaction(function () use ($r, $id, $data) {
            $ticket = \App\Modules\Ticketing\Infrastructure\Eloquent\Ticket::whereKey($id)->lockForUpdate()->firstOrFail();
            $run = DB::table('ticket_sla_runs')->where('ticket_id', $id)->whereNull('ended_at')->latest('cycle')->first();
            abort_unless($run && in_array($data['status'], $this->engine->config($run->id)['pause_statuses']), 422, 'Bu pauza siyosatda ruxsat etilmagan.');
            $previous = DB::table('ticket_statuses')->where('id', $ticket->status_id)->value('code');
            abort_if(in_array($previous, ['RESOLVED', 'CLOSED', 'REJECTED', 'CANCELLED', 'WAITING_USER', 'WAITING_VENDOR']), 422);
            $metadata = $ticket->metadata ?? [];
            $metadata['sla_previous_status_id'] = $ticket->status_id;
            $ticket->metadata = $metadata;
            $ticket->status_id = DB::table('ticket_statuses')->where('code', $data['status'])->value('id') ?? abort(422);
            $ticket->save();
            return response()->json(['message' => 'SLA pauzaga qo‘yildi']);
        }, 3);
    }

    public function resumeTicket(Request $r, int $id)
    {
        $this->ticketAccess($r, $id, true);
        return DB::transaction(function () use ($id) {
            $ticket = \App\Modules\Ticketing\Infrastructure\Eloquent\Ticket::whereKey($id)->lockForUpdate()->firstOrFail();
            $metadata = $ticket->metadata ?? [];
            abort_unless(isset($metadata['sla_previous_status_id']), 422, 'SLA pauzasi topilmadi.');
            $ticket->status_id = $metadata['sla_previous_status_id'];
            unset($metadata['sla_previous_status_id']);
            $ticket->metadata = $metadata;
            $ticket->save();
            return response()->json(['message' => 'SLA davom ettirildi']);
        }, 3);
    }

    public function decideExtension(Request $r, int $id)
    {
        $data = $r->validate(['approve' => 'required|boolean', 'reason' => 'required|string|min:3|max:5000']);
        return DB::transaction(function () use ($r, $id, $data) {
            $e = DB::table('ticket_sla_extensions')->find($id) ?? abort(404);
            $i = DB::table('ticket_sla_instances')->where('organization_id', CurrentOrg::id($r))->where('id', $e->instance_id)->lockForUpdate()->first() ?? abort(404);
            $e = DB::table('ticket_sla_extensions')->where('id', $id)->lockForUpdate()->first();
            abort_unless((int) $e->approver_id === (int) $r->user()->id && (int) $e->requested_by !== (int) $r->user()->id, 403);
            abort_unless($e->status === 'PENDING', 409, 'So‘rov avval ko‘rib chiqilgan.');
            if ($data['approve']) {
                abort_unless(in_array($i->status, ['RUNNING', 'BREACHED', 'PAUSED']), 422, 'Taymer yakunlangan.');
                $config = $this->engine->config($i->run_id);
                abort_if($i->extension_minutes + $e->minutes > $config['extension']['max_minutes'], 422);
                $target = collect($config['targets'])->firstWhere('metric', $i->metric);
                $old = $i->due_at;
                $i->due_at = app(BusinessTimeCalculator::class)->addSeconds(Time::parse($i->due_at), $e->minutes * 60, $this->engine->calendar($config, $target));
                $i->extension_minutes += $e->minutes;
                if ($i->status === 'PAUSED') $i->remaining_seconds += $e->minutes * 60;
                else $i->next_escalation_at = $this->engine->next($i, $config, $this->engine->calendar($config, $target));
                $this->engine->save($i);
                $this->engine->audit($i->id, 'EXTENSION_APPROVED', $r->user()->id, ['old_due_at' => $old, 'new_due_at' => (string) $i->due_at, 'reason' => $data['reason']]);
            } else {
                $this->engine->audit($i->id, 'EXTENSION_REJECTED', $r->user()->id, ['reason' => $data['reason']]);
            }
            DB::table('ticket_sla_extensions')->where('id', $id)->update(['status' => $data['approve'] ? 'APPROVED' : 'REJECTED', 'decision_reason' => $data['reason'], 'decided_at' => now(), 'updated_at' => now()]);
            return response()->json(['message' => 'Qaror saqlandi']);
        }, 3);
    }

    private function audit(Request $r, string $action, ?int $id): void
    {
        \App\Modules\Audit\Domain\Services\AuditLogger::log($r, $action, $action, ['organization_id' => CurrentOrg::id($r), 'auditable_type' => 'sla_policy', 'auditable_id' => $id]);
    }
}
