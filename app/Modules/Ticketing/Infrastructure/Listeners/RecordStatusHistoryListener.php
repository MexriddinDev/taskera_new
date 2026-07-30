<?php

namespace App\Modules\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RecordStatusHistoryListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Record status transition.
        logger()->info('RecordStatusHistoryListener processed event: ' . get_class($event));
    }
}
