<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChangeResource;
use App\Modules\Change\Infrastructure\Eloquent\Change;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChangeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $changes = Change::query()
            ->with(['requesterUser', 'ownerUser', 'maintenanceWindows'])
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('change_type'), fn($q) => $q->where('change_type', $request->change_type))
            ->when($request->filled('approval_status'), fn($q) => $q->where('approval_status', $request->approval_status))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('change_no', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ChangeResource::collection($changes),
            'meta' => [
                'current_page' => $changes->currentPage(),
                'last_page' => $changes->lastPage(),
                'per_page' => $changes->perPage(),
                'total' => $changes->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'change_type' => 'required|in:STANDARD,NORMAL,EMERGENCY',
            'risk_level' => 'nullable|string|max:32',
            'impact' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:32',
            'requester_user_id' => 'nullable|integer|exists:users,id',
            'owner_user_id' => 'nullable|integer|exists:users,id',
            'planned_start_at' => 'nullable|date',
            'planned_end_at' => 'nullable|date',
            'backout_plan' => 'nullable|string',
            'test_plan' => 'nullable|string',
            'implementation_plan' => 'nullable|string',
        ]);

        $change = Change::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'change_no' => $request->input('change_no', 'CHG-' . Str::random(8)),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'change_type' => $validated['change_type'],
            'risk_level' => $validated['risk_level'] ?? null,
            'impact' => $validated['impact'] ?? null,
            'status' => $validated['status'] ?? 'DRAFT',
            'requester_user_id' => $validated['requester_user_id'] ?? auth()->id(),
            'owner_user_id' => $validated['owner_user_id'] ?? null,
            'planned_start_at' => $validated['planned_start_at'] ?? null,
            'planned_end_at' => $validated['planned_end_at'] ?? null,
            'backout_plan' => $validated['backout_plan'] ?? null,
            'test_plan' => $validated['test_plan'] ?? null,
            'implementation_plan' => $validated['implementation_plan'] ?? null,
            'approval_status' => 'PENDING',
        ]);

        return response()->json([
            'data' => new ChangeResource($change->load(['requesterUser', 'ownerUser'])),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $change = Change::with(['requesterUser', 'ownerUser', 'maintenanceWindows'])->findOrFail($id);

        return response()->json([
            'data' => new ChangeResource($change),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $change = Change::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'change_type' => 'sometimes|required|in:STANDARD,NORMAL,EMERGENCY',
            'risk_level' => 'nullable|string|max:32',
            'impact' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:32',
            'owner_user_id' => 'nullable|integer|exists:users,id',
            'planned_start_at' => 'nullable|date',
            'planned_end_at' => 'nullable|date',
            'actual_start_at' => 'nullable|date',
            'actual_end_at' => 'nullable|date',
            'backout_plan' => 'nullable|string',
            'test_plan' => 'nullable|string',
            'implementation_plan' => 'nullable|string',
        ]);

        $change->update($validated);

        return response()->json([
            'data' => new ChangeResource($change->load(['requesterUser', 'ownerUser'])),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $change = Change::findOrFail($id);
        $change->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $change = Change::findOrFail($id);

        $validated = $request->validate([
            'comment' => 'nullable|string',
        ]);

        $change->update([
            'approval_status' => 'APPROVED',
            'status' => 'APPROVED',
        ]);

        return response()->json([
            'data' => new ChangeResource($change->load(['requesterUser', 'ownerUser'])),
            'message' => 'Change approved',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $change = Change::findOrFail($id);

        $validated = $request->validate([
            'comment' => 'nullable|string',
        ]);

        $change->update([
            'approval_status' => 'REJECTED',
            'status' => 'REJECTED',
        ]);

        return response()->json([
            'data' => new ChangeResource($change->load(['requesterUser', 'ownerUser'])),
            'message' => 'Change rejected',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
