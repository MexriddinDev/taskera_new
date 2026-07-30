<?php

namespace App\Modules\Notification\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyStakeholdersListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Notify stakeholders of status change.
        logger()->info('NotifyStakeholdersListener processed event: ' . get_class($event));
    }
}
