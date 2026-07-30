<?php

declare(strict_types=1);

namespace App\Modules\Notification\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Modules\Notification\Infrastructure\Eloquent\Notification;
use App\Modules\Organization\Infrastructure\Eloquent\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $notifications = Notification::query()
            ->with('deliveries')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('event_type'), fn($q) => $q->where('event_type', $request->event_type))
            ->when($request->filled('notifiable_type'), fn($q) => $q->where('notifiable_type', $request->notifiable_type))
            ->when($request->filled('notifiable_id'), fn($q) => $q->where('notifiable_id', $request->notifiable_id))
            ->when($request->filled('correlation_id'), fn($q) => $q->where('correlation_id', $request->correlation_id))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $notification = Notification::with('deliveries')->findOrFail($id);

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);

        return response()->json([
            'data' => new NotificationResource($notification),
            'message' => 'Marked as read',
        ]);
    }
}
