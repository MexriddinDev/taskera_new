<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $tags = Tag::query()
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => $tags->map(fn($tag) => $this->formatTag($tag)),
            'meta' => [
                'current_page' => $tags->currentPage(),
                'last_page' => $tags->lastPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:32',
        ]);

        $orgId = (int) $request->header('X-Organization-Id', 1);

        $tag = Tag::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
        ]);

        return response()->json([
            'data' => $this->formatTag($tag),
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);

        return response()->json([
            'data' => $this->formatTag($tag),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'color' => 'nullable|string|max:32',
        ]);

        $tag->update($validated);

        return response()->json([
            'data' => $this->formatTag($tag->fresh()),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    private function formatTag($tag): array
    {
        return [
            'id' => $tag->id,
            'public_id' => $tag->public_id,
            'organization_id' => $tag->organization_id,
            'name' => $tag->name,
            'color' => $tag->color,
            'created_at' => $tag->created_at?->toISOString(),
            'updated_at' => $tag->updated_at?->toISOString(),
        ];
    }
}
