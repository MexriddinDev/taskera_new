<?php

namespace App\Modules\Audit\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class WriteAuditLogListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Write immutable audit log record.
        logger()->info('WriteAuditLogListener processed event: ' . get_class($event));
    }
}
