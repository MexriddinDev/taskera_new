<?php

namespace App\Modules\SLA\Infrastructure\Repositories;

use App\Modules\SLA\Domain\Repositories\SlaRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SlaRepository implements SlaRepositoryInterface
{
    public function activePolicyFor(int $organizationId, string $appliesToType): ?object
    {
        return DB::table('sla_policies')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->first();
    }

    public function dueTimers(): Collection
    {
        return DB::table('ticket_slas')
            ->where('status', 'RUNNING')
            ->where('due_at', '<=', now())
            ->get();
    }
}
