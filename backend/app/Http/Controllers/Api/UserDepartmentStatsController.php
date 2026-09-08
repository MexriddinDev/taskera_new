<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserDepartmentStatsController extends Controller
{
    /**
     * Bo'limlar bo'yicha zayavkalar statistikasi va reytingi.
     * Qaysi bo'limdan ko'proq zayavka tushayotganini, holatlar va trendlarni qaytaradi.
     */
    public function departmentStats(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', 'month');
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');
        $branchId = $request->query('branch_id');
        $search = trim((string) $request->query('search', ''));

        // Sana oralig'ini hisoblash
        [$dateStart, $dateEnd] = $this->resolveDateRange($period, $startDateInput, $endDateInput);

        // Baza query
        $baseQuery = DB::table('tickets')
            ->leftJoin('employees as req_emp', 'tickets.requester_employee_id', '=', 'req_emp.id')
            ->leftJoin('departments as dep', function ($join) {
                $join->on('tickets.department_id', '=', 'dep.id')
                    ->orWhere(function ($q) {
                        $q->whereNull('tickets.department_id')
                          ->whereColumn('req_emp.department_id', '=', 'dep.id');
                    });
            })
            ->leftJoin('branches as br', function ($join) {
                $join->on('tickets.branch_id', '=', 'br.id')
                    ->orWhere(function ($q) {
                        $q->whereNull('tickets.branch_id')
                          ->whereColumn('dep.branch_id', '=', 'br.id');
                    });
            })
            ->whereNull('tickets.deleted_at');

        if ($dateStart) {
            $baseQuery->where('tickets.created_at', '>=', $dateStart);
        }
        if ($dateEnd) {
            $baseQuery->where('tickets.created_at', '<=', $dateEnd);
        }

        if ($branchId && $branchId !== 'all') {
            $baseQuery->where(function ($q) use ($branchId) {
                $q->where('tickets.branch_id', $branchId)
                  ->orWhere('dep.branch_id', $branchId);
            });
        }

        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('dep.name', 'like', "%{$search}%")
                  ->orWhere('dep.code', 'like', "%{$search}%")
                  ->orWhere('tickets.origin_department', 'like', "%{$search}%")
                  ->orWhere('br.name', 'like', "%{$search}%");
            });
        }

        // 1. Jami zayavkalar soni
        $totalTickets = (clone $baseQuery)->count();

        // 2. Bo'limlar bo'yicha guruhlash
        $rawDepartments = (clone $baseQuery)
            ->select(
                DB::raw("COALESCE(dep.id, 0) as department_id"),
                DB::raw("COALESCE(dep.name, tickets.origin_department, 'Belgilanmagan bo\\'lim') as department_name"),
                DB::raw("COALESCE(dep.code, 'OTHER') as department_code"),
                DB::raw("COALESCE(br.id, 0) as branch_id"),
                DB::raw("COALESCE(br.name, '—') as branch_name"),
                DB::raw("COUNT(tickets.id) as ticket_count"),
                DB::raw("COUNT(DISTINCT tickets.requester_user_id) as active_users_count"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (1, 2, 3) THEN 1 END) as open_count"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (4, 5, 6) THEN 1 END) as in_progress_count"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (7, 8) THEN 1 END) as resolved_count"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (9, 10) THEN 1 END) as rejected_count"),
                DB::raw("COUNT(CASE WHEN tickets.priority_id = 1 THEN 1 END) as critical_count"),
                DB::raw("COUNT(CASE WHEN tickets.priority_id = 2 THEN 1 END) as high_count"),
                DB::raw("COUNT(CASE WHEN tickets.target_department = 'hardware' THEN 1 END) as hardware_count"),
                DB::raw("COUNT(CASE WHEN tickets.target_department = 'software' THEN 1 END) as software_count"),
                DB::raw("COUNT(CASE WHEN tickets.target_department = '1c' THEN 1 END) as one_c_count"),
                DB::raw("COUNT(CASE WHEN tickets.target_department = 'network' THEN 1 END) as network_count"),
                DB::raw("AVG(CASE WHEN tickets.status_id IN (7, 8) AND tickets.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, tickets.created_at, tickets.resolved_at) END) as avg_resolution_minutes"),
                DB::raw("MAX(tickets.created_at) as last_ticket_at")
            )
            ->groupBy(
                DB::raw("COALESCE(dep.id, 0)"),
                DB::raw("COALESCE(dep.name, tickets.origin_department, 'Belgilanmagan bo\\'lim')"),
                DB::raw("COALESCE(dep.code, 'OTHER')"),
                DB::raw("COALESCE(br.id, 0)"),
                DB::raw("COALESCE(br.name, '—')")
            )
            ->orderByDesc('ticket_count')
            ->get();

        $departmentsList = $rawDepartments->map(function ($row) use ($totalTickets) {
            $count = (int) $row->ticket_count;
            $percentage = $totalTickets > 0 ? round(($count / $totalTickets) * 100, 1) : 0;
            $avgMinutes = $row->avg_resolution_minutes !== null ? (int) round((float) $row->avg_resolution_minutes) : null;
            $resolvedCount = (int) $row->resolved_count;
            $resRate = $count > 0 ? round(($resolvedCount / $count) * 100, 1) : 0;

            return [
                'department_id' => (int) $row->department_id,
                'department_name' => $row->department_name,
                'department_code' => $row->department_code,
                'branch_id' => (int) $row->branch_id,
                'branch_name' => $row->branch_name,
                'total_tickets' => $count,
                'percentage' => $percentage,
                'active_users_count' => (int) $row->active_users_count,
                'open_tickets' => (int) $row->open_count,
                'in_progress_tickets' => (int) $row->in_progress_count,
                'resolved_tickets' => $resolvedCount,
                'rejected_tickets' => (int) $row->rejected_count,
                'resolution_rate' => $resRate,
                'critical_tickets' => (int) $row->critical_count,
                'high_tickets' => (int) $row->high_count,
                'target_breakdown' => [
                    'hardware' => (int) $row->hardware_count,
                    'software' => (int) $row->software_count,
                    'one_c' => (int) $row->one_c_count,
                    'network' => (int) $row->network_count,
                ],
                'avg_resolution_minutes' => $avgMinutes,
                'avg_resolution_formatted' => $this->formatMinutes($avgMinutes),
                'last_ticket_at' => $row->last_ticket_at,
            ];
        });

        // 3. Umumiy KPI ko'rsatkichlar
        $totalResolved = (int) $departmentsList->sum('resolved_tickets');
        $totalOpen = (int) $departmentsList->sum('open_tickets');
        $totalInProgress = (int) $departmentsList->sum('in_progress_tickets');
        $totalRejected = (int) $departmentsList->sum('rejected_tickets');
        $totalRequesters = (clone $baseQuery)->distinct('tickets.requester_user_id')->count('tickets.requester_user_id');

        $overallResolutionRate = $totalTickets > 0 ? round(($totalResolved / $totalTickets) * 100, 1) : 0;

        $topDepartment = $departmentsList->first();

        // 4. Target sohalar (Hardware, Software, 1C, Tarmoq) taqsimoti
        $targetStats = (clone $baseQuery)
            ->select('tickets.target_department', DB::raw('COUNT(*) as count'))
            ->whereNotNull('tickets.target_department')
            ->groupBy('tickets.target_department')
            ->orderByDesc('count')
            ->get()
            ->map(fn($item) => [
                'target' => $item->target_department ?: 'boshqa',
                'count' => (int) $item->count,
                'percentage' => $totalTickets > 0 ? round(((int) $item->count / $totalTickets) * 100, 1) : 0,
            ]);

        // 5. Prioritet taqsimoti
        $priorityStats = (clone $baseQuery)
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->select('ticket_priorities.id', 'ticket_priorities.name', 'ticket_priorities.code', 'ticket_priorities.color', DB::raw('COUNT(tickets.id) as count'))
            ->groupBy('ticket_priorities.id', 'ticket_priorities.name', 'ticket_priorities.code', 'ticket_priorities.color')
            ->orderBy('ticket_priorities.id')
            ->get();

        // 6. Vaqt bo'yicha trend (kunlik/oylik taqsimot)
        $trendData = $this->calculateTimelineTrend($baseQuery, $period, $dateStart, $dateEnd);

        return response()->json([
            'success' => true,
            'kpis' => [
                'total_tickets' => $totalTickets,
                'total_departments' => $departmentsList->count(),
                'total_requesters' => $totalRequesters,
                'total_open' => $totalOpen,
                'total_in_progress' => $totalInProgress,
                'total_resolved' => $totalResolved,
                'total_rejected' => $totalRejected,
                'resolution_rate' => $overallResolutionRate,
                'top_department' => $topDepartment ? [
                    'id' => $topDepartment['department_id'],
                    'name' => $topDepartment['department_name'],
                    'count' => $topDepartment['total_tickets'],
                    'percentage' => $topDepartment['percentage'],
                ] : null,
            ],
            'departments' => $departmentsList,
            'top_departments' => $departmentsList->take(8)->values(),
            'target_breakdown' => $targetStats,
            'priority_breakdown' => $priorityStats,
            'trend' => $trendData,
            'period' => $period,
            'date_range' => [
                'start' => $dateStart?->toIso8601String(),
                'end' => $dateEnd?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Foydalanuvchilar (Zayavka yuboruvchi xodimlar) ro'yxati va ularning zayavka statistikasi.
     */
    public function requesterStats(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', 'month');
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');
        $departmentId = $request->query('department_id');
        $branchId = $request->query('branch_id');
        $search = trim((string) $request->query('search', ''));
        $sortBy = (string) $request->query('sort_by', 'total_tickets');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $page = max((int) $request->query('page', 1), 1);

        [$dateStart, $dateEnd] = $this->resolveDateRange($period, $startDateInput, $endDateInput);

        $query = DB::table('users')
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
            ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
            ->leftJoin('tickets', function ($join) use ($dateStart, $dateEnd) {
                $join->on('users.id', '=', 'tickets.requester_user_id')
                    ->whereNull('tickets.deleted_at');
                if ($dateStart) {
                    $join->where('tickets.created_at', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $join->where('tickets.created_at', '<=', $dateEnd);
                }
            })
            ->whereNull('users.deleted_at')
            ->select(
                'users.id as user_id',
                'users.username',
                'users.image',
                'employees.id as employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.middle_name',
                'employees.email as employee_email',
                'employees.phone as employee_phone',
                'departments.id as department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'branches.id as branch_id',
                'branches.name as branch_name',
                'positions.name as position_name',
                DB::raw("COUNT(tickets.id) as total_tickets"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (1, 2, 3) THEN 1 END) as open_tickets"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (4, 5, 6) THEN 1 END) as in_progress_tickets"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (7, 8) THEN 1 END) as resolved_tickets"),
                DB::raw("COUNT(CASE WHEN tickets.status_id IN (9, 10) THEN 1 END) as rejected_tickets"),
                DB::raw("MAX(tickets.created_at) as last_ticket_at")
            )
            ->groupBy(
                'users.id',
                'users.username',
                'users.image',
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.middle_name',
                'employees.email',
                'employees.phone',
                'departments.id',
                'departments.name',
                'departments.code',
                'branches.id',
                'branches.name',
                'positions.name'
            );

        if ($departmentId && $departmentId !== 'all') {
            $query->where('departments.id', $departmentId);
        }

        if ($branchId && $branchId !== 'all') {
            $query->where('branches.id', $branchId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.username', 'like', "%{$search}%")
                  ->orWhere('employees.first_name', 'like', "%{$search}%")
                  ->orWhere('employees.last_name', 'like', "%{$search}%")
                  ->orWhere('employees.phone', 'like', "%{$search}%")
                  ->orWhere('departments.name', 'like', "%{$search}%")
                  ->orWhere('positions.name', 'like', "%{$search}%");
            });
        }

        // Saralash
        $allowedSorts = [
            'total_tickets' => 'total_tickets',
            'open_tickets' => 'open_tickets',
            'resolved_tickets' => 'resolved_tickets',
            'last_ticket_at' => 'last_ticket_at',
            'username' => 'users.username',
            'first_name' => 'employees.first_name',
            'department_name' => 'departments.name',
        ];

        $sortColumn = $allowedSorts[$sortBy] ?? 'total_tickets';
        $query->orderBy($sortColumn, $sortOrder);
        if ($sortColumn !== 'users.id') {
            $query->orderBy('users.id', 'desc');
        }

        $totalCount = (clone $query)->get()->count();
        $offset = ($page - 1) * $perPage;

        $items = $query->offset($offset)->limit($perPage)->get();

        $userIds = $items->pluck('user_id')->filter()->toArray();
        $lastTicketSubjects = [];
        if (!empty($userIds)) {
            $recentTickets = DB::table('tickets')
                ->whereIn('requester_user_id', $userIds)
                ->whereNull('deleted_at')
                ->select('requester_user_id', 'ticket_no', 'subject', 'created_at')
                ->orderByDesc('created_at')
                ->get()
                ->unique('requester_user_id')
                ->keyBy('requester_user_id');

            foreach ($recentTickets as $uId => $tRow) {
                $lastTicketSubjects[$uId] = [
                    'ticket_no' => $tRow->ticket_no,
                    'subject' => $tRow->subject,
                ];
            }
        }

        $formatted = $items->map(function ($row) use ($lastTicketSubjects) {
            $fullName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            $lastTicket = $lastTicketSubjects[$row->user_id] ?? null;

            return [
                'user_id' => (int) $row->user_id,
                'username' => $row->username,
                'employee_id' => $row->employee_id ? (int) $row->employee_id : null,
                'full_name' => $fullName ?: $row->username,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'image' => $row->image,
                'email' => $row->employee_email,
                'phone' => $row->employee_phone,
                'department_id' => $row->department_id ? (int) $row->department_id : null,
                'department_name' => $row->department_name ?: 'Belgilanmagan',
                'department_code' => $row->department_code,
                'branch_id' => $row->branch_id ? (int) $row->branch_id : null,
                'branch_name' => $row->branch_name ?: '—',
                'position_name' => $row->position_name ?: 'Xodim',
                'total_tickets' => (int) $row->total_tickets,
                'open_tickets' => (int) $row->open_tickets,
                'in_progress_tickets' => (int) $row->in_progress_tickets,
                'resolved_tickets' => (int) $row->resolved_tickets,
                'rejected_tickets' => (int) $row->rejected_tickets,
                'last_ticket_at' => $row->last_ticket_at,
                'last_ticket_no' => $lastTicket['ticket_no'] ?? null,
                'last_ticket_subject' => $lastTicket['subject'] ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => (int) ceil($totalCount / $perPage),
            ],
        ]);
    }

    /**
     * Muayyan bo'limga tegishli zayavkalar ro'yxatini tezkor ko'rish.
     */
    public function departmentTickets(Request $request, $id): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 15), 5), 50);
        $status = $request->query('status');

        $query = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('employees as req_emp', 'tickets.requester_employee_id', '=', 'req_emp.id')
            ->leftJoin('users as assign_user', 'tickets.assigned_user_id', '=', 'assign_user.id')
            ->whereNull('tickets.deleted_at');

        if ((int) $id > 0) {
            $query->where(function ($q) use ($id) {
                $q->where('tickets.department_id', $id)
                  ->orWhere('req_emp.department_id', $id);
            });
        } else {
            // Bo'limi belgilanmagan zayavkalar
            $query->whereNull('tickets.department_id')
                  ->whereNull('req_emp.department_id');
        }

        if ($status === 'open') {
            $query->whereIn('tickets.status_id', [1, 2, 3]);
        } elseif ($status === 'in_progress') {
            $query->whereIn('tickets.status_id', [4, 5, 6]);
        } elseif ($status === 'resolved') {
            $query->whereIn('tickets.status_id', [7, 8]);
        } elseif ($status === 'rejected') {
            $query->whereIn('tickets.status_id', [9, 10]);
        }

        $tickets = $query->select(
            'tickets.id',
            'tickets.ticket_no',
            'tickets.subject',
            'tickets.description',
            'tickets.target_department',
            'tickets.status_id',
            'tickets.priority_id',
            'tickets.created_at',
            'tickets.resolved_at',
            'ticket_statuses.name as status_name',
            'ticket_statuses.code as status_code',
            'ticket_statuses.color as status_color',
            'ticket_priorities.name as priority_name',
            'ticket_priorities.code as priority_code',
            'ticket_priorities.color as priority_color',
            'req_user.username as requester_username',
            'req_emp.first_name as requester_first_name',
            'req_emp.last_name as requester_last_name',
            'assign_user.username as assignee_username'
        )
        ->orderByDesc('tickets.created_at')
        ->limit($limit)
        ->get();

        return response()->json([
            'success' => true,
            'department_id' => (int) $id,
            'data' => $tickets,
        ]);
    }

    /**
     * Sana oralig'ini hisoblash.
     */
    private function resolveDateRange(string $period, ?string $start, ?string $end): array
    {
        $now = Carbon::now();

        if ($period === 'custom' && $start) {
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = $end ? Carbon::parse($end)->endOfDay() : $now->endOfDay();
            return [$startDate, $endDate];
        }

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'quarter' => [$now->copy()->subDays(90)->startOfDay(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'all' => [null, null],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    /**
     * Trend vaqt o'qi bo'yicha hisoblash.
     */
    private function calculateTimelineTrend($baseQuery, string $period, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $daysCount = match ($period) {
            'today' => 1,
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'year' => 365,
            default => 30,
        };

        if ($period === 'all') {
            $daysCount = 60;
        }

        $calcStart = $startDate ?: Carbon::now()->subDays($daysCount - 1)->startOfDay();

        $rows = (clone $baseQuery)
            ->selectRaw("DATE(tickets.created_at) as date_key, COUNT(*) as count")
            ->where('tickets.created_at', '>=', $calcStart)
            ->groupBy(DB::raw("DATE(tickets.created_at)"))
            ->orderBy('date_key')
            ->pluck('count', 'date_key');

        $result = [];
        $dayNames = ['Yak', 'Dush', 'Sesh', 'Chor', 'Pay', 'Juma', 'Shan'];

        $iterations = min($daysCount, 30);
        for ($i = $iterations - 1; $i >= 0; $i--) {
            $dt = Carbon::now()->subDays($i);
            $key = $dt->format('Y-m-d');
            $cnt = (int) ($rows[$key] ?? 0);

            $result[] = [
                'date' => $key,
                'short_date' => $dt->format('d.m'),
                'day_name' => $dayNames[$dt->dayOfWeek],
                'count' => $cnt,
            ];
        }

        return $result;
    }

    /**
     * Daqiqalarni tushunarli formatga o'tkazish.
     */
    private function formatMinutes(?int $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '—';
        }

        if ($minutes < 60) {
            return "{$minutes} daq";
        }

        $hours = floor($minutes / 60);
        $remMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remMinutes > 0 ? "{$hours}s {$remMinutes}d" : "{$hours} soat";
        }

        $days = floor($hours / 24);
        $remHours = $hours % 24;

        return "{$days} kun " . ($remHours > 0 ? "{$remHours}s" : "");
    }
}
