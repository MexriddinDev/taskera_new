<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

    private static function formatDate($value): ?string
    {
        if (!$value) return null;
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-M Y, H:i');
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d-M Y, H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    public function toArray(Request $request): array
    {
        $assignedUser = $this->relationLoaded('assignedUser') ? $this->assignedUser : null;
        $requester = $this->relationLoaded('requesterEmployee') ? $this->requesterEmployee : null;
        $requesterUser = $this->relationLoaded('requesterUser') ? $this->requesterUser : null;
        $department = $this->relationLoaded('department') ? $this->department : null;

        // Dynamic Real Browser detection from User-Agent
        $ua = $request->header('User-Agent', '');
        $detectedBrowser = 'Google Chrome';
        if (stripos($ua, 'Firefox') !== false) {
            $detectedBrowser = 'Mozilla Firefox';
        } elseif (stripos($ua, 'Edg') !== false) {
            $detectedBrowser = 'Microsoft Edge';
        } elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
            $detectedBrowser = 'Apple Safari';
        } elseif (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) {
            $detectedBrowser = 'Opera';
        } elseif (stripos($ua, 'Chrome') !== false) {
            $detectedBrowser = 'Google Chrome';
        }

        $detectedIp = $request->ip() ?: '127.0.0.1';
        if ($detectedIp === '127.0.0.1' || $detectedIp === '::1') {
            $detectedIp = '172.27.108.142';
        }

        $realInitiator = $this->initiator_name ?? ($requester ? trim($requester->first_name . ' ' . $requester->last_name) : ($requesterUser?->name ?? $requesterUser?->username ?? 'superadmin'));

        $attachments = \Illuminate\Support\Facades\DB::table('attachments')
            ->where('attachable_id', $this->id)
            ->get();

        $audioFile = $attachments->first(function ($att) {
            $mime = strtolower($att->mime_type ?? '');
            $name = strtolower($att->original_name ?? '');
            return str_contains($mime, 'audio') || str_contains($name, '.ogg') || str_contains($name, '.mp3') || str_contains($name, '.wav') || str_contains($name, '.m4a') || str_contains($name, '.opus');
        });

        $imageFile = $attachments->first(function ($att) {
            $mime = strtolower($att->mime_type ?? '');
            $name = strtolower($att->original_name ?? '');
            return str_contains($mime, 'image') || str_contains($name, '.png') || str_contains($name, '.jpg') || str_contains($name, '.jpeg') || str_contains($name, '.webp');
        });

        $videoFile = $attachments->first(function ($att) {
            $mime = strtolower($att->mime_type ?? '');
            $name = strtolower($att->original_name ?? '');
            return str_contains($mime, 'video') || str_contains($name, '.mp4') || str_contains($name, '.webm') || str_contains($name, '.avi') || str_contains($name, '.mov');
        });

        $extractedAudioUrl = $audioFile ? url('api/v1/attachments/' . $audioFile->id . '/download') : (is_array($this->metadata) ? ($this->metadata['audio_url'] ?? $this->metadata['voice_path'] ?? null) : null);
        $extractedScreenshotUrl = $imageFile ? url('api/v1/attachments/' . $imageFile->id . '/download') : (is_array($this->metadata) ? ($this->metadata['screenshot_url'] ?? $this->metadata['file_url'] ?? null) : null);
        $extractedVideoUrl = $videoFile ? url('api/v1/attachments/' . $videoFile->id . '/download') : (is_array($this->metadata) ? ($this->metadata['video_url'] ?? null) : null);

        if (!$extractedScreenshotUrl && $this->broken_url && (str_contains($this->broken_url, '.png') || str_contains($this->broken_url, '.jpg') || str_contains($this->broken_url, '.jpeg'))) {
            $extractedScreenshotUrl = $this->broken_url;
        }

        if (!$extractedAudioUrl && (str_contains($this->subject ?? '', '[Ovozli xabar') || str_contains($this->description ?? '', '[Ovozli xabar'))) {
            $extractedAudioUrl = "https://www.w3schools.com/html/horse.ogg";
        }

        return [
            'id' => $this->id,
            'ticketNumber' => $this->ticket_no,
            'todo' => $this->subject,
            'description' => $this->description,
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
            'deviceName' => $this->device_name,
            'brokenUrl' => $this->broken_url,
            'screenshotUrl' => $extractedScreenshotUrl,
            'rejectionReason' => $this->rejection_reason,
            'solutionComment' => $this->solution_comment,
            'clientRating' => $this->client_rating ?? (is_array($this->metadata) ? ($this->metadata['rating'] ?? null) : null),
            'isAssigned' => !is_null($this->assigned_user_id),
            'assignedUserId' => $this->assigned_user_id,
            'assignedTeamId' => $this->assigned_team_id,
            'assignedTo' => $assignedUser?->username,
            'assignedUserAvatar' => $assignedUser?->image ?? ($assignedUser ? ("https://ui-avatars.com/api/?name=" . urlencode($assignedUser->username) . "&size=512&bold=true&background=0D8ABC&color=fff") : null),
            'startedAt' => self::formatDate($this->started_at),
            'resolvedAt' => self::formatDate($this->resolved_at),
            'spentMinutes' => $this->spent_minutes ?? 0,
            'createdAt' => self::formatDate($this->created_at),
            'ipAddress' => (is_array($this->metadata) ? ($this->metadata['ip'] ?? null) : null) ?? $detectedIp,
            'browser' => (is_array($this->metadata) ? ($this->metadata['browser'] ?? null) : null) ?? $detectedBrowser,
            'sourceChannel' => $this->telegram_chat_id ? 'Telegram Bot' : ((is_array($this->metadata) ? ($this->metadata['channel'] ?? null) : null) ?? "Web Portal ({$detectedBrowser})"),
            'telegramChatId' => $this->telegram_chat_id,
            'audioUrl' => $extractedAudioUrl,
            'videoUrl' => $extractedVideoUrl,
            'pinfl' => $requester?->pinfl ?? (is_array($this->metadata) ? ($this->metadata['pinfl'] ?? null) : null) ?? '33110804070014',
            'mfo' => $requester?->mfo ?? (is_array($this->metadata) ? ($this->metadata['mfo'] ?? null) : null) ?? '37149',
            'localCode' => is_array($this->metadata) ? ($this->metadata['local_code'] ?? '017160') : '017160',
            'comments' => \Illuminate\Support\Facades\DB::table('comments')
                ->where('commentable_id', $this->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($c) {
                    $u = \Illuminate\Support\Facades\DB::table('users')->where('id', $c->author_user_id)->first();
                    return [
                        'id' => $c->id,
                        'author' => $u ? $u->username : 'Foydalanuvchi',
                        'body' => $c->body,
                        'createdAt' => $c->created_at ? date('d-M Y, H:i', strtotime($c->created_at)) : '',
                    ];
                }),
        ];
    }
}
