<?php

namespace App\Modules\Asset\Domain\Services;

use App\Modules\Asset\Infrastructure\Eloquent\Asset;
use Illuminate\Support\Facades\DB;

class RegisterAssetDiscoveryService
{
    public function register(int $organizationId, array $discoveryData): Asset
    {
        return DB::transaction(function () use ($organizationId, $discoveryData) {
            $asset = Asset::updateOrCreate(
                ['organization_id' => $organizationId, 'asset_tag' => $discoveryData['asset_tag']],
                array_merge($discoveryData, ['last_discovered_at' => now()])
            );
            return $asset;
        });
    }
}
