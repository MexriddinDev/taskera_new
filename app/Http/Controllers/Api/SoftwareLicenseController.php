<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SoftwareLicenseResource;
use App\Modules\Asset\Infrastructure\Eloquent\SoftwareLicense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoftwareLicenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $licenses = SoftwareLicense::query()
            ->with('softwareProduct', 'vendor')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('software_product_id'), fn($q) => $q->where('software_product_id', $request->software_product_id))
            ->when($request->filled('vendor_id'), fn($q) => $q->where('vendor_id', $request->vendor_id))
            ->when($request->filled('license_type'), fn($q) => $q->where('license_type', $request->license_type))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => SoftwareLicenseResource::collection($licenses),
            'meta' => [
                'current_page' => $licenses->currentPage(),
                'last_page' => $licenses->lastPage(),
                'per_page' => $licenses->perPage(),
                'total' => $licenses->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'software_product_id' => 'required|integer|exists:software_products,id',
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'license_key_ciphertext' => 'nullable|string',
            'license_type' => 'nullable|string|max:32',
            'quantity' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'cost' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
        ]);

        $license = SoftwareLicense::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'software_product_id' => $validated['software_product_id'],
            'vendor_id' => $validated['vendor_id'] ?? null,
            'license_key_ciphertext' => $validated['license_key_ciphertext'] ?? null,
            'license_type' => $validated['license_type'] ?? 'PERPETUAL',
            'quantity' => $validated['quantity'] ?? 1,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_to' => $validated['valid_to'] ?? null,
            'cost' => $validated['cost'] ?? null,
            'currency' => $validated['currency'] ?? null,
        ]);

        return response()->json([
            'data' => new SoftwareLicenseResource($license->load('softwareProduct', 'vendor')),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $license = SoftwareLicense::with('softwareProduct', 'vendor')->findOrFail($id);

        return response()->json([
            'data' => new SoftwareLicenseResource($license),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $license = SoftwareLicense::findOrFail($id);

        $validated = $request->validate([
            'software_product_id' => 'sometimes|required|integer|exists:software_products,id',
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'license_key_ciphertext' => 'nullable|string',
            'license_type' => 'nullable|string|max:32',
            'quantity' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'cost' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
        ]);

        $license->update($validated);

        return response()->json([
            'data' => new SoftwareLicenseResource($license->load('softwareProduct', 'vendor')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $license = SoftwareLicense::findOrFail($id);
        $license->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
