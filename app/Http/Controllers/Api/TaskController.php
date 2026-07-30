<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Collaboration\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $tasks = Task::query()
            ->with('assignee')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('assignee_user_id'), fn($q) => $q->where('assignee_user_id', $request->assignee_user_id))
            ->when($request->filled('taskable_type'), fn($q) => $q->where('taskable_type', $request->taskable_type))
            ->when($request->filled('taskable_id'), fn($q) => $q->where('taskable_id', $request->taskable_id))
            ->when($request->filled('priority_id'), fn($q) => $q->where('priority_id', $request->priority_id))
            ->when($request->filled('due_from'), fn($q) => $q->where('due_at', '>=', $request->due_from))
            ->when($request->filled('due_to'), fn($q) => $q->where('due_at', '<=', $request->due_to))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => TaskResource::collection($tasks),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:32',
            'priority_id' => 'nullable|integer|exists:ticket_priorities,id',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
            'taskable_type' => 'nullable|string|max:100',
            'taskable_id' => 'nullable|integer',
            'due_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ]);

        $task = Task::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'PENDING',
            'priority_id' => $validated['priority_id'] ?? null,
            'assignee_user_id' => $validated['assignee_user_id'] ?? null,
            'taskable_type' => $validated['taskable_type'] ?? null,
            'taskable_id' => $validated['taskable_id'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'completed_at' => $validated['completed_at'] ?? null,
        ]);

        return response()->json([
            'data' => new TaskResource($task->load('assignee')),
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $task = Task::with('assignee')->findOrFail($id);

        return response()->json([
            'data' => new TaskResource($task),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:32',
            'priority_id' => 'nullable|integer|exists:ticket_priorities,id',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
            'taskable_type' => 'nullable|string|max:100',
            'taskable_id' => 'nullable|integer',
            'due_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'COMPLETED' && !$task->completed_at) {
            $validated['completed_at'] = now();
        }

        $task->update($validated);

        return response()->json([
            'data' => new TaskResource($task->load('assignee')),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
