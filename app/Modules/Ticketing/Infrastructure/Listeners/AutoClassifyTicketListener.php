<?php

namespace App\Modules\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AutoClassifyTicketListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Auto classify category and priority.
        logger()->info('AutoClassifyTicketListener processed event: ' . get_class($event));
    }
}
