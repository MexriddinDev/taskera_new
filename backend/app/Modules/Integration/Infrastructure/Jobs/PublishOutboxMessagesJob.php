<?php

namespace App\Modules\Integration\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PublishOutboxMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $messages = DB::table('outbox_messages')
            ->whereNull('published_at')
            ->limit(100)
            ->get();

        foreach ($messages as $msg) {
            // Publish event to Redis stream / WebSockets
            DB::table('outbox_messages')->where('id', $msg->id)->update([
                'published_at' => now(),
            ]);
        }
    }
}
