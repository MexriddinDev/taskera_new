<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarEventResource;
use App\Models\Collaboration\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $events = CalendarEvent::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('starts_from'), fn($q) => $q->where('starts_at', '>=', $request->starts_from))
            ->when($request->filled('starts_to'), fn($q) => $q->where('starts_at', '<=', $request->starts_to))
            ->when($request->filled('ends_from'), fn($q) => $q->where('ends_at', '>=', $request->ends_from))
            ->when($request->filled('ends_to'), fn($q) => $q->where('ends_at', '<=', $request->ends_to))
            ->when($request->filled('eventable_type'), fn($q) => $q->where('eventable_type', $request->eventable_type))
            ->when($request->filled('eventable_id'), fn($q) => $q->where('eventable_id', $request->eventable_id))
            ->when($request->filled('created_by'), fn($q) => $q->where('created_by', $request->created_by))
            ->orderBy('starts_at')
            ->paginate($perPage);

        return response()->json([
            'data' => CalendarEventResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'timezone_id' => 'required|integer|exists:timezones,id',
            'eventable_type' => 'nullable|string|max:100',
            'eventable_id' => 'nullable|integer',
            'recurrence_rule' => 'nullable|string|max:500',
        ]);

        $event = CalendarEvent::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'timezone_id' => $validated['timezone_id'],
            'eventable_type' => $validated['eventable_type'] ?? null,
            'eventable_id' => $validated['eventable_id'] ?? null,
            'recurrence_rule' => $validated['recurrence_rule'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new CalendarEventResource($event),
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $event = CalendarEvent::findOrFail($id);

        return response()->json([
            'data' => new CalendarEventResource($event),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $event = CalendarEvent::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'sometimes|required|date',
            'ends_at' => 'sometimes|required|date|after_or_equal:starts_at',
            'timezone_id' => 'sometimes|required|integer|exists:timezones,id',
            'eventable_type' => 'nullable|string|max:100',
            'eventable_id' => 'nullable|integer',
            'recurrence_rule' => 'nullable|string|max:500',
        ]);

        $event->update($validated);

        return response()->json([
            'data' => new CalendarEventResource($event),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $event = CalendarEvent::findOrFail($id);
        $event->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
