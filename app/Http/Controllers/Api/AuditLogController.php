<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Modules\Audit\Infrastructure\Eloquent\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $logs = AuditLog::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->action))
            ->when($request->filled('auditable_type'), fn($q) => $q->where('auditable_type', $request->auditable_type))
            ->when($request->filled('actor_user_id'), fn($q) => $q->where('actor_user_id', $request->actor_user_id))
            ->when($request->filled('date_from'), fn($q) => $q->where('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->where('created_at', '<=', $request->date_to . ' 23:59:59'))
            ->when($request->filled('source'), fn($q) => $q->where('source', $request->source))
            ->when($request->filled('correlation_id'), fn($q) => $q->where('correlation_id', $request->correlation_id))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $log = AuditLog::findOrFail($id);

        return response()->json([
            'data' => new AuditLogResource($log),
        ]);
    }
}
