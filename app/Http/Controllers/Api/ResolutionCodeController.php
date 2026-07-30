<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResolutionCodeResource;
use App\Models\Itms\ResolutionCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResolutionCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $resolutionCodes = ResolutionCode::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('requires_note'), fn($q) => $q->where('requires_note', $request->boolean('requires_note')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ResolutionCodeResource::collection($resolutionCodes),
            'meta' => [
                'current_page' => $resolutionCodes->currentPage(),
                'last_page' => $resolutionCodes->lastPage(),
                'per_page' => $resolutionCodes->perPage(),
                'total' => $resolutionCodes->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:128',
            'requires_note' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $resolutionCode = ResolutionCode::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'requires_note' => $validated['requires_note'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new ResolutionCodeResource($resolutionCode),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $resolutionCode = ResolutionCode::findOrFail($id);

        return response()->json([
            'data' => new ResolutionCodeResource($resolutionCode),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $resolutionCode = ResolutionCode::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:32',
            'name' => 'sometimes|required|string|max:128',
            'requires_note' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $resolutionCode->update($validated);

        return response()->json([
            'data' => new ResolutionCodeResource($resolutionCode),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $resolutionCode = ResolutionCode::findOrFail($id);
        $resolutionCode->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
