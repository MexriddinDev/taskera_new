<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCatalogItemResource;
use App\Models\Catalog\ServiceCatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCatalogItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $items = ServiceCatalogItem::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('service_offering_id'), fn($q) => $q->where('service_offering_id', $request->service_offering_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ServiceCatalogItemResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_offering_id' => 'nullable|integer',
            'code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_schema' => 'nullable|array',
            'fulfillment_workflow_id' => 'nullable|integer',
            'approval_workflow_id' => 'nullable|integer',
            'estimated_minutes' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $item = ServiceCatalogItem::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'service_offering_id' => $validated['service_offering_id'] ?? null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'form_schema' => $validated['form_schema'] ?? null,
            'fulfillment_workflow_id' => $validated['fulfillment_workflow_id'] ?? null,
            'approval_workflow_id' => $validated['approval_workflow_id'] ?? null,
            'estimated_minutes' => $validated['estimated_minutes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new ServiceCatalogItemResource($item),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $item = ServiceCatalogItem::findOrFail($id);

        return response()->json([
            'data' => new ServiceCatalogItemResource($item),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $item = ServiceCatalogItem::findOrFail($id);

        $validated = $request->validate([
            'service_offering_id' => 'nullable|integer',
            'code' => 'sometimes|required|string|max:64',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'form_schema' => 'nullable|array',
            'fulfillment_workflow_id' => 'nullable|integer',
            'approval_workflow_id' => 'nullable|integer',
            'estimated_minutes' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'data' => new ServiceCatalogItemResource($item),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $item = ServiceCatalogItem::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
