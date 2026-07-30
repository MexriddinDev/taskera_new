<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PositionResource;
use App\Modules\Organization\Infrastructure\Eloquent\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $positions = Position::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('is_managerial'), fn($q) => $q->where('is_managerial', $request->boolean('is_managerial')))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => PositionResource::collection($positions),
            'meta' => [
                'current_page' => $positions->currentPage(),
                'last_page' => $positions->lastPage(),
                'per_page' => $positions->perPage(),
                'total' => $positions->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:255',
            'grade' => 'nullable|string|max:32',
            'is_managerial' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $position = Position::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'grade' => $validated['grade'] ?? null,
            'is_managerial' => $validated['is_managerial'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new PositionResource($position),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $position = Position::findOrFail($id);

        return response()->json([
            'data' => new PositionResource($position),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $position = Position::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:32',
            'name' => 'sometimes|required|string|max:255',
            'grade' => 'nullable|string|max:32',
            'is_managerial' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $position->update($validated);

        return response()->json([
            'data' => new PositionResource($position),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $position = Position::findOrFail($id);
        $position->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
