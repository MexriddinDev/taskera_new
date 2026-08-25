<?php

namespace App\Modules\Notification\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyDispatcherListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Notify ticket dispatchers.
        logger()->info('NotifyDispatcherListener processed event: ' . get_class($event));
    }
}
