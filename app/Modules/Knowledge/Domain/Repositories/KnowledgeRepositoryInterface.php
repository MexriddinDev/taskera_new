<?php

namespace App\Modules\Knowledge\Domain\Repositories;

use Illuminate\Support\Collection;

interface KnowledgeRepositoryInterface
{
    public function searchPublished(int $organizationId, string $query): Collection;
}
