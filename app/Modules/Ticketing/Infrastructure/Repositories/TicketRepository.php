<?php

namespace App\Modules\Ticketing\Infrastructure\Repositories;

use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;

class TicketRepository implements TicketRepositoryInterface
{
    public function findById(int $id): ?Ticket
    {
        return Ticket::find($id);
    }

    public function findByPublicId(string $publicId): ?Ticket
    {
        return Ticket::where('public_id', $publicId)->first();
    }

    public function getForUpdate(int $id): ?Ticket
    {
        return Ticket::where('id', $id)->lockForUpdate()->first();
    }

    public function nextNumber(int $organizationId): string
    {
        $count = Ticket::where('organization_id', $organizationId)->count() + 1;
        return sprintf('INC-%06d', $count);
    }

    public function save(Ticket $ticket): bool
    {
        return $ticket->save();
    }
}
