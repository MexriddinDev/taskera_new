<?php

namespace App\Modules\Automation\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RunAutomationRulesListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        // Execute active automation rules.
        logger()->info('RunAutomationRulesListener processed event: ' . get_class($event));
    }
}
