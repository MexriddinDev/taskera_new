<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\User;
use App\Modules\Audit\Domain\Services\AuditLogger;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Events\TicketStatusChanged;
use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Domain\Services\AssignTicketService;
use App\Modules\Ticketing\Domain\Services\TransitionTicketService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Support\DeviceInfo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    /** Bajarilgan (7, 8) va rad etilgan (9, 10) holatlar — bularda zayavka yopiq. */
    private const CLOSED_STATUS_IDS = [7, 8, 9, 10];

    /** Monitoring keshining eski nusxalarini barcha foydalanuvchilar uchun bekor qiladi. */
    private static function invalidateMonitoringCache(): void
    {
        Cache::add('monitoring.version', 1, now()->addYears(10));
        Cache::increment('monitoring.version');
        Cache::forget('executive.monitoring.v1');
    }

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

        // `comments.authorUser` SHART: TicketResource `relationLoaded('comments')` bo'yicha
        // tarmoqlanadi va relation yuklanmagan bo'lsa HAR BIR zayavka uchun alohida
        // comments+users JOIN so'rovini bajaradi (N+1). Eager-load uni 2 ta so'rovga tushiradi.
        $query = Ticket::with(['assignedUser', 'requesterEmployee', 'requesterUser', 'department', 'attachments', 'comments.authorUser'])
            ->whereNull('deleted_at');

        // Scope filtering
        if (! $user) {
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

            if (! $isStaff || $scope === 'my_submitted') {
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

        if (! empty($search)) {
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
        $dateField = $request->input('dateField', 'created_at');
        if (! in_array($dateField, ['created_at', 'resolved_at', 'closed_at'], true)) {
            $dateField = 'created_at';
        }

        if (! empty($startDate) || ! empty($endDate)) {
            $query->where(function ($q) use ($dateField, $startDate, $endDate) {
                // Ochiq zayavkalar (masalan, resolved_at null) har doim ko'rinadi,
                // yopilganlar esa sana oralig'iga mos bo'lishi kerak.
                $q->whereNull($dateField)
                    ->orWhere(function ($q2) use ($dateField, $startDate, $endDate) {
                        if (! empty($startDate)) {
                            $q2->whereDate($dateField, '>=', $startDate);
                        }
                        if (! empty($endDate)) {
                            $q2->whereDate($dateField, '<=', $endDate);
                        }
                    });
            });
        }

        $total = $query->count();

        $tickets = $query->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        // ── O'qilmagan xabarlar soni (ro'yxatda badge ko'rsatish uchun) ──
        if ($user && $tickets->isNotEmpty()) {
            $unreadMap = DB::table('comments')
                ->where('commentable_type', Ticket::class)
                ->whereIn('commentable_id', $tickets->pluck('id'))
                ->whereNull('read_at')
                ->where('author_user_id', '!=', $user->id)
                ->groupBy('commentable_id')
                ->selectRaw('commentable_id, COUNT(*) as cnt')
                ->pluck('cnt', 'commentable_id')
                ->map(fn ($v) => (int) $v);

            foreach ($tickets as $t) {
                $t->unread_comment_count = $unreadMap[$t->id] ?? 0;
            }
        }

        return response()->json([
            'tasks' => TicketResource::collection($tickets),
            'total' => $total,
            'skip' => $skip,
            'limit' => $limit,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with([
            'assignedUser.employee',
            'requesterEmployee',
            'requesterUser.employee',
            'department',
            'attachments',
            'comments.authorUser.employee',
        ])
            ->whereNull('deleted_at')
            ->find($id);

        if (! $ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        // Zayafka ochildi — ishtirokchi (murojaatchi yoki biriktirilgan xodim)
        // uchun boshqalar yozgan xabarlar o'qilgan deb belgilanadi.
        $user = request()->user() ?? auth()->user();
        if ($user && in_array($user->id, [$ticket->requester_user_id, $ticket->assigned_user_id], true)) {
            $unreadIds = DB::table('comments')
                ->where('commentable_type', Ticket::class)
                ->where('commentable_id', $ticket->id)
                ->where('author_user_id', '!=', $user->id)
                ->whereNull('read_at')
                ->pluck('id')
                ->flip();

            // Frontend chatda yangi (yashil) xabarlarni ko'rsatish uchun
            // o'qish belgilashdan OLDIN o'qilmagan idlar saqlanadi.
            $ticket->unread_comment_ids = $unreadIds;

            DB::table('comments')
                ->where('commentable_type', Ticket::class)
                ->where('commentable_id', $ticket->id)
                ->where('author_user_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        // "Mas'ul xodim" bloki uchun: kimdan kimga, qachon va kim o'tkazgani.
        $ticket->assignment_history = $this->assignmentHistoryFor($ticket->id);

        return response()->json(
            new TicketResource($ticket),
        );
    }

    /**
     * Zayavkaning mas'ul xodim o'zgarishlari tarixi — vaqt bo'yicha o'sish tartibida.
     *
     * Ikki manba birlashtiriladi: AssignTicketService yozadigan
     * ticket_assignment_history (qalamcha orqali biriktirish) va
     * "o'ziga olish" (takeover) paytida yoziladigan ticket_reassignments.
     * Faqat bittasini o'qish tarixni chala ko'rsatardi.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Yechim yoki rad etish matnini zayavka yozishmasiga qo'shadi.
     *
     * `metadata.kind` orqali oddiy izohdan ajratiladi — frontend uni yashil
     * (yechim) yoki qizil (rad etish) ramkada ko'rsatadi.
     */
    private function appendThreadEntry(Ticket $ticket, ?int $authorUserId, string $kind, string $body): void
    {
        $text = trim($body);
        if ($text === '') {
            return;
        }

        // Ayni matn ketma-ket ikki marta yozilmasin (so'rov takrorlansa) —
        // oxirgi yozuv bilan solishtiramiz.
        $lastBody = \App\Modules\Ticketing\Infrastructure\Eloquent\Comment::where('commentable_type', Ticket::class)
            ->where('commentable_id', $ticket->id)
            ->orderByDesc('id')
            ->value('body');

        if ($lastBody === $text) {
            return;
        }

        $comment = new \App\Modules\Ticketing\Infrastructure\Eloquent\Comment();
        $comment->organization_id = $ticket->organization_id;
        $comment->commentable_type = Ticket::class;
        $comment->commentable_id = $ticket->id;
        $comment->author_user_id = $authorUserId;
        $comment->type_id = 1;   // PUBLIC
        $comment->source_id = 1; // WEB
        $comment->body = $text;
        $comment->metadata = json_encode(['kind' => $kind], JSON_UNESCAPED_UNICODE);
        $comment->save();
    }

    private function assignmentHistoryFor(int $ticketId): array
    {
        $avatar = static fn (?string $username, ?string $image): ?string => $image
            ?: ($username ? 'https://ui-avatars.com/api/?name='.urlencode($username).'&size=512&bold=true&background=0D8ABC&color=fff' : null);

        $rows = [];

        $select = static fn (string $table) => DB::table($table)
            ->leftJoin('users as from_u', $table.'.from_user_id', '=', 'from_u.id')
            ->leftJoin('users as to_u', $table.'.to_user_id', '=', 'to_u.id')
            ->leftJoin('users as by_u', $table.'.'.($table === 'ticket_reassignments' ? 'reassigned_by' : 'changed_by'), '=', 'by_u.id')
            ->where($table.'.ticket_id', $ticketId)
            ->select([
                $table.'.id',
                $table.'.reason',
                $table.'.spent_minutes',
                $table.'.created_at',
                'from_u.username as from_username',
                'from_u.image as from_image',
                'to_u.username as to_username',
                'to_u.image as to_image',
                'by_u.username as by_username',
            ])
            ->get();

        foreach (['ticket_assignment_history', 'ticket_reassignments'] as $table) {
            foreach ($select($table) as $row) {
                // Jamoa almashinuvi (foydalanuvchi o'zgarmagan) tarixda ko'rsatilmaydi.
                if (is_null($row->to_username) && is_null($row->from_username)) {
                    continue;
                }

                $rows[] = [
                    'id' => $table.'-'.$row->id,
                    'fromUser' => $row->from_username,
                    'fromUserAvatar' => $avatar($row->from_username, $row->from_image),
                    'toUser' => $row->to_username,
                    'toUserAvatar' => $avatar($row->to_username, $row->to_image),
                    'changedBy' => $row->by_username,
                    'reason' => $row->reason,
                    // Oldingi ijrochi shu almashinuvgacha qancha ishlagani.
                    'spentMinutes' => $row->spent_minutes === null ? null : (int) $row->spent_minutes,
                    'createdAt' => TicketResource::formatDate(Carbon::parse($row->created_at)),
                    'createdAtIso' => Carbon::parse($row->created_at)->toIso8601String(),
                    'sortKey' => Carbon::parse($row->created_at)->getTimestamp(),
                ];
            }
        }

        usort($rows, static fn (array $a, array $b) => $a['sortKey'] <=> $b['sortKey']);

        return array_map(static function (array $row) {
            unset($row['sortKey']);

            return $row;
        }, $rows);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (! $user) {
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
            'file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z|max:20480',
            'screenshot' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,bmp|max:20480',
            'audio' => 'nullable|file|mimes:mp3,ogg,wav,webm|max:20480',
            'video' => 'nullable|file|mimes:mp4,webm,mov|max:20480',
        ]);

        // ── AD dan jonli ma'lumot (guruh → departament) — TRANZAKSIYADAN TASHQARIDA ──
        // Tashqi LDAP chaqiruvi DB tranzaksiya ichida bo'lsa, AD sekinlashsa
        // connection lock'lar uzoq ushlab turiladi. Shuning uchun OLDIN bajariladi.
        // AD ishlamay qolsa — DB dagi so'nggi sinxronlangan ma'lumot ishlatiladi.
        try {
            $adData = app(\App\Services\AdAuthService::class)->lookupByUsername($user->username);
            if ($adData) {
                app(\App\Services\AdUserProvisionService::class)->findOrProvision($adData);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AD lookup zayafka yaratishda muvaffaqiyatsiz: '.$e->getMessage());
        }

        $ticket = DB::transaction(function () use ($validated, $user, $request) {
            $ticketNo = $this->ticketRepository->nextNumber(1);

            // ── Foydalanuvchi (AD/HR) ma'lumotlarini avtomatik to'ldirish ──
            // Login paytida employee kartochkasi AD dan sinxronlanadi;
            // shu ma'lumotlar zayafka yaratishda avtomatik qo'shiladi.
            $employee = DB::table('employees')
                ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
                ->leftJoin('positions', 'positions.id', '=', 'employees.position_id')
                ->where('employees.id', $user->employee_id)
                ->select(
                    'employees.department_id',
                    'employees.first_name',
                    'employees.last_name',
                    'employees.email',
                    'employees.phone',
                    'departments.name as department_name',
                    'positions.name as position_name'
                )
                ->first();

            // AD ma'lumotlari ustuvor — forma sohasidagi qiymatlar faqat fallback
            $deptId = $employee?->department_id ?? 1;
            $employeeFullName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
            $originDepartment = $employee?->department_name ?? ($validated['originDepartment'] ?? null);
            $initiatorName = $employeeFullName !== '' ? $employeeFullName : ($validated['initiatorName'] ?? null);
            $initiatorPhone = $employee?->phone ?? ($validated['initiatorPhone'] ?? null);
            $requesterEmail = $employee?->email ?? $user->email;
            $requesterPosition = $employee?->position_name ?? null;
            $requesterUsername = $user->username;

            $statusId = $validated['status'] ?? null
                ? TicketResource::mapStatusToIds($validated['status'])[0]
                : 1;

            $priorityId = $validated['priority'] ?? null
                ? TicketResource::mapPriorityToId($validated['priority'])
                : 3;

            $targetDepartment = $validated['targetDepartment'] ?? 'hardware';
            $teamId = $validated['teamId'] ?? $validated['assigned_team_id'] ?? null;

            $categoryName = $validated['category'] ?? (['hardware' => 'Uskuna muammosi', 'software' => 'Dastur muammosi', 'network' => 'Tarmoq muammosi', 'banking' => 'Bank dasturlari'][$targetDepartment] ?? 'Boshqa');

            $ticket = Ticket::create([
                'public_id' => (string) Str::uuid(),
                'organization_id' => \App\Support\CurrentOrg::id($request ?? null),
                'ticket_no' => $ticketNo,
                'ticket_type' => 'INCIDENT',
                'subject' => $validated['todo'],
                'description' => $validated['todo'],
                'status_id' => $statusId,
                'priority_id' => $priorityId,
                'source_id' => 1,
                'requester_user_id' => $user->id,
                'department_id' => $deptId,
                'assigned_team_id' => $teamId,
                'category' => $categoryName,
                // SLA muddatlari kategoriyadan olinadi — nomi mos kelgan
                // kategoriya bo'lsa, zayavka darrov unga bog'lanadi.
                'category_id' => DB::table('categories')->whereNull('deleted_at')
                    ->where('name', $categoryName)->value('id'),
                'target_department' => $targetDepartment,
                'origin_department' => $originDepartment,
                'floor' => $validated['floor'] ?? null,
                'initiator_name' => $initiatorName,
                'initiator_phone' => $initiatorPhone,
                'requester_email' => $requesterEmail,
                'requester_position' => $requesterPosition,
                'requester_username' => $requesterUsername,
                'device_name' => $validated['deviceName'] ?? null,
                'broken_url' => $validated['brokenUrl'] ?? null,
            ]);

            // Save metadata if media URLs were passed in request
            $metadata = [];

            // Zayavka qaysi qurilmadan yuborilgani — YARATISH paytida aniqlanadi.
            // Keyinchalik TicketResource shu saqlangan qiymatni o'qiydi; ilgari u
            // so'rov paytidagi User-Agent'ni tekshirib, ko'ruvchining qurilmasini
            // ko'rsatib qo'yardi.
            $metadata['device'] = DeviceInfo::fromUserAgent($request->userAgent());
            if ($request->filled('audio_url') || $request->filled('audioUrl')) {
                $metadata['audio_url'] = $request->input('audio_url') ?? $request->input('audioUrl');
            }
            if ($request->filled('screenshot_url') || $request->filled('screenshotUrl')) {
                $metadata['screenshot_url'] = $request->input('screenshot_url') ?? $request->input('screenshotUrl');
            }
            if ($request->filled('video_url') || $request->filled('videoUrl')) {
                $metadata['video_url'] = $request->input('video_url') ?? $request->input('videoUrl');
            }

            $ticket->metadata = $metadata;
            $ticket->save();

            // Process multipart file uploads if sent with ticket creation
            foreach (['file', 'screenshot', 'audio', 'video'] as $fileKey) {
                if ($request->hasFile($fileKey)) {
                    $uploadedFile = $request->file($fileKey);
                    $ext = $uploadedFile->getClientOriginalExtension() ?: ($fileKey === 'audio' ? 'webm' : 'png');
                    $safeName = Str::uuid().'.'.$ext;
                    $storagePath = 'attachments/'.date('Y/m/d').'/'.$safeName;

                    $disk = 'public';
                    $dir = 'attachments/'.date('Y/m/d');
                    try {
                        Storage::disk($disk)->putFileAs($dir, $uploadedFile, $safeName);
                    } catch (\Throwable $e) {
                        $disk = 'local';
                        Storage::disk($disk)->putFileAs($dir, $uploadedFile, $safeName);
                    }

                    $typeCodeMap = ['file' => 'FILE', 'screenshot' => 'IMAGE', 'audio' => 'AUDIO', 'video' => 'VIDEO'];
                    $attachmentTypeId = DB::table('attachment_types')
                        ->where('code', $typeCodeMap[$fileKey] ?? 'FILE')
                        ->value('id') ?? 1;

                    DB::table('attachments')->insert([
                        'organization_id' => \App\Support\CurrentOrg::id($request ?? null),
                        'public_id' => (string) Str::uuid(),
                        'attachable_type' => Ticket::class,
                        'attachable_id' => $ticket->id,
                        'attachment_type_id' => $attachmentTypeId,
                        'uploaded_by' => $user->id,
                        'source_id' => 1,
                        // Yuqorida 'public' ga yozib bo'lmasa 'local' ga tushiladi —
                        // qaysi diskka yozilgan bo'lsa, o'sha saqlanishi kerak.
                        'storage_disk' => $disk,
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

            event(new TicketCreated($ticket));

            $mediaCount = DB::table('attachments')->where('attachable_id', $ticket->id)->count();
            $mediaHint = $mediaCount > 0 ? " (+{$mediaCount} ta fayl)" : '';

            AuditLogger::log($request, 'TICKET_CREATED', "Zayavka #{$ticket->ticket_no} yaratildi: ".Str::limit($ticket->subject, 60).$mediaHint, [
                'actor_user_id' => $user->id,
                'auditable_type' => Ticket::class,
                'auditable_id' => $ticket->id,
                'auditable_public_id' => $ticket->public_id,
                'source' => 'WEB_API',
            ]);

            return $ticket;
        });

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);
        self::invalidateMonitoringCache();

        return response()->json(
            new TicketResource($ticket),
            201,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::whereNull('deleted_at')->find($id);

        if (! $ticket) {
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
        if (! $user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        if (! empty($validated['assignToMe'])) {
            // Egasiz zayavkani navbatdan olish — ishlash huquqi yetarli.
            // Sherigiga biriktirilgan zayavkani tortib olish esa dispetcherlik
            // amali: unga `tickets.assign` kerak.
            $takingFromColleague = ! is_null($ticket->assigned_user_id) && $ticket->assigned_user_id != $user->id;
            $canAssign = $takingFromColleague ? $user->canAssignTickets() : $user->canTakeTickets();

            if (! $canAssign) {
                return response()->json([
                    'message' => $takingFromColleague
                        ? "Boshqa xodimga biriktirilgan zayavkani o'ziga olish uchun biriktirish huquqi kerak"
                        : "Sizda zayavkani o'ziga olish huquqi yo'q",
                ], 403);
            }

            // Yopilgan zayavkani o'ziga olib bo'lmaydi — assign() dagi qoida bilan bir xil.
            if (in_array((int) $ticket->status_id, self::CLOSED_STATUS_IDS, true)) {
                return response()->json([
                    'message' => "Zayavka yopilgan — mas'ul xodimni o'zgartirib bo'lmaydi.",
                ], 422);
            }

            if (! is_null($ticket->assigned_user_id) && $ticket->assigned_user_id != $user->id && empty(trim((string) $request->input('reason')))) {
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

        // Holat o'zgarishi — `tickets.transition` huquqi.
        //
        // Zayavka MUALLIFI istisno: u o'z zayavkasini baholaydi (clientRating)
        // yoki qaytaradi (status = rejected) — bu huquq talab qilmaydi.
        // Ilgari bu yerda umuman tekshiruv yo'q edi: navbatni ko'rish huquqi
        // bo'lgan (yoki hatto begona) xodim ham holatni o'zgartira olardi.
        $isRequester = (int) $ticket->requester_user_id === (int) $user->id;
        $wantsStatusChange = isset($validated['status'])
            || ! empty($validated['completed'])
            || ! empty($validated['solutionComment']);

        if ($wantsStatusChange && ! $isRequester && ! $user->canTransitionTickets()) {
            return response()->json(['message' => "Sizda zayavka holatini o'zgartirish huquqi yo'q"], 403);
        }

        // Rule: Limit of max 3 active tasks ("todo" + "in_progress" combined) per employee
        $willBeAssignedTo = ! empty($validated['assignToMe']) ? $user->id : ($ticket->assigned_user_id ?? $user->id);
        $targetStatusId = isset($validated['status']) ? TicketResource::mapStatusToIds($validated['status'])[0] : $ticket->status_id;

        if (in_array($targetStatusId, [1, 2, 3, 4, 5, 6]) && $willBeAssignedTo && (! empty($validated['assignToMe']) || $ticket->assigned_user_id !== $willBeAssignedTo)) {
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
            if (! empty($validated['assignToMe'])) {
                $takeoverReason = trim((string) $request->input('reason')) ?: 'Sherigi zayavkasini o\'ziga biriktirdi (Takeover)';
                $takenFromSomeoneElse = ! is_null($ticket->assigned_user_id) && $ticket->assigned_user_id != $user->id;

                if ($takenFromSomeoneElse) {
                    // Oldingi ijrochi qancha ishlagani tarixga yoziladi: taymer
                    // yangi xodim uchun noldan boshlanadi, aks holda bu vaqt
                    // butunlay yo'qolib ketardi.
                    DB::table('ticket_reassignments')->insert([
                        'ticket_id' => $ticket->id,
                        'from_user_id' => $ticket->assigned_user_id,
                        'to_user_id' => $user->id,
                        'reassigned_by' => $user->id,
                        'reason' => $takeoverReason,
                        'spent_minutes' => $ticket->started_at
                            ? max(1, (int) abs(now()->diffInMinutes($ticket->started_at)))
                            : null,
                        'created_at' => now(),
                    ]);
                }

                $ticket->assigned_user_id = $user->id;

                // Timer "qabul qilingan paytdan" boshlanadi. Zayavka boshqa
                // xodimdan olingan bo'lsa — noldan qayta boshlanadi.
                if ($takenFromSomeoneElse || is_null($ticket->started_at)) {
                    $ticket->started_at = now();
                }
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
                    // rejection_reason ATAYLAB tozalanmaydi: rad etish sababi
                    // yozishmaning bir qismi. Zayavka qayta yopilganda ham
                    // eski suhbat to'liq ko'rinib turishi kerak.
                    $ticket->resolved_at = now();
                    if ($ticket->started_at) {
                        $mins = (int) abs(now()->diffInMinutes($ticket->started_at));
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

            // `solution_comment` va `rejection_reason` — bitta ustun, ya'ni har
            // safar USTIGA yoziladi: zayavka ikkinchi marta yopilganda birinchi
            // yechim ham, rad etish sababi ham yo'qolib ketardi. Shu sabab ular
            // yozishmaga (comments) ham qo'shiladi — u qo'shiluvchi jadval va
            // butun tarix saqlanib qoladi.
            //
            // Hodisa ataylab otilmaydi: bu yerda maqsad tarixni saqlash;
            // bildirishnomalar o'z yo'li bilan yuboriladi, aks holda bitta amal
            // uchun ikki marta xabar ketardi.
            if (! empty($validated['rejectionReason'])) {
                $this->appendThreadEntry($ticket, $user->id, 'rejection', $validated['rejectionReason']);
            }

            if (! empty($validated['solutionComment'])) {
                $this->appendThreadEntry($ticket, $user->id, 'solution', $validated['solutionComment']);
            }

            if (isset($validated['clientRating'])) {
                $ticket->client_rating = $validated['clientRating'];
                if (! in_array($ticket->status_id, [7, 8])) {
                    $ticket->status_id = 7;
                    $statusChanged = true;
                }
                $ticket->resolved_at = now();
                if ($ticket->started_at && $ticket->spent_minutes == 0) {
                    $mins = (int) abs(now()->diffInMinutes($ticket->started_at));
                    $ticket->spent_minutes = max(1, $mins);
                }
            }

            if (isset($validated['completed']) && $validated['completed']) {
                if (! in_array($ticket->status_id, [7, 8])) {
                    $ticket->status_id = 7;
                    $statusChanged = true;
                }
                // rejection_reason bu yerda ham saqlanadi — yozishma tarixi.
                $ticket->resolved_at = now();
                if ($ticket->started_at && $ticket->spent_minutes == 0) {
                    $mins = (int) abs(now()->diffInMinutes($ticket->started_at));
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

            // Bildirishnoma hodisasi. Ilgari bu yerda otilmasdi: status faqat
            // shu yerda yozilar, TicketStatusChanged esa bot va
            // TransitionTicketService da otilardi. Natijada saytdan yopilgan
            // zayavka haqida so'rovchiga Telegramga hech narsa bormasdi.
            //
            // save() dan KEYIN otiladi — tinglovchi yangi holatni ko'rishi kerak.
            if ($statusChanged) {
                event(new TicketStatusChanged($ticket, $oldStatusId, (int) $ticket->status_id, (int) $user->id));
            }
        });

        $statusName = fn (?int $sid) => $sid !== null ? (TicketResource::mapStatusFromId($sid) ?? (string) $sid) : 'todo';

        if (! empty($validated['assignToMe']) && $oldAssignedUserId && $oldAssignedUserId !== $user->id) {
            AuditLogger::log($request, 'TICKET_TAKEN', "Zayavka #{$ticket->ticket_no} boshqa xodimdan o'ziga biriktirildi (".($ticket->assignedUser?->username ?? "user #{$oldAssignedUserId}").' dan)', [
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
            AuditLogger::log($request, 'TICKET_REJECTED', "Zayavka #{$ticket->ticket_no} rad etildi: ".Str::limit($validated['rejectionReason'], 120), [
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
        self::invalidateMonitoringCache();

        return response()->json(
            new TicketResource($ticket),
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (! $user || ! ($user->isSuperAdmin() || $user->hasPermission('tickets.delete'))) {
            return response()->json(['message' => "Sizda zayavka o'chirish huquqi yo'q"], 403);
        }

        $ticket = Ticket::whereNull('deleted_at')->find($id);

        if (! $ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        $ticketNo = $ticket->ticket_no;
        $subject = $ticket->subject;
        $publicId = $ticket->public_id;
        $ticket->delete();

        AuditLogger::log($request, 'TICKET_DELETED', "Zayavka #{$ticketNo} o'chirildi: ".Str::limit($subject, 60), [
            'actor_user_id' => $request->user()?->id ?? auth()->id(),
            'auditable_type' => Ticket::class,
            'auditable_id' => $id,
            'auditable_public_id' => $publicId,
        ]);

        self::invalidateMonitoringCache();

        return response()->json(['message' => 'Zayavka o\'chirildi']);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (! $user || ! $user->canTransitionTickets()) {
            return response()->json(['message' => "Sizda zayavka holatini o'zgartirish huquqi yo'q"], 403);
        }

        // Zayavka egasi (requester) ham o'z zayavkasini boshqarishi mumkin
        $target = Ticket::whereNull('deleted_at')->find($id);
        if ($target && (int) $target->requester_user_id === (int) $user->id) {
            return response()->json(['message' => "Zayavka egasi holatni o'zgartira olmaydi. Baholash yoki rad etish orqali amalga oshiring."], 403);
        }

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
        self::invalidateMonitoringCache();

        return response()->json(new TicketResource($ticket));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        // Yopilgan (bajarilgan yoki rad etilgan) zayavkada mas'ul xodimni
        // o'zgartirishga ruxsat yo'q — tarix o'zgarmas bo'lib qolishi kerak.
        $ticketStatusId = (int) (Ticket::whereNull('deleted_at')->where('id', $id)->value('status_id') ?? 0);
        if (in_array($ticketStatusId, self::CLOSED_STATUS_IDS, true)) {
            return response()->json([
                'message' => "Zayavka yopilgan — mas'ul xodimni o'zgartirib bo'lmaydi.",
            ], 422);
        }

        $validated = $request->validate([
            'team_id' => 'nullable|integer',
            'assignee_user_id' => 'nullable|integer',
            'reason' => 'nullable|string',
        ]);

        // Huquq amalning MAZMUNIGA qarab tekshiriladi:
        //   egasiz zayavkani o'ziga olish  -> ishlash huquqi yetarli;
        //   boshqa xodimga biriktirish yoki sherigining ishini o'ziga olish
        //   -> `tickets.assign` (dispetcherlik) kerak.
        $currentAssigneeId = (int) (Ticket::whereNull('deleted_at')->where('id', $id)->value('assigned_user_id') ?? 0);
        $targetUserId = (int) ($validated['assignee_user_id'] ?? auth()->id());
        $isSelfPickup = $targetUserId === (int) auth()->id() && $currentAssigneeId === 0;

        if (! ($isSelfPickup ? $user->canTakeTickets() : $user->canAssignTickets())) {
            return response()->json([
                'message' => $isSelfPickup
                    ? "Sizda zayavkani o'ziga olish huquqi yo'q"
                    : "Sizda zayavkani boshqa xodimga biriktirish huquqi yo'q",
            ], 403);
        }

        $service = app(AssignTicketService::class);
        $ticket = $service->execute(
            $id,
            $validated['team_id'] ?? null,
            $validated['assignee_user_id'] ?? auth()->id(),
            auth()->id() ?? 1,
            $validated['reason'] ?? null,
        );

        // `reason` ixtiyoriy: yuborilmasa `$validated` da kaliti umuman
        // bo'lmaydi va unga to'g'ridan-to'g'ri murojaat 500 xato berardi.
        $reason = $validated['reason'] ?? null;

        $assigneeName = $ticket->assignedUser?->username;
        AuditLogger::log($request, 'TICKET_ASSIGNED', "Zayavka #{$ticket->ticket_no} biriktirildi: ".($assigneeName ?? 'user #'.($validated['assignee_user_id'] ?? auth()->id())).($reason ? ' (Sabab: '.Str::limit($reason, 100).')' : ''), [
            'actor_user_id' => auth()->id(),
            'auditable_type' => Ticket::class,
            'auditable_id' => $ticket->id,
            'auditable_public_id' => $ticket->public_id,
            'new_values' => ['assigned_user_id' => $ticket->assigned_user_id],
            'changed_fields' => ['assigned_user_id'],
            'reason' => $reason,
        ]);

        $ticket->load(['assignedUser', 'requesterEmployee', 'department']);
        self::invalidateMonitoringCache();

        return response()->json(new TicketResource($ticket));
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }
        $userId = $user->id;
        $period = strtolower((string) $request->query('period', $request->query('range', 'month')));

        // Ixtiyoriy sana oralig'i. Bittasi berilsa ham davr tugmalari
        // (bugun/hafta/oy) o'rniga aynan shu oraliq ishlatiladi.
        $request->validate(['startDate' => 'nullable|date', 'endDate' => 'nullable|date|after_or_equal:startDate']);
        $rangeStart = $request->filled('startDate') ? Carbon::parse($request->query('startDate'))->startOfDay() : null;
        $rangeEnd = $request->filled('endDate') ? Carbon::parse($request->query('endDate'))->endOfDay() : null;
        $hasRange = $rangeStart || $rangeEnd;

        $dateFilter = function ($query) use ($period, $rangeStart, $rangeEnd, $hasRange) {
            if ($hasRange) {
                if ($rangeStart) {
                    $query->where('updated_at', '>=', $rangeStart);
                }
                if ($rangeEnd) {
                    $query->where('updated_at', '<=', $rangeEnd);
                }

                return;
            }
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

        // Kunlik dinamika: oraliq berilsa o'sha oraliq, aks holda davr bo'yicha.
        // Grafik o'qilarli qolishi uchun uzunligi 62 kun bilan cheklanadi.
        $trendEnd = ($rangeEnd ?: now())->copy()->endOfDay();
        if ($hasRange) {
            $trendStart = ($rangeStart ?: $trendEnd->copy()->subDays(29))->copy()->startOfDay();
            $daysCount = min(62, max(1, (int) abs($trendStart->diffInDays($trendEnd)) + 1));
        } else {
            $daysCount = $period === 'today' ? 1 : ($period === 'week' ? 7 : 30);
        }
        $dailyTrend = [];
        $dayNames = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
        $maxClosedCount = 0;
        $peakDay = "Ma'lumot yetarli emas";

        // PERFORMANCE: 30 ta alohida COUNT o'rniga bitta GROUP BY DATE() query
        $trendRows = Ticket::query()
            ->selectRaw("DATE(updated_at) as day, COUNT(*) as cnt")
            ->whereNull('deleted_at')
            ->where('assigned_user_id', $userId)
            ->whereIn('status_id', [7, 8])
            ->where('updated_at', '>=', $trendEnd->copy()->subDays($daysCount - 1)->startOfDay())
            ->where('updated_at', '<=', $trendEnd)
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->pluck('cnt', 'day');

        for ($i = $daysCount - 1; $i >= 0; $i--) {
            $date = $trendEnd->copy()->subDays($i);

            $count = (int) ($trendRows[$date->format('Y-m-d')] ?? 0);

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

    /**
     * Zayavka ustida ishlay oladigan xodimlar so'rovi.
     *
     * Mezon User::isSupportStaff() bilan bir xil: `tickets.view` yoki
     * `tickets.assign` huquqi. Ilgari shart "birorta roli bor" edi — rolsiz
     * foydalanuvchi tushunchasi olib tashlangandan keyin u butun tashkilotni
     * qamrab olardi.
     *
     * Super admin hammani ko'radi, qolganlar — o'z bo'limidagilarni.
     */
    private function staffQuery(?object $user, bool $isSuper): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('users')
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->whereNull('users.deleted_at')
            ->where(function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('model_has_roles as mhr')
                        ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'mhr.role_id')
                        ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                        ->whereColumn('mhr.model_id', 'users.id')
                        ->where('mhr.model_type', User::class)
                        ->whereIn('p.name', ['tickets.view', 'tickets.assign', 'tickets.transition'])
                        ->selectRaw('1');
                })
                    ->orWhere('users.username', 'admin')
                    ->orWhere('users.username', 'superadmin');
            });

        if (! $isSuper && $user) {
            $employee = DB::table('employees')->where('id', $user->employee_id)->first();
            $deptId = $employee ? $employee->department_id : 1;

            $query->where(function ($q) use ($deptId, $user) {
                $q->where('employees.department_id', $deptId)
                    ->orWhere('users.id', $user->id);
            });
        }

        return $query;
    }

    /**
     * Zayavkani biriktirish oynasi uchun xodimlar ro'yxati.
     *
     * Ilgari frontend buni /tickets/monitoring dan olishga urinardi, lekin u
     * javobda `employees` kalitini umuman qaytarmaydi (faqat employeeStats,
     * employeeAvatars, reassignments) — shuning uchun ro'yxat doim bo'sh edi
     * va hech kimni tanlab bo'lmasdi.
     */
    public function assignableStaff(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        $isSuper = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        // Biriktirish huquqi bo'lgan xodim BUTUN tashkilot bo'yicha tanlaydi.
        //
        // Ilgari ro'yxat doim so'rovchining bo'limi bilan cheklanardi va
        // support xodimi (odatda IT bo'limida bitta o'zi) oynani ochganda
        // ro'yxat bo'sh chiqardi — "biriktirish ishlamayapti" shundan edi.
        $seesAll = $isSuper || ($user && $user->canAssignTickets());

        $staff = $this->staffQuery($user, $seesAll)
            ->select('users.id', 'users.username', 'users.image', 'employees.first_name', 'employees.last_name')
            ->distinct()
            ->orderBy('employees.first_name')
            ->orderBy('users.username')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->username,
                'image' => $u->image,
            ])
            ->values();

        return response()->json(['data' => $staff]);
    }

    public function monitoring(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        $isSuper = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        // PERFORMANCE: global monitoring dashboard 60s keshlanadi
        // (ticket o'zgarishlari maksimal 1 daqiqa kechikib ko'rinadi)
        $cacheVersion = (int) Cache::get('monitoring.version', 1);
        $cacheKey = ($isSuper ? 'monitoring.super' : 'monitoring.dept.'.(\App\Support\CurrentOrg::id($request)).'.u'.$user?->id).'.v'.$cacheVersion;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        $employees = $this->staffQuery($user, $isSuper)
            ->select('users.id', 'users.username', 'users.image', 'employees.first_name', 'employees.last_name', 'employees.phone')
            ->distinct()
            ->get();

        // PERFORMANCE: bitta shartli agregatsiya (5N+1 emas, 1 query) —
        // har xodim uchun alohida COUNT o'rniga CASE WHEN bilan guruhlanadi.
        $statsRows = Ticket::query()
            ->selectRaw('assigned_user_id')
            ->selectRaw("SUM(CASE WHEN status_id IN (1,2,3) THEN 1 ELSE 0 END) as todo")
            ->selectRaw("SUM(CASE WHEN status_id IN (4,5,6) THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status_id = 9 THEN 1 ELSE 0 END) as rejected")
            ->selectRaw("SUM(CASE WHEN status_id IN (7,8) THEN 1 ELSE 0 END) as done")
            ->selectRaw("AVG(CASE WHEN status_id IN (7,8) AND spent_minutes > 0 THEN spent_minutes END) as avg_spent")
            ->whereNull('deleted_at')
            ->whereIn('assigned_user_id', $employees->pluck('id'))
            ->groupBy('assigned_user_id')
            ->get()
            ->keyBy('assigned_user_id');

        $employeeStats = [];
        $employeeAvatars = [];

        foreach ($employees as $emp) {
            $name = trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')) ?: $emp->username;

            $row = $statsRows->get($emp->id);
            $todo = (int) ($row->todo ?? 0);
            $inProgress = (int) ($row->in_progress ?? 0);
            $rejected = (int) ($row->rejected ?? 0);
            $done = (int) ($row->done ?? 0);

            $activeCount = $todo + $inProgress + $rejected;

            $employeeAvatars[] = [
                'userId' => $emp->id,
                'name' => $name,
                'username' => $emp->username,
                'activeCount' => $activeCount,
                'avatarUrl' => $emp->image ?: ('https://ui-avatars.com/api/?name='.urlencode($name).'&size=512&bold=true&background=0D8ABC&color=fff'),
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
                    'avgSpentMinutes' => round((float) ($row->avg_spent ?? 0), 1),
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
            ->get()
            // Payload keshlanadi (database cache = PHP serialize). Collection obyektini
            // keshlash mumkin emas: qaytarishda __PHP_Incomplete_Class bo'lib, JSON'ga
            // massiv emas, obyekt bo'lib chiqadi va frontend .map() da yiqiladi.
            ->values()
            ->all();

        $payload = [
            'employeeStats' => $employeeStats,
            'employeeAvatars' => $employeeAvatars,
            'reassignments' => $reassignments,
        ];

        Cache::put($cacheKey, $payload, now()->addSeconds(60));

        return response()->json($payload);
    }

    /**
     * Monitoring sahifasi uchun sana oralig'i.
     *
     * Davrlar "Foydalanuvchilar va bo'limlar statistikasi" sahifasi bilan
     * BIR XIL (UserDepartmentStatsController::resolveDateRange) — ikki ekranda
     * bir xil filtr bir xil oraliqni bildirishi kerak.
     *
     * @return array{0: ?\Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon}
     */
    private function monitoringDateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'quarter' => [$now->copy()->subDays(90)->startOfDay(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'all' => [null, null],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    /**
     * Monitoring uchun kunlik trend — UsersPage dagi "Zayavkalar dinamikasi"
     * bilan bir xil shakl (date / short_date / day_name / count), shuning uchun
     * grafik ham bir xil ko'rinadi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function monitoringTrend(string $period, ?\Illuminate\Support\Carbon $rangeStart): array
    {
        $daysCount = match ($period) {
            'today' => 1,
            'week' => 7,
            'quarter' => 90,
            'year' => 365,
            'all' => 60,
            default => 30,
        };

        $calcStart = $rangeStart ?: now()->subDays($daysCount - 1)->startOfDay();

        $rows = DB::table('tickets')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $calcStart)
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as c')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('c', 'date_key');

        $dayNames = ['Yak', 'Dush', 'Sesh', 'Chor', 'Pay', 'Juma', 'Shan'];
        $result = [];

        // Ko'pi bilan 30 ustun — undan ortig'i grafikda o'qilmaydi.
        $iterations = min($daysCount, 30);
        for ($i = $iterations - 1; $i >= 0; $i--) {
            $dt = now()->subDays($i);
            $key = $dt->format('Y-m-d');

            $result[] = [
                'date' => $key,
                'short_date' => $dt->format('d.m'),
                'day_name' => $dayNames[$dt->dayOfWeek],
                'count' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $result;
    }

    public function executiveMonitoring(Request $request): JsonResponse
    {
        // Vaqt bo'yicha filtr — statistika sahifasidagi kabi davrlar.
        $period = (string) $request->query('period', 'month');
        [$rangeStart, $rangeEnd] = $this->monitoringDateRange($period);

        // Har bir agregat so'rovga shu oraliq qo'yiladi. 'all' da oraliq NULL —
        // ya'ni filtr umuman qo'shilmaydi.
        $range = function ($query) use ($rangeStart, $rangeEnd) {
            if ($rangeStart) {
                $query->where('created_at', '>=', $rangeStart);
            }
            if ($rangeEnd) {
                $query->where('created_at', '<=', $rangeEnd);
            }
        };

        // PERFORMANCE: 120s kesh — rahbariyat dashboard'i har ochilishda DB'ni urmaydi.
        // Kesh kaliti davrga bog'liq, aks holda filtr almashtirilganda eski
        // davr ma'lumoti qaytardi.
        $cacheKey = 'executive.monitoring.v2.'.$period;
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        $totalTickets = Ticket::whereNull('deleted_at')->tap($range)->count();
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
            ->tap($range)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes')
            ->value('avg_minutes');
        // Ma'lumot yo'q bo'lsa soxta qiymat o'ylab topilmaydi — null qaytariladi
        $calculatedAvgMinutes = $avgResolutionTime !== null ? max(round((float) $avgResolutionTime, 0), 5) : null;

        $avgRating = Ticket::whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->whereNotNull('client_rating')
            ->tap($range)
            ->avg('client_rating');
        $calculatedAvgRating = $avgRating !== null ? round((float) $avgRating, 1) : null;

        // Group / Team Performance Stats — single grouped queries instead of N+1
        $teams = DB::table('teams')->whereNull('deleted_at')->get();
        $teamIds = $teams->pluck('id')->all();
        $teamMetrics = [];

        $statusBuckets = collect();
        if (! empty($teamIds)) {
            $statusBuckets = DB::table('tickets')
                ->whereNull('deleted_at')
                ->whereIn('assigned_team_id', $teamIds)
                ->tap($range)
                ->selectRaw('assigned_team_id, status_id, COUNT(*) as c')
                ->groupBy('assigned_team_id', 'status_id')
                ->get()
                ->groupBy('assigned_team_id');
        }

        $teamAvgMinutesByTeam = collect();
        if (! empty($teamIds)) {
            $teamAvgMinutesByTeam = DB::table('tickets')
                ->whereNull('deleted_at')
                ->whereIn('assigned_team_id', $teamIds)
                ->whereIn('status_id', [7, 8])
                ->tap($range)
                ->selectRaw('assigned_team_id, AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes')
                ->groupBy('assigned_team_id')
                ->pluck('avg_minutes', 'assigned_team_id');
        }

        // Per-user ticket buckets — shared by team members, leaderboard and ratings
        $userStatusCounts = DB::table('tickets')
            ->whereNull('deleted_at')
            ->whereNotNull('assigned_user_id')
            ->tap($range)
            ->selectRaw('assigned_user_id, status_id, COUNT(*) as c')
            ->groupBy('assigned_user_id', 'status_id')
            ->get()
            ->groupBy('assigned_user_id');

        $userRatings = DB::table('tickets')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->whereNotNull('client_rating')
            ->whereNotNull('assigned_user_id')
            ->tap($range)
            ->selectRaw('assigned_user_id, AVG(client_rating) as avg_rating')
            ->groupBy('assigned_user_id')
            ->pluck('avg_rating', 'assigned_user_id');

        $userAvgSpent = DB::table('tickets')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->whereNotNull('assigned_user_id')
            ->tap($range)
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
            $slaPercent = $assignedCount > 0 ? min(round(($completedCount / $assignedCount) * 100, 1), 100) : null;

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
                $mName = trim(($mUser->first_name ?? '').' '.($mUser->last_name ?? '')) ?: $mUser->username;
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
                    'avatarUrl' => $mUser->image ?: ('https://ui-avatars.com/api/?name='.urlencode($mName).'&size=512&bold=true&background=0D8ABC&color=fff'),
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

        // Ma'lumot yo'q bo'lsa SOXTA demo ma'lumot qaytarilmaydi —
        // rahbariyat real raqamlarni ko'rishi kerak.
        if (empty($teamMetrics)) {
            $teamMetrics = [];
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
            $name = trim(($userItem->first_name ?? '').' '.($userItem->last_name ?? '')) ?: $userItem->username;
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
                    'avatarUrl' => $userItem->image ?: ('https://ui-avatars.com/api/?name='.urlencode($name).'&size=512&bold=true&background=0D8ABC&color=fff'),
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
            })
            // Keshlanadi — Collection emas, oddiy massiv bo'lishi shart (yuqoridagi izohga qarang).
            ->values()
            ->all();

        // Hourly Ticket Creation Spike (09:00 - 18:00) — one grouped query
        $hourCounts = DB::table('tickets')
            ->whereNull('deleted_at')
            ->tap($range)
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
                (object) ['id' => 1, 'name' => 'Texnik guruh'],
                (object) ['id' => 2, 'name' => 'NOC monitoring guruh'],
                (object) ['id' => 3, 'name' => 'Backend dasturchilar guruh'],
                (object) ['id' => 4, 'name' => 'Frontend dasturchilar guruh'],
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
            ->tap($range)
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
                $slugCounts['team_'.$tid] = $count;
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
                'key' => 'cat_'.$idx,
                'name' => $ts['name'],
                'value' => $ts['value'],
                'percent' => round(($ts['value'] / $totalCategoryTickets) * 100),
                'color' => $catColors[$idx % count($catColors)],
            ];
        }

        $payload = [
            'kpis' => [
                'totalTickets' => (int) $totalTickets,
                'todayCompleted' => (int) $todayCompleted,
                'openUnassigned' => (int) $openUnassigned,
                'avgResolutionMinutes' => $calculatedAvgMinutes,
                'avgRating' => $calculatedAvgRating,
                // SLA compliance: yopilganlar ichidan belgilangan muddatga moslari
                // (real hisob — resolved_at <= created_at + 24h shartli)
                'slaCompliancePercent' => $this->calculateSlaCompliance($range),
            ],
            'period' => $period,
            'trend' => $this->monitoringTrend($period, $rangeStart),
            'teamMetrics' => $teamMetrics,
            'topSpecialists' => $topSpecialists,
            'lowRatedSpecialists' => $lowRatedSpecialists,
            'unassignedQueue' => $unassignedQueue,
            'hourlySpikes' => $hourlySpikes,
            'weeklyGroupPerformance' => $weeklyGroupPerformance,
            'categoryDistribution' => $categoryDistribution,
        ];

        // PERFORMANCE: executive dashboard 120s keshlanadi
        \Illuminate\Support\Facades\Cache::put($cacheKey, $payload, now()->addSeconds(120));

        return response()->json($payload);
    }

    /**
     * Real SLA compliance: yopilgan (7,8) zayavkalar ichidan 24 soat ichida
     * yopilganlar ulushi. Ma'lumot bo'lmasa null.
     */
    private function calculateSlaCompliance(?callable $range = null): ?float
    {
        // $range — monitoring sahifasidagi davr filtri. Berilmasa butun tarix.
        $apply = $range ?: static function ($query) {};

        $total = Ticket::whereNull('deleted_at')->whereIn('status_id', [7, 8])->tap($apply)->count();
        if ($total === 0) {
            return null;
        }

        $withinSla = Ticket::whereNull('deleted_at')
            ->whereIn('status_id', [7, 8])
            ->tap($apply)
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, resolved_at) <= 24')
            ->count();

        return round(($withinSla / $total) * 100, 1);
    }
}
