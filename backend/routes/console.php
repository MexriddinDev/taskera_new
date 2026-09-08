<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sla:tick', function () {
    $count = app(\App\Modules\SLA\Domain\Services\SlaEngine::class)->tick();
    app(\App\Modules\SLA\Domain\Services\SlaNotificationService::class)->process();
    $this->info("Processed {$count} SLA timers.");
})->purpose('Evaluate due SLA thresholds and deliver pending notifications');

Schedule::command('sla:tick')->everyMinute()->withoutOverlapping();

// Eskirgan SMS kodlarini kunlik tozalash (7 kundan keyin) —
// jadval cheksiz o'sishining va eski PII saqlanishining oldini oladi.
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('sms_codes')
        ->where('expires_at', '<', now()->subDays(7))
        ->delete();
})->daily()->name('sms-codes-cleanup')->withoutOverlapping();
