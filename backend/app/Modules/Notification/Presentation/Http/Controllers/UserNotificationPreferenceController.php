<?php

declare(strict_types=1);

namespace App\Modules\Notification\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationPreferenceResource;
use App\Modules\Notification\Infrastructure\Eloquent\UserNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $preferences = UserNotificationPreference::where('user_id', $userId)
            ->when($request->filled('event_type'), fn($q) => $q->where('event_type', $request->event_type))
            ->when($request->filled('channel_id'), fn($q) => $q->where('channel_id', $request->channel_id))
            ->get();

        return response()->json([
            'data' => UserNotificationPreferenceResource::collection($preferences),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.event_type' => 'required|string|max:128',
            'preferences.*.channel_id' => 'required|integer|exists:notification_channels,id',
            'preferences.*.is_enabled' => 'nullable|boolean',
            'preferences.*.quiet_hours' => 'nullable|array',
        ]);

        $updated = [];

        foreach ($validated['preferences'] as $pref) {
            $preference = UserNotificationPreference::updateOrCreate(
                [
                    'user_id' => $userId,
                    'event_type' => $pref['event_type'],
                    'channel_id' => $pref['channel_id'],
                ],
                [
                    'is_enabled' => $pref['is_enabled'] ?? true,
                    'quiet_hours' => $pref['quiet_hours'] ?? null,
                ]
            );

            $updated[] = $preference;
        }

        return response()->json([
            'data' => UserNotificationPreferenceResource::collection($updated),
        ]);
    }
}
