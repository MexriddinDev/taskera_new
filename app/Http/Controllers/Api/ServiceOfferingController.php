<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceOfferingResource;
use App\Models\Itms\ServiceOffering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceOfferingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $serviceOfferings = ServiceOffering::query()
            ->with('service')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('service_id'), fn($q) => $q->where('service_id', $request->service_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('is_requestable'), fn($q) => $q->where('is_requestable', $request->boolean('is_requestable')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ServiceOfferingResource::collection($serviceOfferings),
            'meta' => [
                'current_page' => $serviceOfferings->currentPage(),
                'last_page' => $serviceOfferings->lastPage(),
                'per_page' => $serviceOfferings->perPage(),
                'total' => $serviceOfferings->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'service_id' => 'required|integer|exists:services,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'default_sla_policy_id' => 'nullable|integer|exists:sla_policies,id',
            'is_requestable' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'form_schema' => 'nullable|array',
        ]);

        $serviceOffering = ServiceOffering::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'service_id' => $validated['service_id'],
            'category_id' => $validated['category_id'] ?? null,
            'default_sla_policy_id' => $validated['default_sla_policy_id'] ?? null,
            'is_requestable' => $validated['is_requestable'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'form_schema' => $validated['form_schema'] ?? null,
        ]);

        return response()->json([
            'data' => new ServiceOfferingResource($serviceOffering->load('service')),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $serviceOffering = ServiceOffering::with('service')->findOrFail($id);

        return response()->json([
            'data' => new ServiceOfferingResource($serviceOffering),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $serviceOffering = ServiceOffering::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:64',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'service_id' => 'sometimes|required|integer|exists:services,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'default_sla_policy_id' => 'nullable|integer|exists:sla_policies,id',
            'is_requestable' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'form_schema' => 'nullable|array',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $serviceOffering->update($validated);

        return response()->json([
            'data' => new ServiceOfferingResource($serviceOffering->load('service')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $serviceOffering = ServiceOffering::findOrFail($id);
        $serviceOffering->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
