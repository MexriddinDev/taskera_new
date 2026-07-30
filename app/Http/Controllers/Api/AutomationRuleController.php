<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AutomationRuleResource;
use App\Modules\Automation\Infrastructure\Eloquent\AutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AutomationRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $rules = AutomationRule::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('event_type'), fn($q) => $q->where('event_type', $request->event_type))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('priority')
            ->paginate($perPage);

        return response()->json([
            'data' => AutomationRuleResource::collection($rules),
            'meta' => [
                'current_page' => $rules->currentPage(),
                'last_page' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event_type' => 'required|string|max:128',
            'conditions' => 'nullable|array',
            'actions' => 'required|array',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'stop_processing' => 'nullable|boolean',
        ]);

        $orgId = (int) $request->header('X-Organization-Id', 1);

        $rule = AutomationRule::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'event_type' => $validated['event_type'],
            'conditions' => $validated['conditions'] ?? null,
            'actions' => $validated['actions'],
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'stop_processing' => $validated['stop_processing'] ?? false,
        ]);

        return response()->json([
            'data' => new AutomationRuleResource($rule),
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);

        return response()->json([
            'data' => new AutomationRuleResource($rule),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'event_type' => 'sometimes|required|string|max:128',
            'conditions' => 'nullable|array',
            'actions' => 'sometimes|required|array',
            'priority' => 'nullable|integer|min:0',
            'stop_processing' => 'nullable|boolean',
        ]);

        $rule->update($validated);

        return response()->json([
            'data' => new AutomationRuleResource($rule->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function toggle(Request $request, $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json([
            'data' => new AutomationRuleResource($rule->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
