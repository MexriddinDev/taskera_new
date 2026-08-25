<?php

namespace App\Modules\SLA\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class InitializeSlaListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Initialize SLA calculations for ticket.
        logger()->info('InitializeSlaListener processed event: ' . get_class($event));
    }
}
