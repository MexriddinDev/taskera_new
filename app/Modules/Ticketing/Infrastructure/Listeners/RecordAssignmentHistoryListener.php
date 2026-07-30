<?php

namespace App\Modules\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RecordAssignmentHistoryListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Record assignment history.
        logger()->info('RecordAssignmentHistoryListener processed event: ' . get_class($event));
    }
}
