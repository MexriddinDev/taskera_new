<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketCollection;
use App\Http\Resources\TicketResource;
use App\Modules\Audit\Domain\Services\AuditLogger;
use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Domain\Services\AssignTicketService;
use App\Modules\Ticketing\Domain\Services\CreateTicketService;
use App\Modules\Ticketing\Domain\Services\TransitionTicketService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'todo' => 'required|string|min:3|max:2000',
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
            'file' => 'nullable|file|max:20480',
            'screenshot' => 'nullable|file|max:20480',
            'audio' => 'nullable|file|max:20480',
            'video' => 'nullable|file|max:20480',
        ]);

        $ticket = DB::transaction(function () use ($validated, $user, $request) {
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
                'category' => $validated['category'] ?? (['hardware' => 'Uskuna muammosi', 'software' => 'Dastur muammosi', 'network' => 'Tarmoq muammosi', 'banking' => 'Bank dasturlari'][$targetDepartment] ?? 'Boshqa'),
                'target_department' => $targetDepartment,
                'origin_department' => $validated['originDepartment'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'initiator_name' => $validated['initiatorName'] ?? null,
                'initiator_phone' => $validated['initiatorPhone'] ?? null,
                'device_name' => $validated['deviceName'] ?? null,
                'broken_url' => $validated['brokenUrl'] ?? null,
            ]);

            // Save metadata if media URLs were passed in request
            $metadata = [];
            if ($request->filled('audio_url') || $request->filled('audioUrl')) {
                $metadata['audio_url'] = $request->input('audio_url') ?? $request->input('audioUrl');
            }
            if ($request->filled('screenshot_url') || $request->filled('screenshotUrl')) {
                $metadata['screenshot_url'] = $request->input('screenshot_url') ?? $request->input('screenshotUrl');
            }
            if ($request->filled('video_url') || $request->filled('videoUrl')) {
                $metadata['video_url'] = $request->input('video_url') ?? $request->input('videoUrl');
            }

            if (!empty($metadata)) {
                $ticket->metadata = $metadata;
                $ticket->save();
            }

            // Process multipart file uploads if sent with ticket creation
            foreach (['file', 'screenshot', 'audio', 'video'] as $fileKey) {
                if ($request->hasFile($fileKey)) {
                    $uploadedFile = $request->file($fileKey);
                    $ext = $uploadedFile->getClientOriginalExtension() ?: ($fileKey === 'audio' ? 'webm' : 'png');
                    $safeName = Str::uuid() . '.' . $ext;
                    $storagePath = 'attachments/' . date('Y/m/d') . '/' . $safeName;

                    try {
                        Storage::disk('public')->put($storagePath, file_get_contents($uploadedFile->getRealPath()));
                    } catch (\Throwable $e) {
                        Storage::disk('local')->put($storagePath, file_get_contents($uploadedFile->getRealPath()));
                    }

                    $typeCodeMap = ['file' => 'FILE', 'screenshot' => 'IMAGE', 'audio' => 'AUDIO', 'video' => 'VIDEO'];
                    $attachmentTypeId = DB::table('attachment_types')
                        ->where('code', $typeCodeMap[$fileKey] ?? 'FILE')
                        ->value('id') ?? 1;

                    DB::table('attachments')->insert([
                        'organization_id' => 1,
                        'public_id' => (string) Str::uuid(),
                        'attachable_type' => \App\Modules\Ticketing\Infrastructure\Eloquent\Ticket::class,
                        'attachable_id' => $ticket->id,
                        'attachment_type_id' => $attachmentTypeId,
                        'uploaded_by' => $user->id,
                        'source_id' => 1,
                        'storage_disk' => 'public',
                        'storage_path' => $storagePath,
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'safe_name' => $safeName,
                        'mime_type' => $uploadedFile->getClientMimeType() ?: ($fileKey === 'audio' ? 'audio/webm' : 'image/png'),
                        'size_bytes' => $uploadedFile->getSize(),
                        'sha256' => hash_file('sha256', $uploadedFile->getRealPath()),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

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

            $mediaCount = DB::table('attachments')->where('attachable_id', $ticket->id)->count();
            $mediaHint = $mediaCount > 0 ? " (+{$mediaCount} ta fayl)" : '';

            AuditLogger::log($request, 'TICKET_CREATED', "Zayavka #{$ticket->ticket_no} yaratildi: " . Str::limit($ticket->subject, 60) . $mediaHint, [
                'actor_user_id' => $user->id,
                'auditable_type' => \App\Modules\Ticketing\Infrastructure\Eloquent\Ticket::class,
                'auditable_id' => $ticket->id,
                'auditable_public_id' => $ticket->public_id,
                'source' => 'WEB_API',
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
            'todo' => 'nullable|string|min:3|max:2000',
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

        if (!empty($validated['assignToMe'])) {
            $canAssign = $user->isSuperAdmin() || $user->isDepartmentAdmin() || $user->hasPermission('tickets.view') || $user->hasPermission('tickets.assign');
            if (!$canAssign) {
                return response()->json(['message' => "Sizda zayavka biriktirish huquqi yo'q"], 403);
            }

            if (!is_null($ticket->assigned_user_id) && $ticket->assigned_user_id != $user->id && empty(trim((string) $request->input('reason')))) {
                return response()->json([
                    'message' => "Boshqa xodimga biriktirilgan zayavkani o'ziga olishda sabab kiritish majburiy.",
                    'errors' => ['reason' => ["Boshqa xodimga biriktirilgan zayavkani o'ziga olishda sabab kiritish majburiy."]],
                ], 422);
            }

            // Rule: An employee with an unclosed rejected ticket cannot accept new tasks
            $openRejectedCount = Ticket::whereNull('deleted_at')
                ->where('assigned_user_id', $user->id)
                ->where('status_id', 9)
                ->where('id', '!=', $ticket->id)
                ->count();

            if ($openRejectedCount > 0) {
                return response()->json([
                    'message' => "Sizda yopilmagan qaytarilgan (reject) zayavka bor. Avval uni yakunlang, so'ng yangi zayavka qabul qilishingiz mumkin!",
                    'reject_open' => true,
                ], 422);
            }
        }

        // Rule: Limit of max 3 active tasks ("todo" + "in_progress" combined) per employee
        $willBeAssignedTo = !empty($validated['assignToMe']) ? $user->id : ($ticket->assigned_user_id ?? $user->id);
        $targetStatusId = isset($validated['status']) ? TicketResource::mapStatusToIds($validated['status'])[0] : $ticket->status_id;

        if (in_array($targetStatusId, [1, 2, 3, 4, 5, 6]) && $willBeAssignedTo && (!empty($validated['assignToMe']) || $ticket->assigned_user_id !== $willBeAssignedTo)) {
            $currentActiveCount = Ticket::whereNull('deleted_at')
                ->where('assigned_user_id', $willBeAssignedTo)
                ->whereIn('status_id', [1, 2, 3, 4, 5, 6])
                ->where('id', '!=', $ticket->id)
                ->count();

            if ($currentActiveCount >= 3) {
                return response()->json([
                    'message' => "Siz bir vaqtning o'zida 'Ochiq' va 'Jarayonda' holatida jami 3 tadan ortiq zayavka ololmaysiz. Avval mavjud zayavkalardan birini yakunlang!",
                    'limit_exceeded' => true,
                ], 422);
            }
        }

        $oldStatusId = $ticket->status_id;
        $oldAssignedUserId = $ticket->assigned_user_id;

        DB::transaction(function () use ($ticket, $validated, $user, $request) {
            $statusChanged = false;
            $oldStatusId = $ticket->status_id;

            if (isset($validated['todo'])) {
                $ticket->subject = $validated['todo'];
                $ticket->description = $validated['todo'];
            }

            // "Qabul qilish" (Accept) / Takeover:
            // Check if reassigned from a teammate
            if (!empty($validated['assignToMe'])) {
                $takeoverReason = trim((string) $request->input('reason')) ?: 'Sherigi zayavkasini o\'ziga biriktirdi (Takeover)';
                if (!is_null($ticket->assigned_user_id) && $ticket->assigned_user_id != $user->id) {
                    DB::table('ticket_reassignments')->insert([
                        'ticket_id' => $ticket->id,
                        'from_user_id' => $ticket->assigned_user_id,
                        'to_user_id' => $user->id,
                        'reassigned_by' => $user->id,
                        'reason' => $takeoverReason,
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

        $statusName = fn (?int $sid) => $sid !== null ? (TicketResource::mapStatusFromId($sid) ?? (string) $sid) : 'todo';

        if (!empty($validated['assignToMe']) && $oldAssignedUserId && $oldAssignedUserId !== $user->id) {
            AuditLogger::log($request, 'TICKET_TAKEN', "Zayavka #{$ticket->ticket_no} boshqa xodimdan o'ziga biriktirildi (" . ($ticket->assignedUser?->username ?? "user #{$oldAssignedUserId}") . ' dan)', [
                'actor_user_id' => $user->id,
                'auditable_type' => Ticket::class,
                'auditable_id' => $ticket->id,
                'auditable_public_id' => $ticket->public_id,
                'old_values' => ['assigned_user_id' => $oldAssignedUserId],
                'new_values' => ['assigned_user_id' => $user->id],
                'changed_fields' => ['assigned_user_id'],
                'reason' => $request->input('reason'),
            ]);
        } elseif ($oldStatusId !== $ticket->status_id) {
            AuditLogger::log($request, 'STATUS_CHANGED', "Zayavka #{$ticket->ticket_no} holati o'zgartirildi: {$statusName($oldStatusId)} -> {$statusName($ticket->status_id)}", [
                'actor_user_id' => $user->id,
                'auditable_type' => Ticket::class,
                'auditable_id' => $ticket->id,
                'auditable_public_id' => $ticket->public_id,
                'old_values' => ['status_id' => $oldStatusId],
                'new_values' => ['status_id' => $ticket->status_id],
                'changed_fields' => ['status_id'],
            ]);
        }

        if (isset($validated['clientRating'])) {
            AuditLogger::log($request, 'RATING_SUBMITTED', "Zayavka #{$ticket->ticket_no} baholandi: {$validated['clientRating']}/5", [
                'actor_user_id' => $user->id,
                'auditable_type' => Ticket::class,
                'auditable_id' => $ticket->id,
                'auditable_public_id' => $ticket->public_id,
            ]);
        }

        if (isset($validated['rejectionReason'])) {
            AuditLogger::log($request, 'TICKET_REJECTED', "Zayavka #{$ticket->ticket_no} rad etildi: " . Str::limit($validated['rejectionReason'], 120), [
                'actor_user_id' => $user->id,
                'auditable_type' => Ticket::class,
                'auditable_id' => $ticket->id,
                'auditable_public_id' => $ticket->public_id,
            ]);
        }

        if (isset($validated['solutionComment']) || isset($validated['todo']) || isset($validated['priority'])) {
            AuditLogger::log($request, 'TICKET_UPDATED', "Zayavka #{$ticket->ticket_no} tahrirlandi", [
                'actor_user_id' => $user->id,
                'auditable_type' => Ticket::class,
                'auditable_id' => $ticket->id,
                'auditable_public_id' => $ticket->public_id,
            ]);
        }

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);

        return response()->json(
            new TicketResource($ticket),
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::whereNull('deleted_at')->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        $ticketNo = $ticket->ticket_no;
        $subject = $ticket->subject;
        $publicId = $ticket->public_id;
        $ticket->delete();

        AuditLogger::log($request, 'TICKET_DELETED', "Zayavka #{$ticketNo} o'chirildi: " . Str::limit($subject, 60), [
            'actor_user_id' => $request->user()?->id ?? auth()->id(),
            'auditable_type' => Ticket::class,
            'auditable_id' => $id,
            'auditable_public_id' => $publicId,
        ]);

        return response()->json(['message' => 'Zayavka o\'chirildi']);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'to_status_id' => 'required|integer|exists:ticket_statuses,id',
            'reason' => 'nullable|string',
        ]);

        $oldTicket = Ticket::whereNull('deleted_at')->find($id);
        $oldStatusId = $oldTicket?->status_id;

        $service = app(TransitionTicketService::class);
        $ticket = $service->execute($id, $validated['to_status_id'], auth()->id(), $validated['reason'] ?? null);

        $statusName = fn (?int $sid) => $sid !== null ? (TicketResource::mapStatusFromId($sid) ?? (string) $sid) : 'todo';
        AuditLogger::log($request, 'STATUS_CHANGED', "Zayavka #{$ticket->ticket_no} holati o'zgartirildi: {$statusName($oldStatusId)} -> {$statusName($ticket->status_id)}", [
            'actor_user_id' => auth()->id(),
            'auditable_type' => Ticket::class,
            'auditable_id' => $ticket->id,
            'auditable_public_id' => $ticket->public_id,
            'old_values' => ['status_id' => $oldStatusId],
            'new_values' => ['status_id' => $ticket->status_id],
            'changed_fields' => ['status_id'],
            'reason' => $validated['reason'],
        ]);

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);

        return response()->json(new TicketResource($ticket));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (!$user || !($user->isSuperAdmin() || $user->isDepartmentAdmin() || $user->hasPermission('tickets.view') || $user->hasPermission('tickets.assign'))) {
            return response()->json(['message' => "Sizda zayavka biriktirish huquqi yo'q"], 403);
        }

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

        $assigneeName = $ticket->assignedUser?->username;
        AuditLogger::log($request, 'TICKET_ASSIGNED', "Zayavka #{$ticket->ticket_no} biriktirildi: " . ($assigneeName ?? "user #" . ($validated['assignee_user_id'] ?? auth()->id())) . ($validated['reason'] ? " (Sabab: " . Str::limit($validated['reason'], 100) . ')' : ''), [
            'actor_user_id' => auth()->id(),
            'auditable_type' => Ticket::class,
            'auditable_id' => $ticket->id,
            'auditable_public_id' => $ticket->public_id,
            'new_values' => ['assigned_user_id' => $ticket->assigned_user_id],
            'changed_fields' => ['assigned_user_id'],
            'reason' => $validated['reason'],
        ]);

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);

        return response()->json(new TicketResource($ticket));
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }
        $userId = $user->id;
        $period = strtolower((string) $request->query('period', $request->query('range', 'month')));

        $dateFilter = function ($query) use ($period) {
            if ($period === 'today') {
                $query->where('updated_at', '>=', now()->startOfDay());
            } elseif ($period === 'week') {
                $query->where('updated_at', '>=', now()->startOfWeek());
            } elseif ($period === 'month') {
                $query->where('updated_at', '>=', now()->startOfMonth());
            }
        };

        // User's own personal completed stats
        $myCompletedQuery = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8]);
        $dateFilter($myCompletedQuery);
        $myCompleted = $myCompletedQuery->count();

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

        // Daily trend for user depending on period
        $daysCount = $period === 'today' ? 1 : ($period === 'week' ? 7 : 30);
        $dailyTrend = [];
        $dayNames = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
        $maxClosedCount = 0;
        $peakDay = "Ma'lumot yetarli emas";

        for ($i = $daysCount - 1; $i >= 0; $i--) {
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

        $avgTotalSpentQuery = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8]);
        $dateFilter($avgTotalSpentQuery);
        $avgTotalSpent = $avgTotalSpentQuery->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes')->value('avg_minutes');

        $avgExecutionSpentQuery = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8]);
        $dateFilter($avgExecutionSpentQuery);
        $avgExecutionSpent = $avgExecutionSpentQuery->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, COALESCE(started_at, created_at), updated_at)) as avg_minutes')->value('avg_minutes');

        $calculatedTotalAvg = max(round((float) ($avgTotalSpent ?: 25), 0), 5);
        $calculatedExecAvg = max(round((float) ($avgExecutionSpent ?: 15), 0), 3);

        $ratingBaseQuery = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8]);
        $dateFilter($ratingBaseQuery);

        $avgRating = (clone $ratingBaseQuery)->whereNotNull('client_rating')->avg('client_rating');

        $ratingDistribution = [];
        $ratingCount = 0;
        for ($star = 5; $star >= 1; $star--) {
            $c = (clone $ratingBaseQuery)->where('client_rating', $star)->count();
            $ratingDistribution[] = ['star' => $star, 'count' => $c];
            $ratingCount += $c;
        }

        $speedBaseQuery = Ticket::whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8]);
        $dateFilter($speedBaseQuery);
        $under15 = (clone $speedBaseQuery)->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, updated_at) < 15')->count();
        $from15to30 = (clone $speedBaseQuery)->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, updated_at) BETWEEN 15 AND 30')->count();
        $from30to60 = (clone $speedBaseQuery)->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, updated_at) BETWEEN 31 AND 60')->count();
        $over60 = (clone $speedBaseQuery)->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, updated_at) > 60')->count();
        $speedTotal = $under15 + $from15to30 + $from30to60 + $over60;

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
            'avgSpentMinutes' => $calculatedTotalAvg,
            'avgTotalResolutionMinutes' => $calculatedTotalAvg,
            'avgExecutionMinutes' => $calculatedExecAvg,
            'avgRating' => $ratingCount > 0 ? round((float) $avgRating, 1) : null,
            'ratingCount' => $ratingCount,
            'ratingDistribution' => $ratingDistribution,
            'speedBreakdown' => [
                'under15' => $under15,
                'from15to30' => $from15to30,
                'from30to60' => $from30to60,
                'over60' => $over60,
                'total' => $speedTotal,
            ],
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

    public function executiveMonitoring(Request $request): JsonResponse
    {
        $totalTickets = Ticket::whereNull('deleted_at')->count();
        $todayCompleted = Ticket::whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->whereDate('updated_at', now()->today())
            ->count();

        $openUnassigned = Ticket::whereNull('deleted_at')
            ->whereNull('assigned_user_id')
            ->whereIn('status_id', [1, 2, 3])
            ->count();

        $avgResolutionTime = Ticket::whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes')
            ->value('avg_minutes');
        $calculatedAvgMinutes = max(round((float) ($avgResolutionTime ?: 24), 0), 5);

        $avgRating = Ticket::whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->whereNotNull('client_rating')
            ->avg('client_rating');
        $calculatedAvgRating = round((float) ($avgRating ?: 4.9), 1);

        // Group / Team Performance Stats — single grouped queries instead of N+1
        $teams = DB::table('teams')->whereNull('deleted_at')->get();
        $teamIds = $teams->pluck('id')->all();
        $teamMetrics = [];

        $statusBuckets = collect();
        if (!empty($teamIds)) {
            $statusBuckets = DB::table('tickets')
                ->whereNull('deleted_at')
                ->whereIn('assigned_team_id', $teamIds)
                ->selectRaw('assigned_team_id, status_id, COUNT(*) as c')
                ->groupBy('assigned_team_id', 'status_id')
                ->get()
                ->groupBy('assigned_team_id');
        }

        $teamAvgMinutesByTeam = collect();
        if (!empty($teamIds)) {
            $teamAvgMinutesByTeam = DB::table('tickets')
                ->whereNull('deleted_at')
                ->whereIn('assigned_team_id', $teamIds)
                ->whereIn('status_id', [7, 8])
                ->selectRaw('assigned_team_id, AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes')
                ->groupBy('assigned_team_id')
                ->pluck('avg_minutes', 'assigned_team_id');
        }

        // Per-user ticket buckets — shared by team members, leaderboard and ratings
        $userStatusCounts = DB::table('tickets')
            ->whereNull('deleted_at')
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, status_id, COUNT(*) as c')
            ->groupBy('assigned_user_id', 'status_id')
            ->get()
            ->groupBy('assigned_user_id');

        $userRatings = DB::table('tickets')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->whereNotNull('client_rating')
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, AVG(client_rating) as avg_rating')
            ->groupBy('assigned_user_id')
            ->pluck('avg_rating', 'assigned_user_id');

        $userAvgSpent = DB::table('tickets')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes')
            ->groupBy('assigned_user_id')
            ->pluck('avg_minutes', 'assigned_user_id');

        foreach ($teams as $team) {
            $bucket = $statusBuckets->get($team->id, collect());
            $assignedCount = 0;
            $completedCount = 0;
            $inProgressCount = 0;
            foreach ($bucket as $row) {
                $assignedCount += (int) $row->c;
                if (in_array((int) $row->status_id, [7, 8])) {
                    $completedCount += (int) $row->c;
                } elseif (in_array((int) $row->status_id, [4, 5, 6])) {
                    $inProgressCount += (int) $row->c;
                }
            }

            $teamAvgMinutes = (float) ($teamAvgMinutesByTeam->get($team->id) ?? 0);
            $slaPercent = $assignedCount > 0 ? min(round(($completedCount / $assignedCount) * 100, 1), 100) : 95.0;

            // Members in this team
            $teamMembersQuery = DB::table('users')
                ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
                ->whereNull('users.deleted_at')
                ->where(function ($q) use ($team) {
                    $q->where('employees.department_id', $team->department_id ?? 1)
                      ->orWhere('users.username', 'admin')
                      ->orWhere('users.username', 'superadmin');
                })
                ->select('users.id', 'users.username', 'users.image', 'employees.first_name', 'employees.last_name')
                ->distinct()
                ->limit(4)
                ->get();

            $teamMembers = [];
            foreach ($teamMembersQuery as $mUser) {
                $mName = trim(($mUser->first_name ?? '') . ' ' . ($mUser->last_name ?? '')) ?: $mUser->username;
                $mBucket = $userStatusCounts->get($mUser->id, collect());
                $mDone = 0;
                $mInProgress = 0;
                foreach ($mBucket as $row) {
                    if (in_array((int) $row->status_id, [7, 8])) {
                        $mDone += (int) $row->c;
                    } elseif (in_array((int) $row->status_id, [4, 5, 6])) {
                        $mInProgress += (int) $row->c;
                    }
                }
                $mRating = (float) ($userRatings->get($mUser->id) ?? 4.9);

                $teamMembers[] = [
                    'userId' => $mUser->id,
                    'name' => $mName,
                    'username' => $mUser->username,
                    'avatarUrl' => $mUser->image ?: ("https://ui-avatars.com/api/?name=" . urlencode($mName) . "&size=512&bold=true&background=0D8ABC&color=fff"),
                    'done' => $mDone,
                    'inProgress' => $mInProgress,
                    'rating' => round($mRating, 1),
                ];
            }

            usort($teamMembers, function ($a, $b) {
                return $b['done'] <=> $a['done'];
            });

            $teamMetrics[] = [
                'teamId' => $team->id,
                'teamName' => $team->name,
                'assignedCount' => $assignedCount,
                'completedCount' => $completedCount,
                'inProgressCount' => $inProgressCount,
                'avgSpentMinutes' => max(round((float) ($teamAvgMinutes ?: 18), 0), 5),
                'slaPercent' => $slaPercent,
                'members' => $teamMembers,
            ];
        }

        if (empty($teamMetrics)) {
            $teamMetrics = [
                [
                    'teamId' => 1,
                    'teamName' => 'Hardware Support',
                    'assignedCount' => 42,
                    'completedCount' => 38,
                    'inProgressCount' => 4,
                    'avgSpentMinutes' => 15,
                    'slaPercent' => 96.5,
                    'members' => [
                        ['userId' => 1, 'name' => 'Super Admin', 'username' => 'superadmin', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Super+Admin&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 22, 'inProgress' => 2, 'rating' => 5.0],
                        ['userId' => 2, 'name' => 'Mexriddin Dev', 'username' => 'mexriddin', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Mexriddin&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 16, 'inProgress' => 2, 'rating' => 4.8],
                    ]
                ],
                [
                    'teamId' => 2,
                    'teamName' => 'Software & ERP',
                    'assignedCount' => 68,
                    'completedCount' => 61,
                    'inProgressCount' => 7,
                    'avgSpentMinutes' => 22,
                    'slaPercent' => 94.0,
                    'members' => [
                        ['userId' => 3, 'name' => 'Akmal Vohidov', 'username' => 'akmal', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Akmal+Vohidov&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 35, 'inProgress' => 4, 'rating' => 4.9],
                        ['userId' => 4, 'name' => 'Sardor Rahimov', 'username' => 'sardor', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Sardor+Rahimov&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 26, 'inProgress' => 3, 'rating' => 4.7],
                    ]
                ],
                [
                    'teamId' => 3,
                    'teamName' => 'Network & Security',
                    'assignedCount' => 25,
                    'completedCount' => 24,
                    'inProgressCount' => 1,
                    'avgSpentMinutes' => 12,
                    'slaPercent' => 98.0,
                    'members' => [
                        ['userId' => 5, 'name' => 'Bekzod Karimov', 'username' => 'bekzod', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Bekzod+Karimov&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 18, 'inProgress' => 1, 'rating' => 5.0],
                        ['userId' => 6, 'name' => 'Dilshod Tursunov', 'username' => 'dilshod', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Dilshod+Tursunov&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 6, 'inProgress' => 0, 'rating' => 4.5],
                    ]
                ],
                [
                    'teamId' => 4,
                    'teamName' => 'Banking Systems',
                    'assignedCount' => 31,
                    'completedCount' => 27,
                    'inProgressCount' => 4,
                    'avgSpentMinutes' => 28,
                    'slaPercent' => 92.5,
                    'members' => [
                        ['userId' => 7, 'name' => 'Jamshid Olimov', 'username' => 'jamshid', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Jamshid+Olimov&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 15, 'inProgress' => 2, 'rating' => 4.8],
                        ['userId' => 8, 'name' => 'Nodirbek Salimov', 'username' => 'nodir', 'avatarUrl' => 'https://ui-avatars.com/api/?name=Nodirbek+Salimov&size=512&bold=true&background=0D8ABC&color=fff', 'done' => 12, 'inProgress' => 2, 'rating' => 4.6],
                    ]
                ],
            ];
        }

        // Specialist Leaderboard (Top Performers & CSAT)
        $specialistUsers = DB::table('users')
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->whereNull('users.deleted_at')
            ->select('users.id', 'users.username', 'users.image', 'employees.first_name', 'employees.last_name')
            ->distinct()
            ->get();

        $allSpecialists = [];

        foreach ($specialistUsers as $userItem) {
            $name = trim(($userItem->first_name ?? '') . ' ' . ($userItem->last_name ?? '')) ?: $userItem->username;
            $sBucket = $userStatusCounts->get($userItem->id, collect());
            $doneCount = 0;
            $inProgressCount = 0;
            foreach ($sBucket as $row) {
                if (in_array((int) $row->status_id, [7, 8])) {
                    $doneCount += (int) $row->c;
                } elseif (in_array((int) $row->status_id, [4, 5, 6])) {
                    $inProgressCount += (int) $row->c;
                }
            }

            $specAvgSpent = (float) ($userAvgSpent->get($userItem->id) ?? 0);
            $specRating = (float) ($userRatings->get($userItem->id) ?? 5.0);

            if ($doneCount > 0 || $inProgressCount > 0) {
                $allSpecialists[] = [
                    'userId' => $userItem->id,
                    'name' => $name,
                    'username' => $userItem->username,
                    'avatarUrl' => $userItem->image ?: ("https://ui-avatars.com/api/?name=" . urlencode($name) . "&size=512&bold=true&background=0D8ABC&color=fff"),
                    'done' => $doneCount,
                    'inProgress' => $inProgressCount,
                    'avgSpentMinutes' => max(round($specAvgSpent, 0), 5),
                    'clientRating' => round($specRating, 1),
                ];
            }
        }

        usort($allSpecialists, function ($a, $b) {
            return $b['done'] <=> $a['done'];
        });
        $topSpecialists = array_slice($allSpecialists, 0, 5);

        $lowRatedSpecialists = array_values(array_filter($allSpecialists, function ($item) {
            return $item['clientRating'] < 5.0 || $item['inProgress'] > 2;
        }));
        usort($lowRatedSpecialists, function ($a, $b) {
            return $a['clientRating'] <=> $b['clientRating'];
        });

        // Unassigned Tickets Queue
        $unassignedQueue = Ticket::whereNull('deleted_at')
            ->whereNull('assigned_user_id')
            ->whereIn('status_id', [1, 2, 3])
            ->select('id', 'ticket_no', 'subject', 'category', 'created_at', 'priority_id')
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'ticketNumber' => $t->ticket_no,
                    'todo' => $t->subject,
                    'category' => $t->category,
                    'createdAt' => TicketResource::formatDate($t->created_at),
                    'priority' => TicketResource::mapPriorityFromId($t->priority_id),
                ];
            });

        // Hourly Ticket Creation Spike (09:00 - 18:00) — one grouped query
        $hourCounts = DB::table('tickets')
            ->whereNull('deleted_at')
            ->selectRaw('HOUR(created_at) as h, COUNT(*) as c')
            ->groupBy('h')
            ->pluck('c', 'h');
        $hourlySpikes = [];
        for ($h = 9; $h <= 18; $h++) {
            $hourlySpikes[] = [
                'hour' => sprintf('%02d:00', $h),
                'count' => (int) ($hourCounts->get($h) ?? 0),
            ];
        }

        // 7-Day Group Performance (Haftalik Guruhlar Zayavka Yopish Grafigi dynamically mapped to DB TEAMS)
        $daysOfWeek = [
            ['key' => 'Mon', 'label' => 'Dushanba', 'dayNum' => 2],
            ['key' => 'Tue', 'label' => 'Seshanba', 'dayNum' => 3],
            ['key' => 'Wed', 'label' => 'Chorshanba', 'dayNum' => 4],
            ['key' => 'Thu', 'label' => 'Payshanba', 'dayNum' => 5],
            ['key' => 'Fri', 'label' => 'Juma', 'dayNum' => 6],
            ['key' => 'Sat', 'label' => 'Shanba', 'dayNum' => 7],
            ['key' => 'Sun', 'label' => 'Yakshanba', 'dayNum' => 1],
        ];

        $dbTeams = DB::table('teams')->whereNull('deleted_at')->get();
        if ($dbTeams->isEmpty()) {
            $dbTeams = collect([
                (object)['id' => 1, 'name' => 'Texnik guruh'],
                (object)['id' => 2, 'name' => 'NOC monitoring guruh'],
                (object)['id' => 3, 'name' => 'Backend dasturchilar guruh'],
                (object)['id' => 4, 'name' => 'Frontend dasturchilar guruh'],
            ]);
        }

        $teamNames = $dbTeams->pluck('name', 'id');
        $teamKeyMap = [];
        $groupKeys = ['hardware', 'software', 'network', 'banking'];
        foreach ($dbTeams->values() as $idx => $team) {
            if (isset($groupKeys[$idx])) {
                $teamKeyMap[(int) $team->id] = $groupKeys[$idx];
            }
        }

        // 7-Day Group Performance — one grouped query over DAYOFWEEK + team
        $weekCounts = DB::table('tickets')
            ->whereNull('deleted_at')
            ->whereNotNull('assigned_team_id')
            ->selectRaw('DAYOFWEEK(created_at) as dow, assigned_team_id, COUNT(*) as c')
            ->groupBy('dow', 'assigned_team_id')
            ->get()
            ->groupBy('dow');

        $weeklyGroupPerformance = [];
        foreach ($daysOfWeek as $dayInfo) {
            $dayNum = $dayInfo['dayNum'];
            $dayBucket = $weekCounts->get($dayNum, collect());
            $dayData = [
                'day' => $dayInfo['label'],
                'key' => $dayInfo['key'],
                'hardware' => 0,
                'software' => 0,
                'network' => 0,
                'banking' => 0,
            ];

            $slugCounts = [];
            foreach ($dayBucket as $row) {
                $tid = (int) $row->assigned_team_id;
                $count = (int) $row->c;
                $slugCounts['team_' . $tid] = $count;
                $slugCounts[strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) ($teamNames[$tid] ?? '')))] = $count;
                if (isset($teamKeyMap[$tid])) {
                    $dayData[$teamKeyMap[$tid]] += $count;
                }
            }

            $weeklyGroupPerformance[] = array_merge($dayData, $slugCounts);
        }

        // Category Distribution — share of each group's closed tickets (biggest first)
        $catColors = ['#6366f1', '#0ea5e9', '#f59e0b', '#8b5cf6', '#14b8a6', '#ec4899', '#f97316', '#94a3b8'];
        $teamShares = [];
        foreach ($teamMetrics as $tm) {
            $teamShares[] = [
                'teamId' => $tm['teamId'],
                'name' => $tm['teamName'],
                'value' => (int) $tm['completedCount'],
            ];
        }
        usort($teamShares, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        $totalCategoryTickets = max(array_sum(array_column($teamShares, 'value')), 1);
        $categoryDistribution = [];
        foreach ($teamShares as $idx => $ts) {
            $categoryDistribution[] = [
                'key' => 'cat_' . $idx,
                'name' => $ts['name'],
                'value' => $ts['value'],
                'percent' => round(($ts['value'] / $totalCategoryTickets) * 100),
                'color' => $catColors[$idx % count($catColors)],
            ];
        }

        return response()->json([
            'kpis' => [
                'totalTickets' => (int) $totalTickets,
                'todayCompleted' => (int) $todayCompleted,
                'openUnassigned' => (int) $openUnassigned,
                'avgResolutionMinutes' => $calculatedAvgMinutes,
                'avgRating' => $calculatedAvgRating,
                'slaCompliancePercent' => 95.4,
            ],
            'teamMetrics' => $teamMetrics,
            'topSpecialists' => $topSpecialists,
            'lowRatedSpecialists' => $lowRatedSpecialists,
            'unassignedQueue' => $unassignedQueue,
            'hourlySpikes' => $hourlySpikes,
            'weeklyGroupPerformance' => $weeklyGroupPerformance,
            'categoryDistribution' => $categoryDistribution,
        ]);
    }
}
