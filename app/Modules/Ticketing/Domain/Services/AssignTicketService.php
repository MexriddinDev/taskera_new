<?php

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketAssigned;
use Illuminate\Support\Facades\DB;

class AssignTicketService
{
    public function __construct(private TicketRepositoryInterface $ticketRepository) {}

    public function execute(int $ticketId, ?int $teamId, ?int $assigneeUserId, int $assignedByUserId, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticketId, $teamId, $assigneeUserId, $assignedByUserId, $reason) {
            $ticket = $this->ticketRepository->getForUpdate($ticketId);
            $fromTeamId = $ticket->assigned_team_id;
            $fromUserId = $ticket->assigned_user_id;

            if ($fromUserId && $fromUserId !== $assigneeUserId && empty(trim((string) $reason))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reason' => "Boshqa xodimga biriktirilgan zayavkani o'ziga olishda sabab kiritish majburiy.",
                ]);
            }

            $ticket->assigned_team_id = $teamId;
            $ticket->assigned_user_id = $assigneeUserId;
            $this->ticketRepository->save($ticket);

            DB::table('ticket_assignment_history')->insert([
                'ticket_id' => $ticket->id,
                'from_team_id' => $fromTeamId,
                'to_team_id' => $teamId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $assigneeUserId,
                'changed_by' => $assignedByUserId,
                'reason' => $reason,
                'source_id' => 1,
                'created_at' => now(),
            ]);

            event(new TicketAssigned($ticket, $fromUserId, $assigneeUserId, $assignedByUserId));
            return $ticket;
        });
    }
}
