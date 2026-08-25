<?php

namespace App\Modules\Ticketing\Domain\Repositories;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;

interface TicketRepositoryInterface
{
    public function findById(int $id): ?Ticket;
    public function findByPublicId(string $publicId): ?Ticket;
    public function getForUpdate(int $id): ?Ticket;
    public function nextNumber(int $organizationId): string;
    public function save(Ticket $ticket): bool;
}
