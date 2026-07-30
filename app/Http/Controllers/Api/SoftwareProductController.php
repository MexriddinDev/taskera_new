<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SoftwareProductResource;
use App\Modules\Asset\Infrastructure\Eloquent\SoftwareProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoftwareProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $products = SoftwareProduct::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('publisher', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('is_licensed'), fn($q) => $q->where('is_licensed', $request->boolean('is_licensed')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => SoftwareProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publisher' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'version_pattern' => 'nullable|string|max:255',
            'is_licensed' => 'nullable|boolean',
        ]);

        $product = SoftwareProduct::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'publisher' => $validated['publisher'],
            'name' => $validated['name'],
            'version_pattern' => $validated['version_pattern'] ?? null,
            'is_licensed' => $validated['is_licensed'] ?? true,
        ]);

        return response()->json([
            'data' => new SoftwareProductResource($product),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $product = SoftwareProduct::findOrFail($id);

        return response()->json([
            'data' => new SoftwareProductResource($product),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $product = SoftwareProduct::findOrFail($id);

        $validated = $request->validate([
            'publisher' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'version_pattern' => 'nullable|string|max:255',
            'is_licensed' => 'nullable|boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'data' => new SoftwareProductResource($product),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $product = SoftwareProduct::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
