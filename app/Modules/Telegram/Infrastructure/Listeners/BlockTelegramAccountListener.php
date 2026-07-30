<?php

namespace App\Modules\Telegram\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class BlockTelegramAccountListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Block associated Telegram account.
        logger()->info('BlockTelegramAccountListener processed event: ' . get_class($event));
    }
}
