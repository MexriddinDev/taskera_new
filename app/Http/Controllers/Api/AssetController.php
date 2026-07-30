<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetResource;
use App\Modules\Asset\Infrastructure\Eloquent\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $assets = Asset::query()
            ->with('model.manufacturer')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('asset_type_id'), fn($q) => $q->where('asset_type_id', $request->asset_type_id))
            ->when($request->filled('status_id'), fn($q) => $q->where('status_id', $request->status_id))
            ->when($request->filled('model_id'), fn($q) => $q->where('model_id', $request->model_id))
            ->when($request->filled('vendor_id'), fn($q) => $q->where('vendor_id', $request->vendor_id))
            ->when($request->filled('department_id'), fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('asset_tag', 'like', '%' . $request->search . '%')
                  ->orWhere('serial_number', 'like', '%' . $request->search . '%')
                  ->orWhere('hostname', 'like', '%' . $request->search . '%');
            }))
            ->when($request->filled('source_system'), fn($q) => $q->where('source_system', $request->source_system))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => AssetResource::collection($assets),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_tag' => 'required|string|max:128',
            'serial_number' => 'nullable|string|max:255',
            'hostname' => 'nullable|string|max:255',
            'asset_type_id' => 'required|integer|exists:asset_types,id',
            'status_id' => 'required|integer|exists:asset_statuses,id',
            'model_id' => 'nullable|integer|exists:asset_models,id',
            'owner_employee_id' => 'nullable|integer|exists:employees,id',
            'custodian_employee_id' => 'nullable|integer|exists:employees,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'purchase_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date',
            'installed_at' => 'nullable|date',
            'retired_at' => 'nullable|date',
            'ip_addresses' => 'nullable|array',
            'mac_addresses' => 'nullable|array',
            'os_name' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:128',
            'source_system' => 'nullable|string|max:32',
            'external_id' => 'nullable|string|max:255',
            'last_discovered_at' => 'nullable|date',
            'attributes' => 'nullable|array',
        ]);

        $asset = Asset::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'asset_tag' => $validated['asset_tag'],
            'serial_number' => $validated['serial_number'] ?? null,
            'hostname' => $validated['hostname'] ?? null,
            'asset_type_id' => $validated['asset_type_id'],
            'status_id' => $validated['status_id'],
            'model_id' => $validated['model_id'] ?? null,
            'owner_employee_id' => $validated['owner_employee_id'] ?? null,
            'custodian_employee_id' => $validated['custodian_employee_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'vendor_id' => $validated['vendor_id'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'warranty_end_date' => $validated['warranty_end_date'] ?? null,
            'installed_at' => $validated['installed_at'] ?? null,
            'retired_at' => $validated['retired_at'] ?? null,
            'ip_addresses' => $validated['ip_addresses'] ?? null,
            'mac_addresses' => $validated['mac_addresses'] ?? null,
            'os_name' => $validated['os_name'] ?? null,
            'os_version' => $validated['os_version'] ?? null,
            'source_system' => $validated['source_system'] ?? 'MANUAL',
            'external_id' => $validated['external_id'] ?? null,
            'last_discovered_at' => $validated['last_discovered_at'] ?? null,
            'attributes' => $validated['attributes'] ?? null,
        ]);

        return response()->json([
            'data' => new AssetResource($asset),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $asset = Asset::with(['model.manufacturer', 'owner', 'custodian', 'department', 'branch', 'vendor'])->findOrFail($id);

        return response()->json([
            'data' => new AssetResource($asset),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'asset_tag' => 'sometimes|required|string|max:128',
            'serial_number' => 'nullable|string|max:255',
            'hostname' => 'nullable|string|max:255',
            'asset_type_id' => 'sometimes|required|integer|exists:asset_types,id',
            'status_id' => 'sometimes|required|integer|exists:asset_statuses,id',
            'model_id' => 'nullable|integer|exists:asset_models,id',
            'owner_employee_id' => 'nullable|integer|exists:employees,id',
            'custodian_employee_id' => 'nullable|integer|exists:employees,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'purchase_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date',
            'installed_at' => 'nullable|date',
            'retired_at' => 'nullable|date',
            'ip_addresses' => 'nullable|array',
            'mac_addresses' => 'nullable|array',
            'os_name' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:128',
            'source_system' => 'nullable|string|max:32',
            'external_id' => 'nullable|string|max:255',
            'last_discovered_at' => 'nullable|date',
            'attributes' => 'nullable|array',
        ]);

        $asset->update($validated);

        return response()->json([
            'data' => new AssetResource($asset),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $asset->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
