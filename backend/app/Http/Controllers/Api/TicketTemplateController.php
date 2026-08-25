<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Infrastructure\Eloquent\TicketTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = TicketTemplate::query()
            ->with('team:id,name')
            ->when($request->filled('team_id'), fn ($q) => $q->where('team_id', (int) $request->input('team_id')))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $templates->map(fn ($t) => [
                'id' => $t->id,
                'teamId' => $t->team_id,
                'name' => $t->name,
                'content' => $t->content,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'team_id' => 'nullable|integer|exists:teams,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $template = DB::transaction(function () use ($validated) {
            return TicketTemplate::create([
                'name' => $validated['name'],
                'content' => $validated['content'],
                'team_id' => $validated['team_id'] ?? null,
                'is_active' => true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
        });

        return response()->json([
            'data' => $template->load('team:id,name'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = TicketTemplate::find($id);

        if (!$template) {
            return response()->json(['message' => 'Shablon topilmadi'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'team_id' => ['nullable', Rule::exists('teams', 'id')],
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $template->update($validated);

        return response()->json([
            'data' => $template->load('team:id,name'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $template = TicketTemplate::find($id);

        if (!$template) {
            return response()->json(['message' => 'Shablon topilmadi'], 404);
        }

        $template->delete();

        return response()->json(['message' => 'Shablon o\'chirildi']);
    }
}
