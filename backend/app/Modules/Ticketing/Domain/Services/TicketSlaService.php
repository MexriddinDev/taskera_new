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
 *
 * Bir guruhda istagancha qoida bo'lishi mumkin. Zayavkaga muhimligi aynan
 * mos keladiganlari qo'llanadi; bunday qoida bo'lmasa — guruhning umumiy
 * qoidalari (priority_id = null).
 *
 * Bir nechta qoida mos kelsa ENG QATTIG'I (eng qisqa muddatlisi) tanlanadi:
 * SLA — so'rovchiga berilgan va'da, shuning uchun ikkilanishda qisqasi
 * olinadi. Tanlov qoidalar yaratilish tartibiga bog'liq emas.
 */
final class TicketSlaService
{
    /** @var array<int, array<int, array<int, array<int, object>>>> organization => team => priority => rules */
    private static array $rulesByOrganization = [];

    public static function forgetRules(): void
    {
        self::$rulesByOrganization = [];
    }

    /** @return array<int, array<string, mixed>> */
    public function forTicket(Ticket $ticket): array
    {
        if (! $ticket->assigned_team_id) {
            return [];
        }

        $rule = $this->ruleFor(
            (int) $ticket->organization_id,
            (int) $ticket->assigned_team_id,
            $ticket->priority_id === null ? null : (int) $ticket->priority_id
        );

        if (! $rule) {
            return [];
        }

        $createdAt = $this->at($ticket->created_at);
        $acceptedAt = $this->at($ticket->started_at);
        $resolvedAt = $this->at($ticket->resolved_at);
        $context = [
            'slaId' => (int) $rule->id,
            'slaName' => $rule->name,
            'priorityId' => $rule->priority_id === null ? null : (int) $rule->priority_id,
            'priorityName' => $rule->priority_name,
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

    /**
     * Guruh va muhimlik bo'yicha qo'llanadigan qoida.
     *
     * Avval aynan shu muhimlik uchun yozilgan qoidalar qaraladi, ular bo'lmasa
     * — guruhning umumiy qoidalari. Ikkalasi ham bo'lmasa zayavkada SLA yo'q.
     */
    private function ruleFor(int $organizationId, int $teamId, ?int $priorityId): ?object
    {
        $teamRules = $this->rules($organizationId)[$teamId] ?? [];
        $matching = ($priorityId !== null && ! empty($teamRules[$priorityId]))
            ? $teamRules[$priorityId]
            : ($teamRules[0] ?? []);

        return $this->strictest($matching);
    }

    /**
     * Mos qoidalardan eng qattig'i: avval qabul qilish, so'ng ishlash muddati
     * bo'yicha. Teng bo'lsa kichik `id` — natija har safar bir xil bo'lishi
     * uchun (tartib tasodifiy bo'lib qolmasin).
     *
     * @param  array<int, object>  $rules
     */
    private function strictest(array $rules): ?object
    {
        $best = null;

        foreach ($rules as $rule) {
            if ($best === null
                || [(int) $rule->accept_minutes, (int) $rule->work_minutes, (int) $rule->id]
                 < [(int) $best->accept_minutes, (int) $best->work_minutes, (int) $best->id]) {
                $best = $rule;
            }
        }

        return $best;
    }

    /**
     * @return array<int, array<int, array<int, object>>> team => (priority|0) => rules
     *
     * Umumiy qoidalar 0 kaliti ostida turadi — `priority_id` NULL bo'lgani
     * uchun uni massiv kaliti sifatida ishlatib bo'lmaydi.
     */
    private function rules(int $organizationId): array
    {
        if (! array_key_exists($organizationId, self::$rulesByOrganization)) {
            $grouped = [];

            $rows = DB::table('sla_rules as s')
                ->join('teams as t', 't.id', '=', 's.team_id')
                ->leftJoin('ticket_priorities as p', 'p.id', '=', 's.priority_id')
                ->where('s.organization_id', $organizationId)
                ->where('s.is_active', true)
                ->whereNull('s.deleted_at')
                ->whereNull('t.deleted_at')
                ->get([
                    's.id', 's.team_id', 's.priority_id', 's.name', 's.description',
                    's.accept_minutes', 's.work_minutes',
                    't.name as team_name', 'p.name as priority_name',
                ]);

            foreach ($rows as $row) {
                $grouped[(int) $row->team_id][(int) ($row->priority_id ?? 0)][] = $row;
            }

            self::$rulesByOrganization[$organizationId] = $grouped;
        }

        return self::$rulesByOrganization[$organizationId];
    }

    private function at(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
