<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\Integration\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookEndpointController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $endpoints = WebhookEndpoint::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => WebhookEndpointResource::collection($endpoints),
            'meta' => [
                'current_page' => $endpoints->currentPage(),
                'last_page' => $endpoints->lastPage(),
                'per_page' => $endpoints->perPage(),
                'total' => $endpoints->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:2048',
            'secret_ref' => 'required|string|max:255',
            'subscribed_events' => 'required|array',
            'is_active' => 'nullable|boolean',
        ]);

        $orgId = (int) $request->header('X-Organization-Id', 1);

        $endpoint = WebhookEndpoint::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret_ref' => $validated['secret_ref'],
            'subscribed_events' => $validated['subscribed_events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new WebhookEndpointResource($endpoint),
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::findOrFail($id);

        return response()->json([
            'data' => new WebhookEndpointResource($endpoint),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|required|string|max:2048',
            'secret_ref' => 'sometimes|required|string|max:255',
            'subscribed_events' => 'sometimes|required|array',
            'is_active' => 'nullable|boolean',
        ]);

        $endpoint->update($validated);

        return response()->json([
            'data' => new WebhookEndpointResource($endpoint->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::findOrFail($id);
        $endpoint->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
