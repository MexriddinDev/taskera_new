<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Modules\Organization\Infrastructure\Eloquent\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $branches = Branch::query()
            ->with('region')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('region_id'), fn($q) => $q->where('region_id', $request->region_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => BranchResource::collection($branches),
            'meta' => [
                'current_page' => $branches->currentPage(),
                'last_page' => $branches->lastPage(),
                'per_page' => $branches->perPage(),
                'total' => $branches->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:255',
            'region_id' => 'required|integer|exists:regions,id',
            'parent_id' => 'nullable|integer|exists:branches,id',
            'branch_type' => 'nullable|string|max:32',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'manager_employee_id' => 'nullable|integer|exists:employees,id',
            'is_active' => 'nullable|boolean',
        ]);

        $branch = Branch::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'region_id' => $validated['region_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'branch_type' => $validated['branch_type'] ?? 'HEADQUARTERS',
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'manager_employee_id' => $validated['manager_employee_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new BranchResource($branch->load('region')),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $branch = Branch::with('region')->findOrFail($id);

        return response()->json([
            'data' => new BranchResource($branch),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:32',
            'name' => 'sometimes|required|string|max:255',
            'region_id' => 'sometimes|required|integer|exists:regions,id',
            'parent_id' => 'nullable|integer|exists:branches,id',
            'branch_type' => 'nullable|string|max:32',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'manager_employee_id' => 'nullable|integer|exists:employees,id',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $branch->update($validated);

        return response()->json([
            'data' => new BranchResource($branch->load('region')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
