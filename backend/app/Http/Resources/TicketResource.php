<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Support\DeviceInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

final class TicketResource extends JsonResource
{
    private static array $statusMap = [
        'todo' => [1, 2, 3],
        'in_progress' => [4, 5, 6],
        'done' => [7, 8],
        'rejected' => [9],
    ];

    private static array $priorityMap = [
        'low' => 4,
        'medium' => 3,
        'high' => 2,
        'critical' => 1,
    ];

    private static array $reversePriorityMap = [
        1 => 'high',
        2 => 'high',
        3 => 'medium',
        4 => 'low',
    ];

    private static array $reverseStatusMap = [
        1 => 'todo',
        2 => 'todo',
        3 => 'todo',
        4 => 'in_progress',
        5 => 'in_progress',
        6 => 'in_progress',
        7 => 'done',
        8 => 'done',
        9 => 'rejected',
        10 => 'rejected',
    ];

    public static function mapStatusToIds(string $status): array
    {
        return self::$statusMap[$status] ?? [1, 2, 3];
    }

    public static function mapPriorityToId(string $priority): int
    {
        return self::$priorityMap[$priority] ?? 3;
    }

    public static function mapStatusFromId(int $statusId): string
    {
        return self::$reverseStatusMap[$statusId] ?? 'todo';
    }

    public static function mapPriorityFromId(int $priorityId): string
    {
        return self::$reversePriorityMap[$priorityId] ?? 'medium';
    }

    public static function mapCompletedFromStatus(int $statusId): bool
    {
        return in_array($statusId, [7, 8]);
    }

    public static function formatDate($value): ?string
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->copy()->setTimezone('Asia/Tashkent')->format('d-M Y, H:i');
        }
        try {
            return Carbon::parse($value)->timezone('Asia/Tashkent')->format('d-M Y, H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * Biriktirmaga vaqtinchalik imzolangan NISBIY havola.
     *
     * Nima uchun imzolangan: attachments.download marshruti `signed:relative`
     * middleware bilan himoyalangan (IDOR). Ilgari bu yerda oddiy url() ishlatilar
     * edi — imzosiz havola har doim 403 qaytarardi, ya'ni zayavkani ochgan odam
     * rasm va ovozni umuman ko'ra olmasdi.
     *
     * Nima uchun nisbiy: url() APP_URL dan http://localhost/... yasaydi. Frontend
     * esa boshqa host'dan (masalan https://172.28.201.27:5173) ochiladi va /api ni
     * proxy orqali uzatadi — absolyut havola LAN'dagi qurilmada ochilmaydi.
     */
    /**
     * Amal muddati 10 daqiqalik "chelak"ka yaxlitlanadi.
     *
     * Sabab: zayavka sahifasi har 5 soniyada refetch qiladi. Muddat har safar
     * now()+30min bo'lsa, imzo ham har javobda o'zgaradi va <audio src> yangi
     * qiymat oladi — brauzer faylni qaytadan yuklab, ijroni uzib qo'yadi
     * (ovoz bir necha soniyada "tugab qolgandek" bo'ladi).
     *
     * Yaxlitlangan muddat bilan bir xil biriktirma uchun havola 10 daqiqa
     * davomida o'zgarmaydi, ya'ni src barqaror qoladi.
     */
    private const URL_BUCKET_SECONDS = 600;

    private static function attachmentUrl(int $attachmentId): string
    {
        // Chelak chegarasiga yaxlitlab, ustiga to'liq oyna qo'shamiz —
        // shunda havola har doim kamida 30 daqiqa amal qiladi.
        $bucket = (int) ceil(time() / self::URL_BUCKET_SECONDS) * self::URL_BUCKET_SECONDS;

        // DIQQAT: temporarySignedRoute int qiymatni "hozirdan shuncha soniya"
        // deb tushunadi — timestamp berish uchun sana obyekti kerak.
        return URL::temporarySignedRoute(
            'attachments.download',
            Carbon::createFromTimestamp($bucket + 1800),
            ['id' => $attachmentId],
            absolute: false
        );
    }

    public function toArray(Request $request): array
    {
        $assignedUser = $this->relationLoaded('assignedUser') ? $this->assignedUser : null;
        $requester = $this->relationLoaded('requesterEmployee') ? $this->requesterEmployee : null;
        $requesterUser = $this->relationLoaded('requesterUser') ? $this->requesterUser : null;
        $department = $this->relationLoaded('department') ? $this->department : null;

        // Zayavka QAYSI qurilmadan yuborilgani — yaratish paytida saqlangan
        // qiymatdan. Ilgari bu yerda $request->header('User-Agent') tekshirilardi,
        // ya'ni zayavkani ko'rayotgan odamning brauzeri ko'rsatilib, ma'lumot
        // noto'g'ri bo'lardi.
        $storedDevice = is_array($this->metadata) ? ($this->metadata['device'] ?? null) : null;
        $device = DeviceInfo::normalize($storedDevice);

        // Telegram zayavkalari uchun qurilma turi hech qachon aniqlanmaydi:
        // botning HTTP so'rovida User-Agent yo'q, shuning uchun 'unknown' bo'lib
        // qoladi. Bunday holatda kanalning o'zi yetarli ma'lumot.
        $isTelegram = $this->telegram_chat_id || (int) $this->source_id === 2;
        if ($isTelegram && $device['kind'] === DeviceInfo::KIND_UNKNOWN) {
            $device = DeviceInfo::telegram();
        }

        $detectedIp = $request->ip() ?: '127.0.0.1';
        if ($detectedIp === '127.0.0.1' || $detectedIp === '::1') {
            $detectedIp = '172.27.108.142';
        }

        $realInitiator = $this->initiator_name ?? ($requester ? trim($requester->first_name.' '.$requester->last_name) : ($requesterUser?->name ?? $requesterUser?->username ?? 'superadmin'));

        $attachments = $this->relationLoaded('attachments')
            ? $this->attachments
            : ($this->id ? DB::table('attachments')->where('attachable_id', $this->id)->get() : collect());

        $audioFile = $attachments->first(function ($att) {
            $mime = strtolower((string) ($att->mime_type ?? ''));
            $name = strtolower((string) ($att->original_name ?? ''));

            return str_contains($mime, 'audio') || str_contains($name, '.ogg') || str_contains($name, '.mp3') || str_contains($name, '.wav') || str_contains($name, '.m4a') || str_contains($name, '.opus');
        });

        $imageFile = $attachments->first(function ($att) {
            $mime = strtolower((string) ($att->mime_type ?? ''));
            $name = strtolower((string) ($att->original_name ?? ''));

            return str_contains($mime, 'image') || str_contains($name, '.png') || str_contains($name, '.jpg') || str_contains($name, '.jpeg') || str_contains($name, '.webp');
        });

        $videoFile = $attachments->first(function ($att) {
            $mime = strtolower((string) ($att->mime_type ?? ''));
            $name = strtolower((string) ($att->original_name ?? ''));
            if (str_contains($mime, 'audio')) {
                return false;
            }

            return str_contains($mime, 'video') || str_contains($name, '.mp4') || str_contains($name, '.webm') || str_contains($name, '.avi') || str_contains($name, '.mov');
        });

        $extractedAudioUrl = $audioFile ? self::attachmentUrl((int) $audioFile->id) : (is_array($this->metadata) ? ($this->metadata['audio_url'] ?? $this->metadata['voice_path'] ?? null) : null);
        $extractedScreenshotUrl = $imageFile ? self::attachmentUrl((int) $imageFile->id) : (is_array($this->metadata) ? ($this->metadata['screenshot_url'] ?? $this->metadata['file_url'] ?? null) : null);
        $extractedVideoUrl = $videoFile ? self::attachmentUrl((int) $videoFile->id) : (is_array($this->metadata) ? ($this->metadata['video_url'] ?? null) : null);

        if (! $extractedScreenshotUrl && $this->broken_url && (str_contains($this->broken_url, '.png') || str_contains($this->broken_url, '.jpg') || str_contains($this->broken_url, '.jpeg'))) {
            $extractedScreenshotUrl = $this->broken_url;
        }

        $media = $attachments->map(function ($att) {
            $mime = strtolower((string) ($att->mime_type ?? ''));
            $name = strtolower((string) ($att->original_name ?? ''));
            $type = 'file';
            if (str_contains($mime, 'audio') || str_contains($name, '.ogg') || str_contains($name, '.mp3') || str_contains($name, '.wav') || str_contains($name, '.m4a') || str_contains($name, '.opus')) {
                $type = 'audio';
            } elseif (str_contains($mime, 'video') || str_contains($name, '.mp4') || str_contains($name, '.webm') || str_contains($name, '.avi') || str_contains($name, '.mov')) {
                $type = 'video';
            } elseif (str_contains($mime, 'image') || str_contains($name, '.png') || str_contains($name, '.jpg') || str_contains($name, '.jpeg') || str_contains($name, '.webp')) {
                $type = 'image';
            }

            return [
                'id' => (int) $att->id,
                'type' => $type,
                'name' => $att->original_name,
                'url' => self::attachmentUrl((int) $att->id),
                'sizeBytes' => (int) ($att->size_bytes ?? 0),
            ];
        })->values()->all();

        $cleanSubject = trim((string) preg_replace('/\[\s*Ovozli xabar biriktirilgan\s*\]/iu', '', (string) $this->subject));
        $cleanDescription = trim((string) preg_replace('/\[\s*Ovozli xabar biriktirilgan\s*\]/iu', '', (string) $this->description));

        $comments = $this->relationLoaded('comments')
            ? $this->comments->map(function ($c) {
                $authorName = $c->relationLoaded('authorUser') && $c->authorUser
                    ? ($c->authorUser->username ?? 'Foydalanuvchi')
                    : 'Foydalanuvchi';

                return [
                    'id' => $c->id,
                    'author' => $authorName,
                    'body' => $c->body,
                    'createdAt' => $c->created_at ? \Illuminate\Support\Carbon::parse($c->created_at)->timezone('Asia/Tashkent')->format('d-M Y, H:i') : '',
                    'isRead' => ! isset($this->unread_comment_ids[$c->id]),
                ];
            })
            : ($this->id ? DB::table('comments')
                ->leftJoin('users', 'comments.author_user_id', '=', 'users.id')
                ->where('comments.commentable_id', $this->id)
                ->where('comments.commentable_type', \App\Modules\Ticketing\Infrastructure\Eloquent\Ticket::class)
                ->orderBy('comments.created_at', 'asc')
                ->select('comments.*', 'users.username as author_username')
                ->get()
                ->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'author' => $c->author_username ?: 'Foydalanuvchi',
                        'body' => $c->body,
                        'createdAt' => $c->created_at ? \Illuminate\Support\Carbon::parse($c->created_at)->timezone('Asia/Tashkent')->format('d-M Y, H:i') : '',
                        'isRead' => ! isset($this->unread_comment_ids[$c->id]),
                    ];
                }) : collect());

        $pinfl = $requester?->attributes['pinfl'] ?? ($requester?->pinfl ?? (is_array($this->metadata) ? ($this->metadata['pinfl'] ?? null) : null));
        $mfo = $requester?->attributes['mfo'] ?? ($requester?->mfo ?? (is_array($this->metadata) ? ($this->metadata['mfo'] ?? null) : null));
        $localCode = is_array($this->metadata) ? ($this->metadata['local_code'] ?? null) : null;

        return [
            'id' => $this->id,
            'ticketNumber' => $this->ticket_no,
            'todo' => $cleanSubject,
            'description' => $cleanDescription,
            'completed' => self::mapCompletedFromStatus($this->status_id),
            'userId' => $this->requester_user_id,
            'status' => self::mapStatusFromId($this->status_id),
            'priority' => self::mapPriorityFromId($this->priority_id),
            'targetDepartment' => $this->target_department ?? 'hardware',
            'originDepartment' => $this->origin_department ?? ($department->name ?? 'Noma\'lum'),
            'category' => $this->category ?? 'Noma\'lum',
            'floor' => $this->floor,
            'initiatorName' => $realInitiator,
            'initiatorPhone' => $this->initiator_phone ?? $requester?->phone,
            'requesterEmail' => $this->requester_email ?? $requester?->email ?? $requesterUser?->email,
            'requesterPosition' => $this->requester_position,
            'requesterUsername' => $this->requester_username ?? $requesterUser?->username,
            'requesterDepartment' => $this->origin_department ?? $department->name ?? null,
            'deviceName' => $this->device_name,
            'brokenUrl' => $this->broken_url,
            'screenshotUrl' => $extractedScreenshotUrl,
            'rejectionReason' => $this->rejection_reason,
            'solutionComment' => $this->solution_comment,
            'clientRating' => $this->client_rating ?? (is_array($this->metadata) ? ($this->metadata['rating'] ?? null) : null),
            'isAssigned' => ! is_null($this->assigned_user_id),
            'assignedUserId' => $this->assigned_user_id,
            'assignedTeamId' => $this->assigned_team_id,
            'assignedTo' => $assignedUser?->username,
            'assignedUserAvatar' => $assignedUser?->image ?? ($assignedUser ? ('https://ui-avatars.com/api/?name='.urlencode($assignedUser->username).'&size=512&bold=true&background=0D8ABC&color=fff') : null),
            'startedAt' => self::formatDate($this->started_at),
            'resolvedAt' => self::formatDate($this->resolved_at),
            'startedAtIso' => $this->started_at?->toIso8601String(),
            'resolvedAtIso' => $this->resolved_at?->toIso8601String(),
            'spentMinutes' => $this->spent_minutes ?? 0,
            'createdAt' => self::formatDate($this->created_at),
            'ipAddress' => (is_array($this->metadata) ? ($this->metadata['ip'] ?? null) : null) ?? $detectedIp,
            'browser' => (is_array($this->metadata) ? ($this->metadata['browser'] ?? null) : null) ?? $device['browser'],
            'source' => $this->telegram_chat_id || (int) $this->source_id === 2 ? 'telegram' : 'web',
            'device' => $device,
            'sourceChannel' => $this->telegram_chat_id ? 'Telegram Bot' : ((is_array($this->metadata) ? ($this->metadata['channel'] ?? null) : null) ?? 'Web Portal ('.($device['browser'] ?? "noma'lum brauzer").')'),
            'telegramChatId' => $this->telegram_chat_id,
            'audioUrl' => $extractedAudioUrl,
            'videoUrl' => $extractedVideoUrl,
            'media' => $media,
            'pinfl' => $pinfl,
            'mfo' => $mfo,
            'localCode' => $localCode,
            'unreadCommentCount' => (int) ($this->unread_comment_count ?? 0),
            'comments' => $comments,
        ];
    }
}
