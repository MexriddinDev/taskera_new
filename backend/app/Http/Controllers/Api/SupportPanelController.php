<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Support paneli: qaysi xodim qancha zayavka olgan, nechtasini yopgan,
 * muddatga ulgurganmi va oxirgi ishi qachon bo'lgan.
 *
 * Bo'limlar statistikasidan (UserDepartmentStatsController) farqi — bu yerda
 * hisob MUROJAATCHI bo'yicha emas, MAS'UL XODIM (`assigned_user_id`) bo'yicha
 * yuritiladi.
 */
class SupportPanelController extends Controller
{
    /** Zayavka holati guruhlari — TicketResource dagi id'lar bilan bir xil. */
    private const OPEN = [1, 2, 3];

    private const IN_PROGRESS = [4, 5, 6];

    private const DONE = [7, 8];

    private const REJECTED = [9, 10];

    /** Support xodimlari va ularning ko'rsatkichlari. */
    public function staff(Request $request): JsonResponse
    {
        [$start, $end] = $this->range($request);
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $page = max((int) $request->query('page', 1), 1);

        $query = DB::table('users')
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
            ->leftJoin('tickets', function ($join) use ($start, $end) {
                $join->on('users.id', '=', 'tickets.assigned_user_id')->whereNull('tickets.deleted_at');
                if ($start) {
                    $join->where('tickets.created_at', '>=', $start);
                }
                if ($end) {
                    $join->where('tickets.created_at', '<=', $end);
                }
            })
            ->whereNull('users.deleted_at')
            ->where(function ($q) {
                // Support hisoblanadi: zayavka biriktirish huquqi bo'lgan yoki
                // kamida bitta zayavka biriktirilgan xodim.
                $q->whereIn('users.id', $this->staffUserIds())
                    ->orWhereExists(fn ($sub) => $sub->from('tickets as t2')
                        ->whereColumn('t2.assigned_user_id', 'users.id')->whereNull('t2.deleted_at'));
            })
            ->select(
                'users.id as user_id',
                'users.username',
                'users.image',
                'employees.first_name',
                'employees.last_name',
                'departments.name as department_name',
                'branches.name as branch_name',
                DB::raw('COUNT(tickets.id) as total_tickets'),
                DB::raw('COUNT(CASE WHEN tickets.status_id IN ('.implode(',', self::OPEN).') THEN 1 END) as open_tickets'),
                DB::raw('COUNT(CASE WHEN tickets.status_id IN ('.implode(',', self::IN_PROGRESS).') THEN 1 END) as in_progress_tickets'),
                DB::raw('COUNT(CASE WHEN tickets.status_id IN ('.implode(',', self::DONE).') THEN 1 END) as resolved_tickets'),
                DB::raw('COUNT(CASE WHEN tickets.status_id IN ('.implode(',', self::REJECTED).') THEN 1 END) as rejected_tickets'),
                DB::raw('AVG(tickets.client_rating) as avg_rating'),
                DB::raw('MAX(tickets.created_at) as last_ticket_at')
            )
            // SLA: muddati belgilangan zayavkalar va ulardan buzilganlari.
            // `NOW()` o'rniga bog'langan parametr — SQLite'da (testlar) bunday
            // funksiya yo'q, MySQL'da esa qiymat bir xil bo'lishi kerak.
            ->selectRaw('COUNT(CASE WHEN tickets.due_at IS NOT NULL THEN 1 END) as sla_tracked')
            ->selectRaw('COUNT(CASE WHEN tickets.due_at IS NOT NULL AND ((tickets.resolved_at IS NOT NULL AND tickets.resolved_at > tickets.due_at) OR (tickets.resolved_at IS NULL AND tickets.due_at < ?)) THEN 1 END) as sla_breached', [now()])
            ->selectRaw('AVG(CASE WHEN tickets.resolved_at IS NOT NULL THEN '.$this->minutesBetween('tickets.created_at', 'tickets.resolved_at').' END) as avg_resolution_minutes')
            ->groupBy('users.id', 'users.username', 'users.image', 'employees.first_name', 'employees.last_name',
                'departments.name', 'branches.name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.username', 'like', "%{$search}%")
                    ->orWhere('employees.first_name', 'like', "%{$search}%")
                    ->orWhere('employees.last_name', 'like', "%{$search}%")
                    ->orWhere('departments.name', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('total_tickets')->orderBy('users.id')->get();
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $lastTickets = $this->lastTickets($items->pluck('user_id')->all());

        return response()->json([
            'data' => $items->map(function ($row) use ($lastTickets) {
                $tracked = (int) $row->sla_tracked;
                $breached = (int) $row->sla_breached;

                return [
                    'user_id' => (int) $row->user_id,
                    'username' => $row->username,
                    'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: $row->username,
                    'image' => $row->image,
                    'department_name' => $row->department_name,
                    'branch_name' => $row->branch_name,
                    'total_tickets' => (int) $row->total_tickets,
                    'open_tickets' => (int) $row->open_tickets,
                    'in_progress_tickets' => (int) $row->in_progress_tickets,
                    'resolved_tickets' => (int) $row->resolved_tickets,
                    'rejected_tickets' => (int) $row->rejected_tickets,
                    'sla_tracked' => $tracked,
                    'sla_breached' => $breached,
                    'sla_compliance' => $tracked > 0 ? round(($tracked - $breached) / $tracked * 100, 1) : null,
                    'avg_resolution_minutes' => $row->avg_resolution_minutes === null ? null : (int) round((float) $row->avg_resolution_minutes),
                    'avg_rating' => $row->avg_rating === null ? null : round((float) $row->avg_rating, 1),
                    'last_ticket' => $lastTickets[$row->user_id] ?? null,
                ];
            })->all(),
            'meta' => ['total' => $total, 'per_page' => $perPage, 'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    /** Bitta support xodim zayavkalari — ro'yxat ko'rinishi uchun. */
    public function tickets(Request $request, int $userId): JsonResponse
    {
        [$start, $end] = $this->range($request);
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $page = max((int) $request->query('page', 1), 1);

        $query = DB::table('tickets')
            ->leftJoin('users as requester', 'tickets.requester_user_id', '=', 'requester.id')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->where('tickets.assigned_user_id', $userId)
            ->whereNull('tickets.deleted_at');

        // Muddat filtri: yopilganlar uchun yopilgan sana, qolganlari uchun yaratilgan sana.
        if ($start) {
            $query->where('tickets.created_at', '>=', $start);
        }
        if ($end) {
            $query->where('tickets.created_at', '<=', $end);
        }

        $groups = ['open' => self::OPEN, 'in_progress' => self::IN_PROGRESS, 'done' => self::DONE, 'rejected' => self::REJECTED];
        if (isset($groups[$status])) {
            $query->whereIn('tickets.status_id', $groups[$status]);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tickets.subject', 'like', "%{$search}%")
                    ->orWhere('tickets.ticket_no', 'like', "%{$search}%")
                    ->orWhere('tickets.description', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count('tickets.id');

        $items = $query->orderByDesc('tickets.created_at')
            ->offset(($page - 1) * $perPage)->limit($perPage)
            ->get([
                'tickets.id', 'tickets.ticket_no', 'tickets.subject', 'tickets.status_id', 'tickets.created_at',
                'tickets.resolved_at', 'tickets.due_at', 'tickets.client_rating', 'tickets.spent_minutes',
                'ticket_statuses.name as status_name', 'ticket_priorities.name as priority_name',
                'requester.username as requester_username',
            ]);

        return response()->json([
            'data' => $items->map(fn ($row) => [
                'id' => (int) $row->id,
                'ticket_no' => $row->ticket_no,
                'subject' => $row->subject,
                'status_id' => (int) $row->status_id,
                'status_name' => $row->status_name,
                'priority_name' => $row->priority_name,
                'requester_username' => $row->requester_username,
                'created_at' => $row->created_at,
                'resolved_at' => $row->resolved_at,
                'due_at' => $row->due_at,
                'client_rating' => $row->client_rating === null ? null : (int) $row->client_rating,
                'spent_minutes' => $row->spent_minutes === null ? null : (int) $row->spent_minutes,
                'sla_breached' => $row->due_at !== null && (
                    ($row->resolved_at !== null && $row->resolved_at > $row->due_at)
                    || ($row->resolved_at === null && Carbon::parse($row->due_at)->isPast())
                ),
            ])->all(),
            'meta' => ['total' => $total, 'per_page' => $perPage, 'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    /**
     * Ikki ustun orasidagi daqiqalar. MySQL'da TIMESTAMPDIFF, SQLite'da
     * (testlar) julianday — bitta so'rov ikkala muhitda ham ishlashi kerak.
     */
    private function minutesBetween(string $from, string $to): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday({$to}) - julianday({$from})) * 1440"
            : "TIMESTAMPDIFF(MINUTE, {$from}, {$to})";
    }

    /** `tickets.assign` huquqiga ega foydalanuvchilar (rol orqali yoki to'g'ridan-to'g'ri). */
    private function staffUserIds(): array
    {
        $permissionId = DB::table('permissions')->where('name', 'tickets.assign')->value('id');
        if (! $permissionId) {
            return [];
        }

        $roleIds = DB::table('role_has_permissions')->where('permission_id', $permissionId)->pluck('role_id');

        return DB::table('model_has_roles')->whereIn('role_id', $roleIds)->pluck('model_id')
            ->merge(DB::table('model_has_permissions')->where('permission_id', $permissionId)->pluck('model_id'))
            ->unique()->values()->all();
    }

    /** Har bir xodimning oxirgi zayavkasi. */
    private function lastTickets(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        $rows = DB::table('tickets')->whereIn('assigned_user_id', $userIds)->whereNull('deleted_at')
            ->orderByDesc('created_at')->get(['id', 'assigned_user_id', 'ticket_no', 'subject', 'created_at']);

        $last = [];
        foreach ($rows as $row) {
            if (! isset($last[$row->assigned_user_id])) {
                $last[$row->assigned_user_id] = ['id' => (int) $row->id, 'ticket_no' => $row->ticket_no,
                    'subject' => $row->subject, 'created_at' => $row->created_at];
            }
        }

        return $last;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function range(Request $request): array
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        return [
            $request->filled('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : null,
            $request->filled('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : null,
        ];
    }
}
