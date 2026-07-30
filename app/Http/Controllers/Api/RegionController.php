<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Modules\Organization\Infrastructure\Eloquent\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $regions = Region::query()
            ->with('manager')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => RegionResource::collection($regions),
            'meta' => [
                'current_page' => $regions->currentPage(),
                'last_page' => $regions->lastPage(),
                'per_page' => $regions->perPage(),
                'total' => $regions->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:255',
            'manager_employee_id' => 'nullable|integer|exists:employees,id',
            'is_active' => 'nullable|boolean',
        ]);

        $region = Region::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'manager_employee_id' => $validated['manager_employee_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new RegionResource($region->load('manager')),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $region = Region::with('manager')->findOrFail($id);

        return response()->json([
            'data' => new RegionResource($region),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $region = Region::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:32',
            'name' => 'sometimes|required|string|max:255',
            'manager_employee_id' => 'nullable|integer|exists:employees,id',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $region->update($validated);

        return response()->json([
            'data' => new RegionResource($region->load('manager')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $region = Region::findOrFail($id);
        $region->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
