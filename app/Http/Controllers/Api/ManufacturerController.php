<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManufacturerResource;
use App\Modules\Asset\Infrastructure\Eloquent\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $manufacturers = Manufacturer::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('website', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ManufacturerResource::collection($manufacturers),
            'meta' => [
                'current_page' => $manufacturers->currentPage(),
                'last_page' => $manufacturers->lastPage(),
                'per_page' => $manufacturers->perPage(),
                'total' => $manufacturers->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'website' => 'nullable|string|max:500',
        ]);

        $manufacturer = Manufacturer::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'name' => $validated['name'],
            'website' => $validated['website'] ?? null,
        ]);

        return response()->json([
            'data' => new ManufacturerResource($manufacturer),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $manufacturer = Manufacturer::findOrFail($id);

        return response()->json([
            'data' => new ManufacturerResource($manufacturer),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $manufacturer = Manufacturer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'website' => 'nullable|string|max:500',
        ]);

        $manufacturer->update($validated);

        return response()->json([
            'data' => new ManufacturerResource($manufacturer),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $manufacturer = Manufacturer::findOrFail($id);
        $manufacturer->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
