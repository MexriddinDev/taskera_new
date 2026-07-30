<?php

namespace App\Modules\Identity\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RevokeSessionsListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Revoke active employee user sessions.
        logger()->info('RevokeSessionsListener processed event: ' . get_class($event));
    }
}
