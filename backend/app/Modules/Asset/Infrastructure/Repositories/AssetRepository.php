<?php

namespace App\Modules\Asset\Infrastructure\Repositories;

use App\Modules\Asset\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Asset\Infrastructure\Eloquent\Asset;
use Illuminate\Support\Collection;

class AssetRepository implements AssetRepositoryInterface
{
    public function findByIdentifiers(int $organizationId, string $assetTag, ?string $serialNumber): ?Asset
    {
        $query = Asset::where('organization_id', $organizationId)
            ->where('asset_tag', $assetTag);

        if ($serialNumber) {
            $query->orWhere(function ($q) use ($organizationId, $serialNumber) {
                $q->where('organization_id', $organizationId)
                  ->where('serial_number', $serialNumber);
            });
        }

        return $query->first();
    }

    public function relationshipGraph(int $assetId): Collection
    {
        return Asset::where('id', $assetId)->with(['organization'])->get();
    }

    public function save(Asset $asset): bool
    {
        return $asset->save();
    }
}
