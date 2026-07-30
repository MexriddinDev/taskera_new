<?php

namespace App\Modules\Telegram\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SyncTelegramThreadListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Sync ticket status with Telegram chat.
        logger()->info('SyncTelegramThreadListener processed event: ' . get_class($event));
    }
}
