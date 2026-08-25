<?php

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use Illuminate\Support\Facades\DB;

class CreateTicketService
{
    public function __construct(
        private TicketRepositoryInterface $ticketRepository
    ) {}

    public function execute(array $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            $ticket = new Ticket();
            $ticket->organization_id = $data['organization_id'];
            $ticket->ticket_no = $this->ticketRepository->nextNumber($data['organization_id']);
            $ticket->ticket_type = $data['ticket_type'] ?? 'INCIDENT';
            $ticket->subject = $data['subject'];
            $ticket->description = $data['description'];
            $ticket->status_id = $data['status_id'] ?? 1; // NEW
            $ticket->priority_id = $data['priority_id'] ?? 3; // MEDIUM
            $ticket->source_id = $data['source_id'] ?? 1; // WEB
            $ticket->requester_user_id = $data['requester_user_id'];
            $ticket->requester_employee_id = $data['requester_employee_id'] ?? null;
            $ticket->department_id = $data['department_id'] ?? null;
            $ticket->category_id = $data['category_id'] ?? null;

            $this->ticketRepository->save($ticket);

            event(new TicketCreated($ticket));

            return $ticket;
        });
    }
}
