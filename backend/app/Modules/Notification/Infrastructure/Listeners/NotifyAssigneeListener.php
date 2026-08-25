<?php

namespace App\Modules\Notification\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAssigneeListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Notify ticket assignee.
        logger()->info('NotifyAssigneeListener processed event: ' . get_class($event));
    }
}
