<?php

namespace App\Modules\SLA\Infrastructure\Listeners;

use App\Modules\SLA\Domain\Services\SlaEngine;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;

final class SlaTicketObserver
{
    public function saved(Ticket $ticket): void
    {
        if ($ticket->wasRecentlyCreated || $ticket->wasChanged(['status_id', 'assigned_user_id', 'assigned_team_id', 'started_at', 'first_response_at', 'category_id', 'priority_id'])) {
            app(SlaEngine::class)->sync($ticket, auth()->id());
        }
    }
}
