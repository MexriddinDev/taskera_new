<?php

namespace App\Modules\SLA\Domain\Repositories;

use Illuminate\Support\Collection;

interface SlaRepositoryInterface
{
    public function activePolicyFor(int $organizationId, string $appliesToType): ?object;
    public function dueTimers(): Collection;
}
