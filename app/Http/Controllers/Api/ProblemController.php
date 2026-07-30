<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProblemResource;
use App\Modules\Problem\Infrastructure\Eloquent\Problem;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProblemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $problems = Problem::query()
            ->with('ownerUser')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('priority_id'), fn($q) => $q->where('priority_id', $request->priority_id))
            ->when($request->filled('known_error'), fn($q) => $q->where('known_error', $request->boolean('known_error')))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('problem_no', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ProblemResource::collection($problems),
            'meta' => [
                'current_page' => $problems->currentPage(),
                'last_page' => $problems->lastPage(),
                'per_page' => $problems->perPage(),
                'total' => $problems->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|integer',
            'priority_id' => 'nullable|integer',
            'owner_user_id' => 'nullable|integer|exists:users,id',
            'known_error' => 'nullable|boolean',
            'root_cause' => 'nullable|string',
            'workaround' => 'nullable|string',
        ]);

        $problem = Problem::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'problem_no' => $request->input('problem_no', 'PRB-' . Str::random(8)),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 1,
            'priority_id' => $validated['priority_id'] ?? null,
            'owner_user_id' => $validated['owner_user_id'] ?? null,
            'known_error' => $validated['known_error'] ?? false,
            'root_cause' => $validated['root_cause'] ?? null,
            'workaround' => $validated['workaround'] ?? null,
        ]);

        return response()->json([
            'data' => new ProblemResource($problem->load('ownerUser')),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $problem = Problem::with(['ownerUser', 'tickets'])->findOrFail($id);

        return response()->json([
            'data' => new ProblemResource($problem),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|integer',
            'priority_id' => 'nullable|integer',
            'owner_user_id' => 'nullable|integer|exists:users,id',
            'known_error' => 'nullable|boolean',
            'root_cause' => 'nullable|string',
            'workaround' => 'nullable|string',
            'resolved_at' => 'nullable|date',
        ]);

        $problem->update($validated);

        return response()->json([
            'data' => new ProblemResource($problem->load('ownerUser')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);
        $problem->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function linkTicket(Request $request, $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        $validated = $request->validate([
            'ticket_id' => 'required|integer|exists:tickets,id',
            'relation_type' => 'nullable|string|max:32',
        ]);

        $ticket = Ticket::findOrFail($validated['ticket_id']);

        $problem->tickets()->attach($ticket->id, [
            'relation_type' => $validated['relation_type'] ?? 'related',
            'created_at' => now(),
        ]);

        $problem->load('tickets');

        return response()->json([
            'data' => new ProblemResource($problem),
            'message' => 'Ticket linked',
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
