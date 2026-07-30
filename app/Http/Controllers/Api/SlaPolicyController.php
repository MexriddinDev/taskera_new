<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlaPolicyResource;
use App\Models\Itms\SlaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $slaPolicies = SlaPolicy::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => SlaPolicyResource::collection($slaPolicies),
            'meta' => [
                'current_page' => $slaPolicies->currentPage(),
                'last_page' => $slaPolicies->lastPage(),
                'per_page' => $slaPolicies->perPage(),
                'total' => $slaPolicies->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'calendar_id' => 'nullable|integer',
            'applies_to_type' => 'nullable|string|max:32',
            'conditions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'version' => 'nullable|integer|min:1',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $slaPolicy = SlaPolicy::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'calendar_id' => $validated['calendar_id'] ?? null,
            'applies_to_type' => $validated['applies_to_type'] ?? 'ALL',
            'conditions' => $validated['conditions'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'version' => $validated['version'] ?? 1,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
        ]);

        return response()->json([
            'data' => new SlaPolicyResource($slaPolicy),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $slaPolicy = SlaPolicy::findOrFail($id);

        return response()->json([
            'data' => new SlaPolicyResource($slaPolicy),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $slaPolicy = SlaPolicy::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:64',
            'name' => 'sometimes|required|string|max:255',
            'calendar_id' => 'nullable|integer',
            'applies_to_type' => 'nullable|string|max:32',
            'conditions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'version' => 'nullable|integer|min:1',
            'effective_from' => 'sometimes|required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $slaPolicy->update($validated);

        return response()->json([
            'data' => new SlaPolicyResource($slaPolicy),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $slaPolicy = SlaPolicy::findOrFail($id);
        $slaPolicy->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
