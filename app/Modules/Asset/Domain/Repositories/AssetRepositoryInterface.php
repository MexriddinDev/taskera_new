<?php

namespace App\Modules\Asset\Domain\Repositories;

use App\Modules\Asset\Infrastructure\Eloquent\Asset;
use Illuminate\Support\Collection;

interface AssetRepositoryInterface
{
    public function findByIdentifiers(int $organizationId, string $assetTag, ?string $serialNumber): ?Asset;
    public function relationshipGraph(int $assetId): Collection;
    public function save(Asset $asset): bool;
}
