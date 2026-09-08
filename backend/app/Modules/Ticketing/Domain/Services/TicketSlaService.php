<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guruhga biriktirilgan faol SLA qoidasidan qabul qilish va ishlash
 * muddatlarini hisoblaydi. Kechikish saqlanmaydi: joriy/yakunlangan vaqt bilan
 * deadline orasidagi farqdan har safar aniq hisoblanadi.
 */
final class TicketSlaService
{
    /** Kategoriya CRUD uchun avvalgi standart qiymatlar saqlab qolindi. */
    public const DEFAULTS = ['accept' => 30, 'work' => 240, 'close' => 120];

    /** @var array<int, array<int, object>> organization => team => rule */
    private static array $rulesByOrganization = [];

    public static function forgetRules(): void
    {
        self::$rulesByOrganization = [];
    }

    public static function forgetCategories(): void
    {
        self::forgetRules();
    }

    /** @return array<int, array<string, mixed>> */
    public function forTicket(Ticket $ticket): array
    {
        if (! $ticket->assigned_team_id) {
            return [];
        }

        $rule = $this->rules((int) $ticket->organization_id)[(int) $ticket->assigned_team_id] ?? null;
        if (! $rule) {
            return [];
        }

        $createdAt = $this->at($ticket->created_at);
        $acceptedAt = $this->at($ticket->started_at);
        $resolvedAt = $this->at($ticket->resolved_at);
        $context = [
            'slaId' => (int) $rule->id,
            'slaName' => $rule->name,
            'description' => $rule->description,
            'teamId' => (int) $rule->team_id,
            'teamName' => $rule->team_name,
        ];

        return [
            $this->stage('accept', (int) $rule->accept_minutes, $createdAt, $acceptedAt) + $context,
            $this->stage('work', (int) $rule->work_minutes, $acceptedAt, $resolvedAt) + $context,
        ];
    }

    /** @return array<string, mixed> */
    private function stage(string $key, int $minutes, ?Carbon $startedAt, ?Carbon $finishedAt): array
    {
        $dueAt = $startedAt?->copy()->addMinutes($minutes);
        $end = $finishedAt ?? now();

        $status = match (true) {
            $startedAt === null => 'WAITING',
            $finishedAt !== null => $finishedAt->greaterThan($dueAt) ? 'BREACHED' : 'MET',
            $end->greaterThan($dueAt) => 'BREACHED',
            default => 'RUNNING',
        };

        $remainingSeconds = $status === 'RUNNING'
            ? max(0, $end->diffInSeconds($dueAt, false))
            : null;
        $overdueMinutes = $status === 'BREACHED'
            ? (int) ceil($dueAt->diffInSeconds($end) / 60)
            : 0;

        return [
            'key' => $key,
            'minutes' => $minutes,
            'startedAt' => $startedAt?->toIso8601String(),
            'dueAt' => $dueAt?->toIso8601String(),
            'finishedAt' => $finishedAt?->toIso8601String(),
            'status' => $status,
            'remainingSeconds' => $remainingSeconds,
            'overdueMinutes' => $overdueMinutes,
        ];
    }

    /** @return array<int, object> */
    private function rules(int $organizationId): array
    {
        if (! array_key_exists($organizationId, self::$rulesByOrganization)) {
            self::$rulesByOrganization[$organizationId] = DB::table('sla_rules as s')
                ->join('teams as t', 't.id', '=', 's.team_id')
                ->where('s.organization_id', $organizationId)
                ->where('s.is_active', true)
                ->whereNull('s.deleted_at')
                ->whereNull('t.deleted_at')
                ->get(['s.id', 's.team_id', 's.name', 's.description', 's.accept_minutes', 's.work_minutes', 't.name as team_name'])
                ->keyBy('team_id')
                ->all();
        }

        return self::$rulesByOrganization[$organizationId];
    }

    private function at(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
