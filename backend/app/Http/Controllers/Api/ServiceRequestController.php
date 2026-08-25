<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $requests = ServiceRequest::query()
            ->with(['ticket', 'catalogItem'])
            ->when($request->filled('fulfillment_status'), fn($q) => $q->where('fulfillment_status', $request->fulfillment_status))
            ->when($request->filled('catalog_item_id'), fn($q) => $q->where('catalog_item_id', $request->catalog_item_id))
            ->when($request->filled('requested_for_user_id'), fn($q) => $q->where('requested_for_user_id', $request->requested_for_user_id))
            ->orderBy('ticket_id')
            ->paginate($perPage);

        return response()->json([
            'data' => $requests,
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $serviceRequest = ServiceRequest::with(['ticket', 'catalogItem'])->findOrFail($id);

        return response()->json([
            'data' => $serviceRequest,
        ]);
    }
}
