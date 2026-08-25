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
        // RACE CONDITION himoyasi: MAX+1 naqshi parallel so'rovlarda dublikat
        // beradi. Shuning uchun oxirgi yozuv lockForUpdate() bilan qulflanadi —
        // bu metod tranzaksiya ichida chaqiriladi (store() dagidek).
        $last = Ticket::withTrashed()
            ->where('organization_id', $organizationId)
            ->orderByRaw('CAST(SUBSTRING(ticket_no, 5) AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('ticket_no');

        $lastNumber = $last ? (int) substr((string) $last, 4) : 0;

        return sprintf('INC-%06d', $lastNumber + 1);
    }

    public function save(Ticket $ticket): bool
    {
        return $ticket->save();
    }
}
