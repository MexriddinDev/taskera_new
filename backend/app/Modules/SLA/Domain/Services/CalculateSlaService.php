<?php

namespace App\Modules\SLA\Domain\Services;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Support\Facades\DB;

class CalculateSlaService
{
    public function calculate(Ticket $ticket): void
    {
        $target = DB::table('sla_targets')->where('priority_id', $ticket->priority_id)->first();
        if (!$target) return;

        DB::table('ticket_slas')->updateOrInsert(
            ['ticket_id' => $ticket->id, 'sla_target_id' => $target->id],
            [
                'public_id' => (string) \Illuminate\Support\Str::uuid(),
                'organization_id' => $ticket->organization_id,
                'started_at' => now(),
                'due_at' => now()->addMinutes($target->target_minutes),
                'status' => 'RUNNING',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
