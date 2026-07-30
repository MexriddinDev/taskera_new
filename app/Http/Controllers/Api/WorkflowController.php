<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkflowResource;
use App\Modules\Automation\Infrastructure\Eloquent\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $workflows = Workflow::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('entity_type'), fn($q) => $q->where('entity_type', $request->entity_type))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => WorkflowResource::collection($workflows),
            'meta' => [
                'current_page' => $workflows->currentPage(),
                'last_page' => $workflows->lastPage(),
                'per_page' => $workflows->perPage(),
                'total' => $workflows->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'entity_type' => 'required|string|max:64',
            'definition' => 'required|array',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
        ]);

        $orgId = (int) $request->header('X-Organization-Id', 1);

        $workflow = Workflow::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'version' => 1,
            'definition' => $validated['definition'],
            'status' => $validated['status'] ?? 'DRAFT',
        ]);

        return response()->json([
            'data' => new WorkflowResource($workflow),
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);

        return response()->json([
            'data' => new WorkflowResource($workflow),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:64',
            'name' => 'sometimes|required|string|max:255',
            'entity_type' => 'sometimes|required|string|max:64',
            'definition' => 'sometimes|required|array',
        ]);

        $workflow->update($validated);

        return response()->json([
            'data' => new WorkflowResource($workflow->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function publish(Request $request, $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update([
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);

        return response()->json([
            'data' => new WorkflowResource($workflow->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function archive(Request $request, $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update(['status' => 'ARCHIVED']);

        return response()->json([
            'data' => new WorkflowResource($workflow->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
