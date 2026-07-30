<?php
namespace App\Modules\Ticketing\Domain\Events;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class TicketPriorityChanged {
    use Dispatchable, SerializesModels;
    public function __construct(public Ticket $ticket, public int $fromPriorityId, public int $toPriorityId, public ?int $changedByUserId) {}
}
