<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceWindowResource;
use App\Modules\Change\Infrastructure\Eloquent\MaintenanceWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MaintenanceWindowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $windows = MaintenanceWindow::query()
            ->with('change')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('change_id'), fn($q) => $q->where('change_id', $request->change_id))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => MaintenanceWindowResource::collection($windows),
            'meta' => [
                'current_page' => $windows->currentPage(),
                'last_page' => $windows->lastPage(),
                'per_page' => $windows->perPage(),
                'total' => $windows->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'recurrence_rule' => 'nullable|string',
            'status' => 'nullable|string|max:32',
            'change_id' => 'nullable|integer|exists:changes,id',
        ]);

        $window = MaintenanceWindow::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'name' => $validated['name'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'recurrence_rule' => $validated['recurrence_rule'] ?? null,
            'status' => $validated['status'] ?? 'SCHEDULED',
            'change_id' => $validated['change_id'] ?? null,
        ]);

        return response()->json([
            'data' => new MaintenanceWindowResource($window->load('change')),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $window = MaintenanceWindow::with('change')->findOrFail($id);

        return response()->json([
            'data' => new MaintenanceWindowResource($window),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $window = MaintenanceWindow::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'starts_at' => 'sometimes|required|date',
            'ends_at' => 'sometimes|required|date|after:starts_at',
            'recurrence_rule' => 'nullable|string',
            'status' => 'nullable|string|max:32',
            'change_id' => 'nullable|integer|exists:changes,id',
        ]);

        $window->update($validated);

        return response()->json([
            'data' => new MaintenanceWindowResource($window->load('change')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $window = MaintenanceWindow::findOrFail($id);
        $window->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
