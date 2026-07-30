<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationResource;
use App\Models\Integration\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $integrations = Integration::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('integration_type_id'), fn($q) => $q->where('integration_type_id', $request->integration_type_id))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => IntegrationResource::collection($integrations),
            'meta' => [
                'current_page' => $integrations->currentPage(),
                'last_page' => $integrations->lastPage(),
                'per_page' => $integrations->perPage(),
                'total' => $integrations->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'integration_type_id' => 'required|integer|exists:integration_types,id',
            'name' => 'required|string|max:255',
            'base_url' => 'nullable|string|max:1024',
            'secret_ref' => 'nullable|string|max:255',
            'config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $orgId = (int) $request->header('X-Organization-Id', 1);

        $integration = Integration::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'integration_type_id' => $validated['integration_type_id'],
            'name' => $validated['name'],
            'base_url' => $validated['base_url'] ?? null,
            'secret_ref' => $validated['secret_ref'] ?? null,
            'config' => $validated['config'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new IntegrationResource($integration),
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);

        return response()->json([
            'data' => new IntegrationResource($integration),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);

        $validated = $request->validate([
            'integration_type_id' => 'sometimes|required|integer|exists:integration_types,id',
            'name' => 'sometimes|required|string|max:255',
            'base_url' => 'nullable|string|max:1024',
            'secret_ref' => 'nullable|string|max:255',
            'config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $integration->update($validated);

        return response()->json([
            'data' => new IntegrationResource($integration->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);
        $integration->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
