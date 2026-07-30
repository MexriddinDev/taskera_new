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

        $user = $request->user();
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

        // If no authenticated user - return empty
        if (!$user) {
            $query->whereRaw('1=0');
        } elseif ($isSuper) {
            // super admin - no additional filters
        } else {
            // get user's teams
            $teamIds = [];
            try {
                $teamIds = \App\Modules\Organization\Infrastructure\Eloquent\TeamMember::where('user_id', $user->id)
                    ->whereNull('left_at')
                    ->pluck('team_id')
                    ->toArray();
            } catch (\Throwable $e) {
                $teamIds = [];
            }

            if (!$isStaff || $scope === 'my_submitted') {
                $query->where('requester_user_id', $user->id);
            } elseif ($scope === 'my_tasks') {
                $query->where('assigned_user_id', $user->id);
            } else {
                $query->where(function ($q) use ($user, $teamIds) {
                    $q->where('requester_user_id', $user->id)
                      ->orWhere('assigned_user_id', $user->id);

                    if (!empty($teamIds)) {
                        $q->orWhereIn('assigned_team_id', $teamIds);
                    }
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
        $ticket = Ticket::with(['assignedUser', 'requesterEmployee', 'department'])
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

        $user = $request->user();

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

        $user = $request->user();

        DB::transaction(function () use ($ticket, $validated, $user) {
            $statusChanged = false;
            $oldStatusId = $ticket->status_id;

            if (isset($validated['todo'])) {
                $ticket->subject = $validated['todo'];
                $ticket->description = $validated['todo'];
            }

            // "Qabul qilish" (Accept): assign the ticket to the current admin
            // but keep it in the TODO state so it appears under "Qabul qilingan"
            // in the admin's My Tasks. Actual work start moves it to in_progress.
            if (!empty($validated['assignToMe']) && is_null($ticket->assigned_user_id)) {
                $ticket->assigned_user_id = $user->id;
            }

            if (isset($validated['status'])) {
                $newStatusIds = TicketResource::mapStatusToIds($validated['status']);
                $ticket->status_id = $newStatusIds[0];
                $statusChanged = true;

                if ($validated['status'] === 'in_progress' && is_null($ticket->assigned_user_id)) {
                    $ticket->assigned_user_id = $user->id;
                }

                if ($validated['status'] === 'done') {
                    $ticket->rejection_reason = null;
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
            }

            if (isset($validated['completed']) && $validated['completed']) {
                if (!in_array($ticket->status_id, [7, 8])) {
                    $ticket->status_id = 7;
                    $statusChanged = true;
                }
                $ticket->rejection_reason = null;
                $ticket->resolved_at = now();
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
        $user = $request->user();
        $userId = $user->id;

        // Super admin sees everything
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            $total = Ticket::whereNull('deleted_at')->count();
            $completed = Ticket::whereNull('deleted_at')->whereIn('status_id', [7, 8])->count();
            $hardware = Ticket::whereNull('deleted_at')->where('target_department', 'hardware')->count();
            $software = Ticket::whereNull('deleted_at')->where('target_department', 'software')->count();
            $open = Ticket::whereNull('deleted_at')->whereIn('status_id', [1, 2, 3])->count();
            $inProgress = Ticket::whereNull('deleted_at')->whereIn('status_id', [4, 5, 6])->count();
            $rejected = Ticket::whereNull('deleted_at')->where('status_id', 9)->count();
            $myTasks = Ticket::whereNull('deleted_at')->where('assigned_user_id', $userId)->count();

            $todayStart = now()->startOfDay();
            $todayCompleted = Ticket::whereNull('deleted_at')
                ->whereIn('status_id', [7, 8])
                ->where('updated_at', '>=', $todayStart)
                ->count();

            return response()->json([
                'total' => $total,
                'completed' => $completed,
                'hardware' => $hardware,
                'software' => $software,
                'open' => $open,
                'inProgress' => $inProgress,
                'rejected' => $rejected,
                'myTasks' => $myTasks,
                'todayCompleted' => $todayCompleted,
            ]);
        }

        // For non-super users, only show tickets relevant to the user or their teams
        $teamIds = [];
        try {
            $teamIds = \App\Modules\Organization\Infrastructure\Eloquent\TeamMember::where('user_id', $userId)
                ->whereNull('left_at')
                ->pluck('team_id')
                ->toArray();
        } catch (\Throwable $e) {
            // fallback to empty array if TeamMember model not available
            $teamIds = [];
        }

        $baseQuery = function () use ($userId, $teamIds) {
            return Ticket::whereNull('deleted_at')
                ->where(function ($q) use ($userId, $teamIds) {
                    $q->where('requester_user_id', $userId)
                      ->orWhere('assigned_user_id', $userId);

                    if (!empty($teamIds)) {
                        $q->orWhereIn('assigned_team_id', $teamIds)
                          ->orWhereIn('assigned_team_id', $teamIds);
                    }
                });
        };

        $total = $baseQuery()->count();
        $completed = $baseQuery()->whereIn('status_id', [7, 8])->count();
        $hardware = $baseQuery()->where('target_department', 'hardware')->count();
        $software = $baseQuery()->where('target_department', 'software')->count();
        $open = $baseQuery()->whereIn('status_id', [1, 2, 3])->count();
        $inProgress = $baseQuery()->whereIn('status_id', [4, 5, 6])->count();
        $rejected = $baseQuery()->where('status_id', 9)->count();
        $myTasks = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->count();

        $todayStart = now()->startOfDay();
        $todayCompleted = $baseQuery()
            ->whereIn('status_id', [7, 8])
            ->where('updated_at', '>=', $todayStart)
            ->count();

        return response()->json([
            'total' => $total,
            'completed' => $completed,
            'hardware' => $hardware,
            'software' => $software,
            'open' => $open,
            'inProgress' => $inProgress,
            'rejected' => $rejected,
            'myTasks' => $myTasks,
            'todayCompleted' => $todayCompleted,
        ]);
    }
}
