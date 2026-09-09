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
use Illuminate\Validation\ValidationException;

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
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

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
            ->orderByRaw('priority_id IS NULL DESC')
            ->orderBy('priority_id')
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
        $this->ensureSlotIsFree($orgId, (int) $validated['team_id'], $validated['priority_id']);

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
        $validated = $this->validated($request, $orgId, true);
        $teamId = (int) ($validated['team_id'] ?? $rule->team_id);
        $priorityId = array_key_exists('priority_id', $validated) ? $validated['priority_id'] : $rule->priority_id;
        $this->ensureSlotIsFree($orgId, $teamId, $priorityId === null ? null : (int) $priorityId, $rule->id);

        DB::transaction(function () use ($request, $rule, $validated) {
            $rule->update($validated + ['updated_by' => $request->user()->id]);
        });

        TicketSlaService::forgetRules();

        return response()->json(['data' => new SlaRuleResource($rule->fresh()->load(['team:id,name,code', 'priority:id,name,code,color']))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $rule = SlaRule::where('organization_id', CurrentOrg::id($request))->findOrFail($id);
        $rule->delete();
        TicketSlaService::forgetRules();

        return response()->json(['message' => 'SLA qoidasi o‘chirildi.']);
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

    /**
     * Guruhda bir nechta qoida bo'lishi mumkin, lekin HAR BIR MUHIMLIK uchun
     * bittadan: aks holda bir zayavkaga ikkita muddat to'g'ri kelib qolardi.
     * `priority_id = null` — guruhning umumiy qoidasi, u ham bitta bo'ladi.
     */
    private function ensureSlotIsFree(int $orgId, int $teamId, ?int $priorityId, ?int $exceptId = null): void
    {
        $exists = SlaRule::where('organization_id', $orgId)
            ->where('team_id', $teamId)
            ->when($priorityId === null,
                fn ($query) => $query->whereNull('priority_id'),
                fn ($query) => $query->where('priority_id', $priorityId))
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if (! $exists) {
            return;
        }

        throw ValidationException::withMessages([
            'priority_id' => $priorityId === null
                ? 'Bu guruhda umumiy (barcha muhimliklar uchun) qoida allaqachon bor.'
                : 'Bu guruhda shu muhimlik uchun qoida allaqachon bor.',
        ]);
    }
}
