<?php

declare(strict_types=1);

namespace App\Modules\Notification\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationTemplateResource;
use App\Modules\Notification\Infrastructure\Eloquent\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $templates = NotificationTemplate::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('code'), fn($q) => $q->where('code', $request->code))
            ->when($request->filled('channel_id'), fn($q) => $q->where('channel_id', $request->channel_id))
            ->when($request->filled('locale_id'), fn($q) => $q->where('locale_id', $request->locale_id))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => NotificationTemplateResource::collection($templates),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64',
            'channel_id' => 'required|integer|exists:notification_channels,id',
            'locale_id' => 'required|integer|exists:locales,id',
            'subject_template' => 'nullable|string',
            'body_template' => 'required|string',
            'version' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $template = NotificationTemplate::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => $validated['code'],
            'channel_id' => $validated['channel_id'],
            'locale_id' => $validated['locale_id'],
            'subject_template' => $validated['subject_template'] ?? null,
            'body_template' => $validated['body_template'],
            'version' => $validated['version'] ?? 1,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:64',
            'channel_id' => 'sometimes|required|integer|exists:notification_channels,id',
            'locale_id' => 'sometimes|required|integer|exists:locales,id',
            'subject_template' => 'nullable|string',
            'body_template' => 'sometimes|required|string',
            'version' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update(array_merge($validated, [
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
