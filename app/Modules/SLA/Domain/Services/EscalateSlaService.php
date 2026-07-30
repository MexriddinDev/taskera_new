<?php

namespace App\Modules\SLA\Domain\Services;

use Illuminate\Support\Facades\DB;

class EscalateSlaService
{
    public function execute(int $ticketSlaId): void
    {
        DB::table('ticket_slas')->where('id', $ticketSlaId)->update([
            'status' => 'BREACHED',
            'breached_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
