<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetModelResource;
use App\Modules\Asset\Infrastructure\Eloquent\AssetModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetModelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $assetModels = AssetModel::query()
            ->with('manufacturer')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('manufacturer_id'), fn($q) => $q->where('manufacturer_id', $request->manufacturer_id))
            ->when($request->filled('asset_type_id'), fn($q) => $q->where('asset_type_id', $request->asset_type_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('model_name', 'like', '%' . $request->search . '%')
                  ->orWhere('part_number', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => AssetModelResource::collection($assetModels),
            'meta' => [
                'current_page' => $assetModels->currentPage(),
                'last_page' => $assetModels->lastPage(),
                'per_page' => $assetModels->perPage(),
                'total' => $assetModels->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'manufacturer_id' => 'required|integer|exists:manufacturers,id',
            'asset_type_id' => 'required|integer|exists:asset_types,id',
            'model_name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:128',
            'attributes_schema' => 'nullable|array',
        ]);

        $assetModel = AssetModel::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'manufacturer_id' => $validated['manufacturer_id'],
            'asset_type_id' => $validated['asset_type_id'],
            'model_name' => $validated['model_name'],
            'part_number' => $validated['part_number'] ?? null,
            'attributes_schema' => $validated['attributes_schema'] ?? null,
        ]);

        return response()->json([
            'data' => new AssetModelResource($assetModel->load('manufacturer')),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $assetModel = AssetModel::with('manufacturer')->findOrFail($id);

        return response()->json([
            'data' => new AssetModelResource($assetModel),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $assetModel = AssetModel::findOrFail($id);

        $validated = $request->validate([
            'manufacturer_id' => 'sometimes|required|integer|exists:manufacturers,id',
            'asset_type_id' => 'sometimes|required|integer|exists:asset_types,id',
            'model_name' => 'sometimes|required|string|max:255',
            'part_number' => 'nullable|string|max:128',
            'attributes_schema' => 'nullable|array',
        ]);

        $assetModel->update($validated);

        return response()->json([
            'data' => new AssetModelResource($assetModel->load('manufacturer')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $assetModel = AssetModel::findOrFail($id);
        $assetModel->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
