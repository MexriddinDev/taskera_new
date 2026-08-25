<?php

namespace App\Modules\Asset\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\Asset\Domain\Services\RegisterAssetDiscoveryService;

class AssetController extends Controller
{
    public function discover(Request $request, RegisterAssetDiscoveryService $service): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer',
            'asset_tag' => 'required|string',
            'hostname' => 'nullable|string',
            'os_name' => 'nullable|string',
        ]);

        $asset = $service->register($validated['organization_id'], $validated);
        return response()->json(['status' => 'discovered', 'asset' => $asset]);
    }
}
