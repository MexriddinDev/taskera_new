<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlaTargetResource;
use App\Modules\SLA\Infrastructure\Eloquent\SlaTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaTargetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $targets = SlaTarget::query()
            ->when($request->filled('sla_policy_id'), fn($q) => $q->where('sla_policy_id', $request->sla_policy_id))
            ->when($request->filled('priority_id'), fn($q) => $q->where('priority_id', $request->priority_id))
            ->when($request->filled('metric_type'), fn($q) => $q->where('metric_type', $request->metric_type))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => SlaTargetResource::collection($targets),
            'meta' => [
                'current_page' => $targets->currentPage(),
                'last_page' => $targets->lastPage(),
                'per_page' => $targets->perPage(),
                'total' => $targets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sla_policy_id' => 'required|integer|exists:sla_policies,id',
            'priority_id' => 'required|integer|exists:ticket_priorities,id',
            'metric_type' => 'required|string|max:32',
            'target_minutes' => 'required|integer|min:1',
            'warning_minutes' => 'nullable|integer|min:1',
            'escalation_minutes' => 'nullable|integer|min:1',
            'pause_statuses' => 'nullable|array',
            'pause_statuses.*' => 'integer',
            'is_active' => 'nullable|boolean',
        ]);

        $target = SlaTarget::create([
            'sla_policy_id' => $validated['sla_policy_id'],
            'priority_id' => $validated['priority_id'],
            'metric_type' => $validated['metric_type'],
            'target_minutes' => $validated['target_minutes'],
            'warning_minutes' => $validated['warning_minutes'] ?? null,
            'escalation_minutes' => $validated['escalation_minutes'] ?? null,
            'pause_statuses' => $validated['pause_statuses'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new SlaTargetResource($target),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $target = SlaTarget::findOrFail($id);

        return response()->json([
            'data' => new SlaTargetResource($target),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = SlaTarget::findOrFail($id);

        $validated = $request->validate([
            'sla_policy_id' => 'sometimes|required|integer|exists:sla_policies,id',
            'priority_id' => 'sometimes|required|integer|exists:ticket_priorities,id',
            'metric_type' => 'sometimes|required|string|max:32',
            'target_minutes' => 'sometimes|required|integer|min:1',
            'warning_minutes' => 'nullable|integer|min:1',
            'escalation_minutes' => 'nullable|integer|min:1',
            'pause_statuses' => 'nullable|array',
            'pause_statuses.*' => 'integer',
            'is_active' => 'nullable|boolean',
        ]);

        $target->update($validated);

        return response()->json([
            'data' => new SlaTargetResource($target->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $target = SlaTarget::findOrFail($id);
        $target->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
