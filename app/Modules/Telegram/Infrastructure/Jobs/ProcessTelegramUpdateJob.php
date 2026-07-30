<?php

namespace App\Modules\Telegram\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $telegramUpdateDbId) {}

    public function handle(): void
    {
        $update = DB::table('telegram_updates')->where('id', $this->telegramUpdateDbId)->first();
        if (!$update || $update->status === 'PROCESSED') return;

        // Process update payload, state machine & Media downloading
        DB::table('telegram_updates')->where('id', $this->telegramUpdateDbId)->update([
            'status' => 'PROCESSED',
            'processed_at' => now(),
        ]);
    }
}
