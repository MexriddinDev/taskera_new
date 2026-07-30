<?php

namespace App\Modules\Knowledge\Infrastructure\Repositories;

use App\Modules\Knowledge\Domain\Repositories\KnowledgeRepositoryInterface;
use App\Modules\Knowledge\Infrastructure\Eloquent\KnowledgeArticle;
use Illuminate\Support\Collection;

class KnowledgeRepository implements KnowledgeRepositoryInterface
{
    public function searchPublished(int $organizationId, string $query): Collection
    {
        return KnowledgeArticle::where('organization_id', $organizationId)
            ->where('status', 'PUBLISHED')
            ->where('title', 'like', "%{$query}%")
            ->get();
    }
}
