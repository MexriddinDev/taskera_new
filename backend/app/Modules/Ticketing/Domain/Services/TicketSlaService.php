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
 * Qaysi qoida qo'llanishi uch bosqichda aniqlanadi:
 *
 *   1. Zayavka yaratishda SHABLON tanlangan bo'lsa (`tickets.sla_rule_id`) —
 *      aynan o'sha qoida: xodim "E-Imzo o'rnatish" ni tanlagan bo'lsa, muddat
 *      ham o'sha xizmatniki bo'lishi kerak.
 *   2. Shablon tanlanmagan bo'lsa — DEFAULT HOLAT: guruhning umumiy
 *      (muhimlikka bog'lanmagan) qoidasi.
 *   3. Guruhda umumiy qoida ham bo'lmasa — tizim standarti (DEFAULT_* ).
 *
 * Bir bosqichda bir nechta qoida mos kelsa ENG QATTIG'I (eng qisqa muddatlisi)
 * tanlanadi: SLA — so'rovchiga berilgan va'da, shuning uchun ikkilanishda
 * qisqasi olinadi. Tanlov qoidalar yaratilish tartibiga bog'liq emas.
 */
final class TicketSlaService
{
    /** Shablon ham, guruh qoidasi ham bo'lmaganda qo'llanadigan standart muddatlar (daqiqa). */
    private const DEFAULT_ACCEPT_MINUTES = 15;

    private const DEFAULT_WORK_MINUTES = 30;

    /** @var array<int, array<int, array<int, array<int, object>>>> organization => team => priority => rules */
    private static array $rulesByOrganization = [];

    public static function forgetRules(): void
    {
        self::$rulesByOrganization = [];
    }

    /** @return array<int, array<string, mixed>> */
    public function forTicket(Ticket $ticket): array
    {
        $rule = $this->ruleFor($ticket);

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

    /**
     * Guruhlar kesimida SLA buzilishi: qabul qilish (kutish) YOKI ishlash
     * muddati o'tib ketgan zayavkalar ulushi.
     *
     * Hisob ataylab SQL'da emas, shu servis orqali qilinadi. Qaysi qoida
     * qo'llanishi uch bosqichli mantiq bilan aniqlanadi (shablon → guruh
     * qoidasi → standart); uni SQL'ga ko'chirish ikkinchi haqiqat manbasini
     * yaratardi va zayavka kartochkasida ko'rinadigan muddat statistikadagi
     * muddatdan farq qilib ketishi mumkin edi.
     *
     * @param  iterable<Ticket>  $tickets
     * @return array<int, array{tracked:int, breached:int, acceptBreached:int, workBreached:int, compliancePercent:float|null}>
     */
    public function breachStatsByTeam(iterable $tickets): array
    {
        $stats = [];

        foreach ($tickets as $ticket) {
            $teamId = (int) ($ticket->assigned_team_id ?? 0);
            $stats[$teamId] ??= ['tracked' => 0, 'breached' => 0, 'acceptBreached' => 0, 'workBreached' => 0];

            $rule = $this->ruleFor($ticket);
            $createdAt = $this->at($ticket->created_at);
            $acceptedAt = $this->at($ticket->started_at);
            $resolvedAt = $this->at($ticket->resolved_at);

            $accept = $this->stage('accept', (int) $rule->accept_minutes, $createdAt, $acceptedAt)['status'] === 'BREACHED';
            $work = $this->stage('work', (int) $rule->work_minutes, $acceptedAt, $resolvedAt)['status'] === 'BREACHED';

            $stats[$teamId]['tracked']++;
            $stats[$teamId]['acceptBreached'] += $accept ? 1 : 0;
            $stats[$teamId]['workBreached'] += $work ? 1 : 0;
            // Bitta zayavka ikkala bosqichda ham kechiksa bir marta sanaladi —
            // foiz zayavkalar soniga nisbatan hisoblanadi, bosqichlarga emas.
            $stats[$teamId]['breached'] += ($accept || $work) ? 1 : 0;
        }

        foreach ($stats as $teamId => $row) {
            $stats[$teamId]['compliancePercent'] = $row['tracked'] > 0
                ? round(($row['tracked'] - $row['breached']) / $row['tracked'] * 100, 1)
                : null;
        }

        return $stats;
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

    /** Zayavkaga qo'llanadigan qoida — sinf izohidagi uch bosqich bo'yicha. */
    private function ruleFor(Ticket $ticket): object
    {
        $teamId = $ticket->assigned_team_id === null ? 0 : (int) $ticket->assigned_team_id;
        $teamRules = $this->rules((int) $ticket->organization_id)[$teamId] ?? [];

        // 1. Zayavka yaratishda tanlangan shablon.
        if ($ticket->sla_rule_id) {
            foreach ($teamRules as $rules) {
                foreach ($rules as $rule) {
                    if ((int) $rule->id === (int) $ticket->sla_rule_id) {
                        return $rule;
                    }
                }
            }
        }

        // 2. Default holat — guruhning umumiy qoidasi.
        $general = $this->strictest($teamRules[0] ?? []);
        if ($general) {
            return $general;
        }

        // 3. Tizim standarti.
        return (object) [
            'id' => 0,
            'team_id' => $teamId,
            'priority_id' => null,
            'name' => 'Standart',
            'description' => null,
            'accept_minutes' => self::DEFAULT_ACCEPT_MINUTES,
            'work_minutes' => self::DEFAULT_WORK_MINUTES,
            'team_name' => null,
            'priority_name' => null,
        ];
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
