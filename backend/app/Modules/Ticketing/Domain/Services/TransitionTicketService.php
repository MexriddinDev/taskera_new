<?php

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketStatusChanged;
use Illuminate\Support\Facades\DB;

class TransitionTicketService
{
    public function __construct(private TicketRepositoryInterface $ticketRepository) {}

    public function execute(int $ticketId, int $toStatusId, ?int $changedByUserId, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticketId, $toStatusId, $changedByUserId, $reason) {
            $ticket = $this->ticketRepository->getForUpdate($ticketId);
            $fromStatusId = $ticket->status_id;

            if ($fromStatusId !== $toStatusId) {
                $rule = DB::table('ticket_status_transitions')
                    ->where('from_status_id', $fromStatusId)
                    ->where('to_status_id', $toStatusId)
                    ->first();

                if ($rule && ! $rule->is_active) {
                    throw new \DomainException("Ushbu holatga o'tish taqiqlangan ({$fromStatusId} -> {$toStatusId})");
                }

                if ($rule && $rule->requires_comment && empty($reason)) {
                    throw new \InvalidArgumentException("Ushbu holatga o'tish uchun izoh (sabab) ko'rsatilishi shart.");
                }
            }

            $ticket->status_id = $toStatusId;
            $this->ticketRepository->save($ticket);

            DB::table('ticket_status_history')->insert([
                'ticket_id' => $ticket->id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatusId,
                'changed_by' => $changedByUserId,
                'source_id' => 1,
                'action' => 'STATUS_TRANSITION',
                'reason' => $reason,
                'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
                'created_at' => now(),
            ]);

            event(new TicketStatusChanged($ticket, $fromStatusId, $toStatusId, $changedByUserId));
            return $ticket;
        });
    }
}
