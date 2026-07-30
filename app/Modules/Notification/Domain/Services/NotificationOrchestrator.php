<?php

namespace App\Modules\Notification\Domain\Services;

use App\Modules\Notification\Infrastructure\Jobs\SendTelegramNotificationJob;
use App\Modules\Notification\Infrastructure\Jobs\SendEmailNotificationJob;
use Illuminate\Support\Facades\DB;

class NotificationOrchestrator
{
    public function send(int $organizationId, string $eventType, string $recipient, string $title, string $body, array $channels = ['TELEGRAM']): void
    {
        $notificationId = DB::table('notifications')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $organizationId,
            'event_type' => $eventType,
            'notifiable_type' => 'App\\Modules\\Identity\\Infrastructure\\Eloquent\\User',
            'notifiable_id' => 1,
            'template_code' => $eventType,
            'title' => $title,
            'body' => $body,
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'created_at' => now(),
        ]);

        foreach ($channels as $channel) {
            if ($channel === 'TELEGRAM') {
                dispatch(new SendTelegramNotificationJob($notificationId, $recipient, $body));
            } elseif ($channel === 'EMAIL') {
                dispatch(new SendEmailNotificationJob($notificationId, $recipient, $title, $body));
            }
        }
    }
}
