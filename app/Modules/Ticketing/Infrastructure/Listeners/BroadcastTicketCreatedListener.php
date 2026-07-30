<?php

namespace App\Modules\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class BroadcastTicketCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Broadcast web socket event for ticket created.
        logger()->info('BroadcastTicketCreatedListener processed event: ' . get_class($event));
    }
}
