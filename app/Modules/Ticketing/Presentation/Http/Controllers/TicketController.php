<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketCollection;
use App\Http\Resources\TicketResource;
use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Domain\Services\AssignTicketService;
use App\Modules\Ticketing\Domain\Services\CreateTicketService;
use App\Modules\Ticketing\Domain\Services\TransitionTicketService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 15), 100);
        $skip = (int) $request->input('skip', 0);
        $search = $request->input('search', '');
        $status = $request->input('status', 'all');
        $priority = $request->input('priority', 'all');
        $targetDepartment = $request->input('targetDepartment', 'all');

        $user = $request->user() ?? auth()->user();
        $isSuper = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        $isStaff = false;
        if ($user) {
            if ($isSuper) {
                $isStaff = true;
            } elseif (method_exists($user, 'isDepartmentAdmin') && $user->isDepartmentAdmin()) {
                $isStaff = true;
            } elseif ($user->hasPermission('tickets.view') || $user->hasPermission('tickets.assign')) {
                $isStaff = true;
            }
        }

        $scope = $request->input('scope', 'all');

        $query = Ticket::with(['assignedUser', 'requesterEmployee', 'department'])
            ->whereNull('deleted_at');

        // Scope filtering
        if (!$user) {
            $query->whereRaw('1=0');
        } elseif ($isSuper) {
            if ($scope === 'my_tasks') {
                $query->where('assigned_user_id', $user->id);
            } elseif ($scope === 'my_submitted') {
                $query->where('requester_user_id', $user->id);
            }
            // scope === 'all' -> Superadmin sees ALL tickets without restriction
        } else {
            $employee = DB::table('employees')->where('id', $user->employee_id)->first();
            $deptId = $employee ? $employee->department_id : 1;

            if (!$isStaff || $scope === 'my_submitted') {
                $query->where('requester_user_id', $user->id);
            } elseif ($scope === 'my_tasks') {
                $query->where('assigned_user_id', $user->id);
            } else {
                // Department-scoped 'all': see tickets belonging to staff's department or assigned/requested by staff
                $query->where(function ($q) use ($user, $deptId) {
                    $q->where('department_id', $deptId)
                      ->orWhere('assigned_user_id', $user->id)
                      ->orWhere('requester_user_id', $user->id);
                });
            }
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ticket_no', 'like', "%{$search}%")
                    ->orWhere('initiator_name', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $statusIds = TicketResource::mapStatusToIds($status);
            $query->whereIn('status_id', $statusIds);
        }

        if ($priority !== 'all') {
            $priorityId = TicketResource::mapPriorityToId($priority);
            $query->where('priority_id', $priorityId);
        }

        if ($targetDepartment !== 'all') {
            $query->where('target_department', $targetDepartment);
        }

        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = $query->count();

        $tickets = $query->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return response()->json([
            'tasks' => TicketResource::collection($tickets),
            'total' => $total,
            'skip' => $skip,
            'limit' => $limit,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with(['assignedUser', 'requesterEmployee', 'requesterUser', 'department'])
            ->whereNull('deleted_at')
            ->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        return response()->json(
            new TicketResource($ticket),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        // Rule: If requester has any unrated completed tickets, block creation of new ticket
        $unratedTicket = Ticket::whereNull('deleted_at')
            ->where('requester_user_id', $user->id)
            ->whereIn('status_id', [7, 8])
            ->whereNull('client_rating')
            ->first();

        if ($unratedTicket) {
            return response()->json([
                'message' => "Eski zayafkangizni baholang! Yangi zayavka yuborishdan oldin bajarilgan zayafkangiz (#{$unratedTicket->ticket_no}) ga baho bering yoki qaytaring.",
                'ticket_no' => $unratedTicket->ticket_no,
                'unrated_blocking' => true,
            ], 422);
        }

        $validated = $request->validate([
            'todo' => 'required|string|min:3|max:500',
            'category' => 'nullable|string|max:255',
            'targetDepartment' => 'nullable|in:hardware,software',
            'teamId' => 'nullable|integer|exists:teams,id',
            'assigned_team_id' => 'nullable|integer|exists:teams,id',
            'originDepartment' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:128',
            'initiatorName' => 'nullable|string|max:255',
            'initiatorPhone' => 'nullable|string|max:32',
            'deviceName' => 'nullable|string|max:255',
            'brokenUrl' => 'nullable|url|max:2048',
            'status' => 'nullable|in:todo,in_progress,done,rejected',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $ticket = DB::transaction(function () use ($validated, $user) {
            $ticketNo = $this->ticketRepository->nextNumber(1);

            $statusId = $validated['status'] ?? null
                ? TicketResource::mapStatusToIds($validated['status'])[0]
                : 1;

            $priorityId = $validated['priority'] ?? null
                ? TicketResource::mapPriorityToId($validated['priority'])
                : 3;

            $targetDepartment = $validated['targetDepartment'] ?? 'hardware';
            $teamId = $validated['teamId'] ?? $validated['assigned_team_id'] ?? null;

            $ticket = Ticket::create([
                'public_id' => (string) Str::uuid(),
                'organization_id' => 1,
                'ticket_no' => $ticketNo,
                'ticket_type' => 'INCIDENT',
                'subject' => $validated['todo'],
                'description' => $validated['todo'],
                'status_id' => $statusId,
                'priority_id' => $priorityId,
                'source_id' => 1,
                'requester_user_id' => $user->id,
                'department_id' => 1,
                'assigned_team_id' => $teamId,
                'category' => $validated['category'] ?? 'Texnik yordam',
                'target_department' => $targetDepartment,
                'origin_department' => $validated['originDepartment'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'initiator_name' => $validated['initiatorName'] ?? null,
                'initiator_phone' => $validated['initiatorPhone'] ?? null,
                'device_name' => $validated['deviceName'] ?? null,
                'broken_url' => $validated['brokenUrl'] ?? null,
            ]);

            DB::table('ticket_status_history')->insert([
                'ticket_id' => $ticket->id,
                'from_status_id' => null,
                'to_status_id' => $statusId,
                'changed_by' => $user->id,
                'source_id' => 1,
                'action' => 'TICKET_CREATED',
                'correlation_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $ticket;
        });

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);

        return response()->json(
            new TicketResource($ticket),
            201,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::whereNull('deleted_at')->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        $validated = $request->validate([
            'todo' => 'nullable|string|min:3|max:500',
            'status' => 'nullable|in:todo,in_progress,done,rejected',
            'priority' => 'nullable|in:low,medium,high',
            'completed' => 'nullable|boolean',
            'assignToMe' => 'nullable|boolean',
            'rejectionReason' => 'nullable|string',
            'solutionComment' => 'nullable|string',
            'clientRating' => 'nullable|integer|min:1|max:5',
        ]);

        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        // Rule: Limit of max 3 tasks in "todo" (To Do) state per employee
        $willBeAssignedTo = !empty($validated['assignToMe']) ? $user->id : ($ticket->assigned_user_id ?? $user->id);
        $targetStatusId = isset($validated['status']) ? TicketResource::mapStatusToIds($validated['status'])[0] : $ticket->status_id;

        if (in_array($targetStatusId, [1, 2, 3]) && $willBeAssignedTo && (!empty($validated['assignToMe']) || $ticket->assigned_user_id !== $willBeAssignedTo)) {
            $currentTodoCount = Ticket::whereNull('deleted_at')
                ->where('assigned_user_id', $willBeAssignedTo)
                ->whereIn('status_id', [1, 2, 3])
                ->where('id', '!=', $ticket->id)
                ->count();

            if ($currentTodoCount >= 3) {
                return response()->json([
                    'message' => "Siz bir vaqtning o'zida 'To Do' holatida 3 tadan ortiq zayavka ololmaysiz. Avval mavjud zayavkalardan birini jarayonga o'tkazing yoki yakunlang!",
                    'limit_exceeded' => true,
                ], 422);
            }
        }

        DB::transaction(function () use ($ticket, $validated, $user) {
            $statusChanged = false;
            $oldStatusId = $ticket->status_id;

            if (isset($validated['todo'])) {
                $ticket->subject = $validated['todo'];
                $ticket->description = $validated['todo'];
            }

            // "Qabul qilish" (Accept) / Takeover:
            // Check if reassigned from a teammate
            if (!empty($validated['assignToMe'])) {
                if (!is_null($ticket->assigned_user_id) && $ticket->assigned_user_id != $user->id) {
                    DB::table('ticket_reassignments')->insert([
                        'ticket_id' => $ticket->id,
                        'from_user_id' => $ticket->assigned_user_id,
                        'to_user_id' => $user->id,
                        'reassigned_by' => $user->id,
                        'reason' => 'Sherigi zayavkasini o\'ziga biriktirdi (Takeover)',
                        'created_at' => now(),
                    ]);
                }
                $ticket->assigned_user_id = $user->id;
            }

            if (isset($validated['status'])) {
                $newStatusIds = TicketResource::mapStatusToIds($validated['status']);
                $ticket->status_id = $newStatusIds[0];
                $statusChanged = true;

                if ($validated['status'] === 'in_progress') {
                    if (is_null($ticket->assigned_user_id)) {
                        $ticket->assigned_user_id = $user->id;
                    }
                    if (is_null($ticket->started_at)) {
                        $ticket->started_at = now();
                    }
                }

                if ($validated['status'] === 'done') {
                    $ticket->rejection_reason = null;
                    $ticket->resolved_at = now();
                    if ($ticket->started_at) {
                        $mins = (int) now()->diffInMinutes($ticket->started_at);
                        $ticket->spent_minutes = max(1, $mins);
                    }
                }
            }

            if (isset($validated['priority'])) {
                $ticket->priority_id = TicketResource::mapPriorityToId($validated['priority']);
            }

            if (isset($validated['rejectionReason'])) {
                $ticket->rejection_reason = $validated['rejectionReason'];
            }

            if (isset($validated['solutionComment'])) {
                $ticket->solution_comment = $validated['solutionComment'];
            }

            if (isset($validated['clientRating'])) {
                $ticket->client_rating = $validated['clientRating'];
                if (!in_array($ticket->status_id, [7, 8])) {
                    $ticket->status_id = 7;
                    $statusChanged = true;
                }
                $ticket->resolved_at = now();
                if ($ticket->started_at && $ticket->spent_minutes == 0) {
                    $mins = (int) now()->diffInMinutes($ticket->started_at);
                    $ticket->spent_minutes = max(1, $mins);
                }
            }

            if (isset($validated['completed']) && $validated['completed']) {
                if (!in_array($ticket->status_id, [7, 8])) {
                    $ticket->status_id = 7;
                    $statusChanged = true;
                }
                $ticket->rejection_reason = null;
                $ticket->resolved_at = now();
                if ($ticket->started_at && $ticket->spent_minutes == 0) {
                    $mins = (int) now()->diffInMinutes($ticket->started_at);
                    $ticket->spent_minutes = max(1, $mins);
                }
            }

            if ($statusChanged) {
                DB::table('ticket_status_history')->insert([
                    'ticket_id' => $ticket->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id' => $ticket->status_id,
                    'changed_by' => $user->id,
                    'source_id' => 1,
                    'action' => 'STATUS_UPDATED',
                    'correlation_id' => (string) Str::uuid(),
                    'created_at' => now(),
                ]);
            }

            $ticket->save();
        });

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);

        return response()->json(
            new TicketResource($ticket),
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $ticket = Ticket::whereNull('deleted_at')->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        $ticket->delete();

        return response()->json(['message' => 'Zayavka o\'chirildi']);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'to_status_id' => 'required|integer|exists:ticket_statuses,id',
            'reason' => 'nullable|string',
        ]);

        $service = app(TransitionTicketService::class);
        $ticket = $service->execute($id, $validated['to_status_id'], auth()->id(), $validated['reason'] ?? null);

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);

        return response()->json(new TicketResource($ticket));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'nullable|integer',
            'assignee_user_id' => 'nullable|integer',
            'reason' => 'nullable|string',
        ]);

        $service = app(AssignTicketService::class);
        $ticket = $service->execute(
            $id,
            $validated['team_id'] ?? null,
            $validated['assignee_user_id'] ?? auth()->id(),
            auth()->id() ?? 1,
            $validated['reason'] ?? null,
        );

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);

        return response()->json(new TicketResource($ticket));
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['total' => 0, 'completed' => 0, 'hardware' => 0, 'software' => 0]);
        }
        $userId = $user->id;

        // User's own personal completed stats
        $myCompleted = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8])
            ->count();

        $todayStart = now()->startOfDay();
        $myTodayCompleted = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8])
            ->where('updated_at', '>=', $todayStart)
            ->count();

        $myTasks = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [1, 2, 3, 4, 5, 6])
            ->count();

        $total = Ticket::whereNull('deleted_at')->count();
        $hardware = Ticket::whereNull('deleted_at')->where('target_department', 'hardware')->count();
        $software = Ticket::whereNull('deleted_at')->where('target_department', 'software')->count();
        $open = Ticket::whereNull('deleted_at')->whereIn('status_id', [1, 2, 3])->count();
        $inProgress = Ticket::whereNull('deleted_at')->whereIn('status_id', [4, 5, 6])->count();
        $rejected = Ticket::whereNull('deleted_at')->where('status_id', 9)->count();

        // 7-day daily trend for user
        $dailyTrend = [];
        $dayNames = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
        $maxClosedCount = 0;
        $peakDay = "Ma'lumot yetarli emas";

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            $count = Ticket::whereNull('deleted_at')
                ->where('assigned_user_id', $userId)
                ->whereIn('status_id', [7, 8])
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $dName = $dayNames[$date->dayOfWeek];
            $dailyTrend[] = [
                'date' => $date->format('Y-m-d'),
                'dayName' => $dName,
                'shortDay' => $date->format('d.m'),
                'count' => $count,
            ];

            if ($count > 0 && $count >= $maxClosedCount) {
                $maxClosedCount = $count;
                $peakDay = "{$dName} ({$count} ta zayavka yopilgan)";
            }
        }

        $avgSpent = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8])
            ->where('spent_minutes', '>', 0)
            ->avg('spent_minutes');

        $avgRating = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8])
            ->whereNotNull('client_rating')
            ->avg('client_rating');

        return response()->json([
            'total' => $total,
            'completed' => $myCompleted,
            'hardware' => $hardware,
            'software' => $software,
            'open' => $open,
            'inProgress' => $inProgress,
            'rejected' => $rejected,
            'myTasks' => $myTasks,
            'todayCompleted' => $myTodayCompleted,
            'avgSpentMinutes' => round((float) ($avgSpent ?: 1), 1),
            'avgRating' => round((float) ($avgRating ?: 5.0), 1),
            'dailyTrend' => $dailyTrend,
            'peakDay' => $peakDay,
            'maxClosedCount' => $maxClosedCount,
        ]);
    }

    public function monitoring(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        $isSuper = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        $employeesQuery = DB::table('users')
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->whereNull('users.deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('model_has_roles.role_id')
                  ->orWhereNotNull('users.employee_id')
                  ->orWhere('users.username', 'admin')
                  ->orWhere('users.username', 'superadmin');
            });

        if (!$isSuper && $user) {
            // Department-scoped: get employees in the same department
            $employee = DB::table('employees')->where('id', $user->employee_id)->first();
            $deptId = $employee ? $employee->department_id : 1;

            $employeesQuery->where(function ($q) use ($deptId, $user) {
                $q->where('employees.department_id', $deptId)
                  ->orWhere('users.id', $user->id);
            });
        }

        $employees = $employeesQuery
            ->select('users.id', 'users.username', 'users.image', 'employees.first_name', 'employees.last_name', 'employees.phone')
            ->distinct()
            ->get();

        $employeeStats = [];
        $employeeAvatars = [];

        foreach ($employees as $emp) {
            $name = trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')) ?: $emp->username;

            $todo = Ticket::whereNull('deleted_at')->where('assigned_user_id', $emp->id)->whereIn('status_id', [1, 2, 3])->count();
            $inProgress = Ticket::whereNull('deleted_at')->where('assigned_user_id', $emp->id)->whereIn('status_id', [4, 5, 6])->count();
            $rejected = Ticket::whereNull('deleted_at')->where('assigned_user_id', $emp->id)->where('status_id', 9)->count();
            $done = Ticket::whereNull('deleted_at')->where('assigned_user_id', $emp->id)->whereIn('status_id', [7, 8])->count();

            $avgSpent = Ticket::whereNull('deleted_at')
                ->where('assigned_user_id', $emp->id)
                ->whereIn('status_id', [7, 8])
                ->where('spent_minutes', '>', 0)
                ->avg('spent_minutes');

            $activeCount = $todo + $inProgress + $rejected;

            $employeeAvatars[] = [
                'userId' => $emp->id,
                'name' => $name,
                'username' => $emp->username,
                'activeCount' => $activeCount,
                'avatarUrl' => $emp->image ?: ("https://ui-avatars.com/api/?name=" . urlencode($name) . "&size=512&bold=true&background=0D8ABC&color=fff"),
            ];

            if ($todo > 0 || $inProgress > 0 || $rejected > 0 || $done > 0) {
                $employeeStats[] = [
                    'userId' => $emp->id,
                    'name' => $name,
                    'username' => $emp->username,
                    'todo' => $todo,
                    'inProgress' => $inProgress,
                    'rejected' => $rejected,
                    'done' => $done,
                    'totalActive' => $activeCount,
                    'avgSpentMinutes' => round((float) ($avgSpent ?? 0), 1),
                ];
            }
        }

        // Reassignment audit report
        $reassignments = DB::table('ticket_reassignments')
            ->leftJoin('users as from_u', 'ticket_reassignments.from_user_id', '=', 'from_u.id')
            ->leftJoin('users as to_u', 'ticket_reassignments.to_user_id', '=', 'to_u.id')
            ->leftJoin('tickets', 'ticket_reassignments.ticket_id', '=', 'tickets.id')
            ->select(
                'ticket_reassignments.id',
                'ticket_reassignments.ticket_id',
                'ticket_reassignments.created_at',
                'ticket_reassignments.reason',
                'tickets.ticket_no',
                'tickets.subject',
                'from_u.username as from_username',
                'to_u.username as to_username'
            )
            ->orderBy('ticket_reassignments.created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'employeeStats' => $employeeStats,
            'employeeAvatars' => $employeeAvatars,
            'reassignments' => $reassignments,
        ]);
    }
}
