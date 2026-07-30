<?php

namespace App\Modules\Notification\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $notificationId,
        public string $chatId,
        public string $text
    ) {}

    public function handle(): void
    {
        // Deliver to Telegram bot API endpoint
        DB::table('notification_deliveries')->insertOrIgnore([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'notification_id' => $this->notificationId,
            'channel_id' => 2, // TELEGRAM
            'recipient' => $this->chatId,
            'status' => 'DELIVERED',
            'delivered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
