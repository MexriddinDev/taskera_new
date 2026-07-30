<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Infrastructure\Eloquent\Team;
use App\Modules\Organization\Infrastructure\Eloquent\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $teams = Team::query()
            ->with('managerUser')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('department_id'), fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $teams->map(fn($team) => $this->formatTeam($team)),
            'meta' => [
                'current_page' => $teams->currentPage(),
                'last_page' => $teams->lastPage(),
                'per_page' => $teams->perPage(),
                'total' => $teams->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:32',
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|integer|exists:departments,id',
            'manager_user_id' => 'nullable|integer|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        $orgId = (int) $request->header('X-Organization-Id', 1);

        $code = !empty($validated['code'])
            ? strtoupper($validated['code'])
            : strtoupper(Str::slug($validated['name'], '_'));

        $team = Team::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'code' => substr($code, 0, 32),
            'name' => $validated['name'],
            'department_id' => $validated['department_id'] ?? null,
            'manager_user_id' => $validated['manager_user_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => $this->formatTeam($team->load('managerUser')),
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $team = Team::with('managerUser')->findOrFail($id);

        return response()->json([
            'data' => $this->formatTeam($team),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $team = Team::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:32',
            'name' => 'sometimes|required|string|max:255',
            'department_id' => 'nullable|integer|exists:departments,id',
            'manager_user_id' => 'nullable|integer|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        $team->update($validated);

        return response()->json([
            'data' => $this->formatTeam($team->load('managerUser')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        $team->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function members(Request $request, $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);

        $members = TeamMember::where('team_id', $teamId)
            ->whereNull('left_at')
            ->with('user')
            ->orderBy('joined_at')
            ->get();

        $data = $members->map(fn($m) => [
            'id' => $m->user?->id ?? $m->user_id,
            'user_id' => $m->user_id,
            'name' => $m->user?->username ?? ('#' . $m->user_id),
            'username' => $m->user?->username,
            'user' => $m->user ? [
                'id' => $m->user->id,
                'username' => $m->user->username,
                'email' => $m->user->email,
            ] : null,
            'is_lead' => $m->is_lead,
            'joined_at' => $m->joined_at?->toISOString(),
            'left_at' => $m->left_at?->toISOString(),
        ]);

        return response()->json([
            'data' => $data,
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function addMember(Request $request, $teamId, $userId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $orgId = (int) $request->header('X-Organization-Id', 1);

        $existing = TeamMember::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'User is already a member of this team',
            ], 409)->header('X-Organization-Id', (string) $orgId);
        }

        $member = TeamMember::create([
            'team_id' => (int) $teamId,
            'user_id' => (int) $userId,
            'is_lead' => $request->boolean('is_lead', false),
            'joined_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'team_id' => $member->team_id,
                'user_id' => $member->user_id,
                'is_lead' => $member->is_lead,
                'joined_at' => $member->joined_at?->toISOString(),
            ],
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function removeMember(Request $request, $teamId, $userId): JsonResponse
    {
        $team = Team::findOrFail($teamId);

        $member = TeamMember::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'User is not a member of this team',
            ], 404)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
        }

        $member->update(['left_at' => now()]);

        return response()->json([
            'message' => 'Member removed',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    private function formatTeam($team): array
    {
        $membersCount = TeamMember::where('team_id', $team->id)
            ->whereNull('left_at')
            ->count();

        return [
            'id' => $team->id,
            'public_id' => $team->public_id,
            'organization_id' => $team->organization_id,
            'department_id' => $team->department_id,
            'code' => $team->code,
            'name' => $team->name,
            'members_count' => $membersCount,
            'manager_user' => $team->relationLoaded('managerUser') && $team->managerUser ? [
                'id' => $team->managerUser->id,
                'username' => $team->managerUser->username,
                'email' => $team->managerUser->email,
            ] : null,
            'is_active' => $team->is_active,
            'created_at' => $team->created_at?->toISOString(),
            'updated_at' => $team->updated_at?->toISOString(),
        ];
    }
}
