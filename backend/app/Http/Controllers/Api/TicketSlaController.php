<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketSlaResource;
use App\Modules\SLA\Infrastructure\Eloquent\TicketSla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketSlaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $ticketSlas = TicketSla::query()
            ->with('slaTarget')
            ->when($request->filled('ticket_id'), fn($q) => $q->where('ticket_id', $request->ticket_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => TicketSlaResource::collection($ticketSlas),
            'meta' => [
                'current_page' => $ticketSlas->currentPage(),
                'last_page' => $ticketSlas->lastPage(),
                'per_page' => $ticketSlas->perPage(),
                'total' => $ticketSlas->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticketSla = TicketSla::with('slaTarget')->findOrFail($id);

        return response()->json([
            'data' => new TicketSlaResource($ticketSla),
        ]);
    }

    public function forTicket(Request $request, int $ticketId): JsonResponse
    {
        $ticketSlas = TicketSla::with('slaTarget')
            ->where('ticket_id', $ticketId)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => TicketSlaResource::collection($ticketSlas),
        ]);
    }
}
