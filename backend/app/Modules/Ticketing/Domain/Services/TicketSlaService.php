<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sodda SLA: uch bosqich, uchalasi ham zayavka kategoriyasida sozlanadi.
 *
 *   1. Qabul qilish — zayavka tushgandan xodim uni o'ziga olgunga qadar.
 *   2. Ishlash      — qabul qilingandan yechim topilgunga qadar.
 *   3. Yopish       — yechimdan zayavka yopilgunga qadar.
 *
 * Muddatlar HECH QAYERDA saqlanmaydi — ular zayavkaning o'z vaqtlaridan
 * (created_at / started_at / resolved_at) hisoblanadi. Shu sababli alohida
 * jadval, taymer va fon jarayoni kerak emas: kategoriya muddati o'zgarsa,
 * hisob ham darrov o'zgaradi.
 */
final class TicketSlaService
{
    /** Kategoriya topilmasa ishlatiladigan standart muddatlar (daqiqa). */
    public const DEFAULTS = [
        'accept' => 30,
        'work' => 240,
        'close' => 120,
    ];

    /** @var array{0: array<int, object>, 1: array<string, object>}|null */
    private static ?array $cache = null;

    /** Kategoriya o'zgargach keshni bekor qiladi (testlar va CRUD uchun). */
    public static function forgetCategories(): void
    {
        self::$cache = null;
    }

    /**
     * Zayavkaning uch bosqichi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forTicket(Ticket $ticket): array
    {
        $minutes = $this->minutesFor($ticket);

        $createdAt = $this->at($ticket->created_at);
        $acceptedAt = $this->at($ticket->started_at);
        $resolvedAt = $this->at($ticket->resolved_at);
        $closedAt = $this->at($ticket->closed_at)
            ?? (in_array((int) $ticket->status_id, [8, 10], true) ? $this->at($ticket->updated_at) : null);

        return [
            $this->stage('accept', $minutes['accept'], $createdAt, $acceptedAt),
            $this->stage('work', $minutes['work'], $acceptedAt, $resolvedAt),
            $this->stage('close', $minutes['close'], $resolvedAt, $closedAt),
        ];
    }

    /**
     * Kategoriya muddatlari. Kategoriya `category_id` orqali, u bo'lmasa
     * `category` matni bo'yicha topiladi — eski zayavkalarda id yo'q.
     *
     * @return array{accept: int, work: int, close: int}
     */
    public function minutesFor(Ticket $ticket): array
    {
        [$byId, $byName] = $this->categories();

        $row = ($ticket->category_id ? ($byId[(int) $ticket->category_id] ?? null) : null)
            ?? ($ticket->category ? ($byName[mb_strtolower(trim((string) $ticket->category))] ?? null) : null);

        if (! $row) {
            return self::DEFAULTS;
        }

        return [
            'accept' => (int) ($row->sla_accept_minutes ?? self::DEFAULTS['accept']),
            'work' => (int) ($row->sla_work_minutes ?? self::DEFAULTS['work']),
            'close' => (int) ($row->sla_close_minutes ?? self::DEFAULTS['close']),
        ];
    }

    /**
     * Bitta bosqich holati.
     *
     * status: WAITING — hali boshlanmagan, RUNNING — ketmoqda,
     *         MET — muddatida bajarilgan, BREACHED — kechikkan.
     *
     * @return array<string, mixed>
     */
    private function stage(string $key, int $minutes, ?Carbon $startedAt, ?Carbon $finishedAt): array
    {
        $dueAt = $startedAt?->copy()->addMinutes($minutes);

        $status = match (true) {
            $startedAt === null => 'WAITING',
            $finishedAt !== null => $finishedAt->greaterThan($dueAt) ? 'BREACHED' : 'MET',
            now()->greaterThan($dueAt) => 'BREACHED',
            default => 'RUNNING',
        };

        return [
            'key' => $key,
            'minutes' => $minutes,
            'startedAt' => $startedAt?->toIso8601String(),
            'dueAt' => $dueAt?->toIso8601String(),
            'finishedAt' => $finishedAt?->toIso8601String(),
            'status' => $status,
            // Qolgan vaqt faqat ketayotgan bosqichda ma'noli; kechikkanda manfiy.
            'remainingSeconds' => $status === 'RUNNING' ? (int) now()->diffInSeconds($dueAt, false) : null,
        ];
    }

    /**
     * Barcha kategoriyalar bir marta o'qiladi va so'rov davomida eslab
     * qolinadi — zayavkalar ro'yxatida har bir satr uchun alohida so'rov
     * ketmasligi uchun.
     *
     * @return array{0: array<int, object>, 1: array<string, object>}
     */
    private function categories(): array
    {
        if (self::$cache === null) {
            $rows = DB::table('categories')->whereNull('deleted_at')
                ->get(['id', 'name', 'sla_accept_minutes', 'sla_work_minutes', 'sla_close_minutes']);

            $byId = [];
            $byName = [];
            foreach ($rows as $row) {
                $byId[(int) $row->id] = $row;
                $byName[mb_strtolower(trim((string) $row->name))] = $row;
            }

            self::$cache = [$byId, $byName];
        }

        return self::$cache;
    }

    private function at(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
