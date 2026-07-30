<?php

namespace App\Modules\SLA\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PauseOrResumeSlaListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Pause or resume SLA timer.
        logger()->info('PauseOrResumeSlaListener processed event: ' . get_class($event));
    }
}
