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
    private function authorizeTaskAccess($user, Task $task, string $action = 'view'): void
    {
        if (!$user) {
            abort(401, 'Tizimga kiring');
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($task->assignee_user_id === $user->id) {
            return;
        }

        // O'QISH: `tasks.view` / `tickets.view` — ko'rish huquqlari.
        if ($action === 'view' && ($user->hasPermission('tasks.view') || $user->hasPermission('tickets.view'))) {
            return;
        }

        // YOZISH: ko'rish huquqi yozishga ruxsat BERMAYDI. Boshqa xodimga biriktirilgan
        // vazifani faqat biriktirish huquqiga ega xodim yoki bo'lim admini tahrirlay oladi.
        if ($action === 'update' && ($user->hasPermission('tickets.assign') || $user->isDepartmentAdmin())) {
            return;
        }

        if ($action === 'delete' && $user->hasPermission('tickets.delete')) {
            return;
        }

        abort(403, "Sizda ushbu vazifani {$action} qilish huquqi yo'q");
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $user = $request->user();

        $query = Task::query()
            ->with('assignee')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('assignee_user_id'), fn($q) => $q->where('assignee_user_id', $request->assignee_user_id))
            ->when($request->filled('taskable_type'), fn($q) => $q->where('taskable_type', $request->taskable_type))
            ->when($request->filled('taskable_id'), fn($q) => $q->where('taskable_id', $request->taskable_id))
            ->when($request->filled('priority_id'), fn($q) => $q->where('priority_id', $request->priority_id))
            ->when($request->filled('due_from'), fn($q) => $q->where('due_at', '>=', $request->due_from))
            ->when($request->filled('due_to'), fn($q) => $q->where('due_at', '<=', $request->due_to));

        // Scope to user if not admin / no global view permission
        if ($user && !$user->isSuperAdmin() && !$user->hasPermission('tasks.view')) {
            $query->where('assignee_user_id', $user->id);
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
            'organization_id' => \App\Support\CurrentOrg::id($request),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'PENDING',
            'priority_id' => $validated['priority_id'] ?? null,
            'assignee_user_id' => $validated['assignee_user_id'] ?? $request->user()->id,
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
        $this->authorizeTaskAccess($request->user(), $task, 'view');

        return response()->json([
            'data' => new TaskResource($task),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $this->authorizeTaskAccess($request->user(), $task, 'update');

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
        $this->authorizeTaskAccess($request->user(), $task, 'delete');
        $task->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
