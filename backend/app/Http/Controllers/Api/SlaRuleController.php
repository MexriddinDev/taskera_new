<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlaRuleResource;
use App\Models\Itms\SlaRule;
use App\Modules\Ticketing\Domain\Services\TicketSlaService;
use App\Support\CurrentOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SlaRuleController extends Controller
{
    public function teams(Request $request): JsonResponse
    {
        $teams = DB::table('teams')
            ->where('organization_id', CurrentOrg::id($request))
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        return response()->json(['data' => $teams]);
    }

    /** Qoida formasi uchun muhimliklar ro'yxati. */
    public function priorities(): JsonResponse
    {
        $priorities = DB::table('ticket_priorities')
            ->orderByDesc('weight')
            ->get(['id', 'code', 'name', 'color']);

        return response()->json(['data' => $priorities]);
    }

    public function index(Request $request): JsonResponse
    {
        $orgId = CurrentOrg::id($request);
        $perPage = min(max((int) $request->query('per_page', '15'), 1), 100);

        $rules = SlaRule::query()
            ->with(['team:id,name,code', 'priority:id,name,code,color'])
            ->where('organization_id', $orgId)
            ->when($request->filled('team_id'), fn ($query) => $query->where('team_id', (int) $request->query('team_id')))
            ->when($request->filled('priority_id'), fn ($query) => $query->where('priority_id', (int) $request->query('priority_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('team', fn ($team) => $team->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('is_active')
            ->orderBy('team_id')
            ->orderByDesc('is_default')
            ->orderByRaw('priority_id IS NULL DESC')
            ->orderBy('priority_id')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => SlaRuleResource::collection($rules),
            'meta' => [
                'current_page' => $rules->currentPage(),
                'last_page' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = CurrentOrg::id($request);
        $validated = $this->validated($request, $orgId);
        $validated['priority_id'] = $validated['priority_id'] ?? null;

        $rule = DB::transaction(function () use ($request, $orgId, $validated) {
            return SlaRule::create($validated + [
                'organization_id' => $orgId,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        TicketSlaService::forgetRules();

        return response()->json(['data' => new SlaRuleResource($rule->load(['team:id,name,code', 'priority:id,name,code,color']))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $orgId = CurrentOrg::id($request);
        $rule = SlaRule::where('organization_id', $orgId)->findOrFail($id);

        // "Default holat" — guruhning doimiy sozlamasi, oddiy qoida emas:
        // undan faqat ikkita muddat o'zgaradi. Nomi yoki guruhi o'zgarsa
        // shablonsiz zayavka qaysi muddatni olishi tushunarsiz bo'lib qolardi.
        $validated = $rule->is_default
            ? $this->validatedDefault($request)
            : $this->validated($request, $orgId, true);

        DB::transaction(function () use ($request, $rule, $validated) {
            $rule->update($validated + ['updated_by' => $request->user()->id]);
        });

        TicketSlaService::forgetRules();

        return response()->json(['data' => new SlaRuleResource($rule->fresh()->load(['team:id,name,code', 'priority:id,name,code,color']))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $rule = SlaRule::where('organization_id', CurrentOrg::id($request))->findOrFail($id);

        if ($rule->is_default) {
            return response()->json([
                'message' => 'Default holat o‘chirilmaydi — faqat muddatini o‘zgartirish mumkin.',
            ], 422);
        }

        $rule->delete();
        TicketSlaService::forgetRules();

        return response()->json(['message' => 'SLA qoidasi o‘chirildi.']);
    }

    /** Default holatda faqat ikkita muddat tahrirlanadi. */
    private function validatedDefault(Request $request): array
    {
        return $request->validate([
            'accept_minutes' => ['required', 'integer', 'min:1', 'max:100000'],
            'work_minutes' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
    }

    private function validated(Request $request, int $orgId, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'team_id' => [$required, 'integer', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('organization_id', $orgId)->whereNull('deleted_at'))],
            // Bo'sh qiymat — guruhning umumiy qoidasi (barcha muhimliklar uchun).
            'priority_id' => ['nullable', 'integer', Rule::exists('ticket_priorities', 'id')],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'accept_minutes' => [$required, 'integer', 'min:1', 'max:100000'],
            'work_minutes' => [$required, 'integer', 'min:1', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
