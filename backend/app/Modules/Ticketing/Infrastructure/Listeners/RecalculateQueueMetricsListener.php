<?php

namespace App\Modules\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RecalculateQueueMetricsListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Recalculate queue metrics.
        logger()->info('RecalculateQueueMetricsListener processed event: ' . get_class($event));
    }
}
