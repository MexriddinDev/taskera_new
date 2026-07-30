<?php

namespace App\Modules\Ticketing\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ScanAttachmentListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Scan attachment for viruses.
        logger()->info('ScanAttachmentListener processed event: ' . get_class($event));
    }
}
