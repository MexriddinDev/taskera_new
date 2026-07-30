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
            'screenshotUrl' => null,
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
            'audioUrl' => is_array($this->metadata) ? ($this->metadata['audio_url'] ?? null) : null,
            'videoUrl' => is_array($this->metadata) ? ($this->metadata['video_url'] ?? null) : null,
            'pinfl' => $requester?->pinfl ?? (is_array($this->metadata) ? ($this->metadata['pinfl'] ?? null) : null) ?? '33110804070014',
            'mfo' => $requester?->mfo ?? (is_array($this->metadata) ? ($this->metadata['mfo'] ?? null) : null) ?? '37149',
            'localCode' => is_array($this->metadata) ? ($this->metadata['local_code'] ?? '017160') : '017160',
        ];
    }
}
