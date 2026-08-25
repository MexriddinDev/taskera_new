<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApprovalRequestResource;
use App\Models\Catalog\ApprovalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $approvals = ApprovalRequest::query()
            ->with('steps')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('approvable_type'), fn($q) => $q->where('approvable_type', $request->approvable_type))
            ->when($request->filled('approvable_id'), fn($q) => $q->where('approvable_id', $request->approvable_id))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ApprovalRequestResource::collection($approvals),
            'meta' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $approval = ApprovalRequest::with('steps')->findOrFail($id);

        return response()->json([
            'data' => new ApprovalRequestResource($approval),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $approval = ApprovalRequest::findOrFail($id);

        $validated = $request->validate([
            'comment' => 'nullable|string',
            'decision_by' => 'nullable|integer',
        ]);

        $approval->update([
            'status' => 'APPROVED',
            'completed_at' => now(),
        ]);

        return response()->json([
            'data' => new ApprovalRequestResource($approval->load('steps')),
            'message' => 'Approval request approved',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $approval = ApprovalRequest::findOrFail($id);

        $validated = $request->validate([
            'comment' => 'nullable|string',
            'decision_by' => 'nullable|integer',
        ]);

        $approval->update([
            'status' => 'REJECTED',
            'completed_at' => now(),
        ]);

        return response()->json([
            'data' => new ApprovalRequestResource($approval->load('steps')),
            'message' => 'Approval request rejected',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
