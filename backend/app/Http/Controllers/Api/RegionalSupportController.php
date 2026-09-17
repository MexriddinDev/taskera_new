<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organization\Infrastructure\Eloquent\Team;
use App\Modules\Ticketing\Domain\Services\TicketSlaService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Support\CurrentOrg;
use App\Support\RegionalRouting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class RegionalSupportController extends Controller
{
    private function org(Request $request): int
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        return CurrentOrg::id($request);
    }

    public function index(Request $request)
    {
        $org = $this->org($request);
        return response()->json([
            'regions' => DB::table('regions')->where('organization_id', $org)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'local_code']),
            // Filiallar ma'lumotnomasi qo'lda yuritilmaydi: xodim kirganda HR
            // xizmatidan kelgan kod bo'yicha o'zi qo'shiladi
            // (AdUserProvisionService::resolveBranchId). Bu yerda ular viloyat
            // bo'yicha guruhlash uchun `region_id` bilan beriladi.
            'branches' => DB::table('branches')->where('organization_id', $org)->whereNull('deleted_at')
                ->orderBy('code')->get(['id', 'code', 'name', 'region_id', 'branch_type', 'is_active']),
            'teams' => Team::where('organization_id', $org)->orderBy('name')->get(['id', 'name', 'region_id', 'republic_only', 'is_active']),
            'routes' => DB::table('office_support_routes')->where('organization_id', $org)->orderBy('bxm_code')->orderBy('local_code')->get(),
            'members' => DB::table('team_members as m')->join('teams as t', 't.id', '=', 'm.team_id')->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('t.organization_id', $org)->whereNotNull('t.region_id')->whereNull('m.left_at')->whereNull('t.deleted_at')
                ->get(['m.team_id', 'm.user_id', 'm.is_lead', 'u.username']),
            'users' => DB::table('users as u')->leftJoin('employees as e', 'e.id', '=', 'u.employee_id')
                ->where('u.organization_id', $org)->whereNull('u.deleted_at')->where('u.status', 'ACTIVE')
                ->orderBy('u.username')->get(['u.id', 'u.username', 'e.first_name as firstName', 'e.last_name as lastName', 'e.bxm_code', 'e.local_code']),
            'unmapped_count' => Ticket::where('support_scope', 'unmapped')->count(),
            'unmapped' => Ticket::where('support_scope', 'unmapped')->orderByDesc('id')->limit(100)->get(['id', 'ticket_no', 'subject', 'bxm_code', 'local_code']),
        ]);
    }

    public function initialize(Request $request)
    {
        app(\Database\Seeders\RegionalSupportSeeder::class)->seedOrganization($this->org($request));
        return $this->index($request);
    }

    public function saveRoute(Request $request, ?int $id = null)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'bxm_code' => ['required', 'string', 'max:32'],
            'local_code' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            // Marshrut HUDUDni belgilaydi — zayavkaning muddati va uni kim
            // bajarishi shundan chiqadi. IT guruhi ixtiyoriy: u faqat admin
            // qo'lda viloyat guruhi ochgan bo'lsa ko'rsatiladi.
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')->where('organization_id', $org)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('organization_id', $org)->whereNull('deleted_at')->where('is_active', true)],
        ]);
        $data['local_code'] = trim($data['local_code'] ?? '');
        $data['bxm_code'] = trim($data['bxm_code']);
        $data['team_id'] = $data['team_id'] ?? null;

        if ($data['team_id'] !== null) {
            $team = Team::findOrFail($data['team_id']);
            abort_if($team->republic_only, 422, 'BI guruhiga ofis biriktirilmaydi.');
            abort_if((int) $team->region_id !== (int) $data['region_id'], 422, 'Guruh tanlangan hududga tegishli emas.');
        }
        $duplicate = DB::table('office_support_routes')->where('organization_id', $org)
            ->where('bxm_code', $data['bxm_code'])->where('local_code', $data['local_code'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
        abort_if($duplicate, 422, 'Bu BXM/local kod allaqachon biriktirilgan.');
        // All offices under one BXM must belong to the same region.
        $otherRegions = DB::table('office_support_routes')->where('organization_id', $org)->where('bxm_code', $data['bxm_code'])
            ->when($id, fn ($q) => $q->where('id', '!=', $id))->pluck('region_id');
        abort_if($otherRegions->contains(fn ($region) => (int) $region !== (int) $data['region_id']), 422, 'Bitta BXM turli hududlarga biriktirilmaydi.');
        if ($id) {
            abort_unless(DB::table('office_support_routes')->where('organization_id', $org)->where('id', $id)->exists(), 404);
            DB::table('office_support_routes')->where('organization_id', $org)->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            $id = DB::table('office_support_routes')->insertGetId($data + ['organization_id' => $org, 'created_at' => now(), 'updated_at' => now()]);
        }
        return response()->json(['id' => $id], 200);
    }

    public function saveIdentity(Request $request, int $userId)
    {
        $org = $this->org($request);
        $data = $request->validate(['bxm_code' => ['required', 'string', 'max:32'], 'local_code' => ['nullable', 'string', 'max:32']]);
        $user = User::where('organization_id', $org)->findOrFail($userId);
        abort_unless($user->employee && (int) $user->employee->organization_id === $org, 422, 'Xodim kartochkasi mavjud emas.');
        $user->employee->update($data);
        return response()->json(['message' => 'Xodimning BXM va local kodi saqlandi.']);
    }

    public function saveMember(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('organization_id', $org)->whereNotNull('region_id')->whereNull('deleted_at')],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('organization_id', $org)->where('status', 'ACTIVE')],
            'role' => ['required', Rule::in(['Regional Support', 'Regional Admin'])],
        ]);
        $user = User::findOrFail($data['user_id']);
        abort_if($user->isSuperAdmin(), 422, 'Superadmin uchun hududiy rol talab qilinmaydi.');
        $team = Team::findOrFail($data['team_id']);
        $identity = RegionalRouting::identity($user);
        $route = RegionalRouting::route($org, ...array_values($identity));
        abort_unless($route && (int) $route->region_id === (int) $team->region_id, 422, 'Xodimning BXM/local kodini avval shu hududga biriktiring.');
        DB::transaction(function () use ($org, $data, $user) {
            $roleId = DB::table('roles')->where('organization_id', $org)->where('name', $data['role'])->value('id');
            abort_unless($roleId, 422, 'Avval hududlarni yarating.');
            $regionalRoles = DB::table('roles')->where('organization_id', $org)->whereIn('name', ['Regional Support', 'Regional Admin'])->pluck('id');
            DB::table('model_has_roles')->where('model_id', $user->id)->whereIn('role_id', $regionalRoles)->delete();
            DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_id' => $user->id, 'model_type' => User::class, 'organization_id' => $org]);
            DB::table('team_members')->updateOrInsert(['team_id' => $data['team_id'], 'user_id' => $user->id], [
                'is_lead' => $data['role'] === 'Regional Admin', 'joined_at' => now(), 'left_at' => null,
            ]);
        });
        $user->clearPermissionsCache();
        return response()->json(['message' => 'Xodim va hududiy rol biriktirildi.']);
    }

    public function removeMember(Request $request, int $teamId, int $userId)
    {
        $org = $this->org($request);
        Team::where('organization_id', $org)->whereNotNull('region_id')->findOrFail($teamId);
        DB::table('team_members')->where('team_id', $teamId)->where('user_id', $userId)->update(['left_at' => now()]);
        return response()->json(['message' => 'Biriktirish bekor qilindi.']);
    }

    public function reroute(Request $request, int $id)
    {
        $org = $this->org($request);
        $ticket = DB::transaction(function () use ($org, $id) {
            $ticket = Ticket::where('organization_id', $org)->lockForUpdate()->findOrFail($id);
            abort_unless($ticket->support_scope === 'unmapped' && ! $ticket->assigned_user_id, 422);
            $route = RegionalRouting::route($org, $ticket->bxm_code, $ticket->local_code);
            abort_unless($route, 422, 'BXM/local kod biriktirilmagan.');
            // Tanlangan XIZMAT guruhi o'zgarmaydi — yo'naltirish faqat hududni
            // (ya'ni muddatni va kim bajarishini) aniqlaydi.
            $ticket->update(['region_id' => $route->region_id,
                'support_scope' => $route->region_id ? 'regional' : 'republic']);
            return $ticket;
        });
        event(new \App\Modules\Ticketing\Domain\Events\TicketCreated($ticket));
        return response()->json(['message' => 'Zayavka tegishli IT bo‘limga yo‘naltirildi.']);
    }

    /**
     * Filial kesimida bajarilish ko'rsatkichlari.
     *
     * Zayavka qaysi filialdan kelgani `tickets.bxm_code` da muhrlangan
     * (`RegionalRouting::stamp`), filial esa viloyatga bog'langan — shu ikki
     * bog'lanish orqali "viloyat → filiallar → zayavkalar" ko'rinishi chiqadi.
     *
     * SLA buzilishi ayni manbadan (`TicketSlaService`) hisoblanadi: bir
     * ekrandagi ikki raqam boshqa-boshqa chiqib qolmasin.
     */
    public function branchStats(Request $request)
    {
        abort_unless($request->user()->isSupportStaff(), 403);
        $org = CurrentOrg::id($request);

        $branches = DB::table('branches')->where('organization_id', $org)->whereNull('deleted_at')
            ->get(['id', 'code', 'name', 'region_id', 'branch_type']);
        $branchesByCode = $branches->keyBy('code');
        $branchesById = $branches->keyBy('id');
        $hqBranch = $branches->firstWhere('branch_type', 'HEADQUARTERS')
            ?? $branches->firstWhere('code', 'HQ')
            ?? $branches->first();

        $regions = DB::table('regions')->where('organization_id', $org)->whereNull('deleted_at')
            ->get(['id', 'name', 'local_code'])->keyBy('id');

        $userBranches = DB::table('users')
            ->join('employees', 'users.employee_id', '=', 'employees.id')
            ->where('users.organization_id', $org)
            ->whereNotNull('employees.branch_id')
            ->pluck('employees.branch_id', 'users.id');

        $sla = app(TicketSlaService::class);
        $tickets = Ticket::where('organization_id', $org)->whereNull('deleted_at')->get();

        $grouped = $tickets->groupBy(function ($t) use ($branchesByCode, $branchesById, $userBranches, $hqBranch) {
            if ($t->bxm_code && $branchesByCode->has($t->bxm_code)) {
                return 'branch:'.$branchesByCode->get($t->bxm_code)->id;
            }
            if ($t->branch_id && $branchesById->has($t->branch_id)) {
                return 'branch:'.$t->branch_id;
            }
            if ($t->requester_user_id && ($empBranchId = $userBranches->get($t->requester_user_id))) {
                return 'branch:'.$empBranchId;
            }
            if ($t->support_scope === 'republic' || $t->support_scope === null) {
                return $hqBranch ? 'branch:'.$hqBranch->id : 'republic';
            }
            return 'unmapped';
        });

        $rows = [];

        foreach ($grouped as $groupKey => $group) {
            $branch = null;
            if (str_starts_with($groupKey, 'branch:')) {
                $branch = $branchesById->get((int) substr($groupKey, 7));
            } elseif ($groupKey === 'republic') {
                $branch = $hqBranch;
            }

            $isHqOrRepublic = ($branch && $branch->id === ($hqBranch?->id ?? 1)) || $groupKey === 'republic';
            $region = $branch ? $regions->get($branch->region_id) : null;
            if (! $region && $isHqOrRepublic) {
                $region = $regions->get(1);
            }

            $regionName = $isHqOrRepublic ? 'Respublika' : ($region?->name ?? 'Biriktirilmagan');
            $branchName = $branch?->name ?? ($isHqOrRepublic ? 'Bosh ofis' : "Noma'lum filial");
            $branchCode = $branch?->code ?? ($isHqOrRepublic ? 'HQ' : null);
            $regionId = $isHqOrRepublic ? ($region?->id ?? 1) : $region?->id;
            $localCode = $region?->local_code ?? ($isHqOrRepublic ? '00000' : null);

            $breached = array_sum(array_column($sla->breachStatsByTeam($group), 'breached'));

            $rows[] = [
                'region_id' => $regionId,
                'region_name' => $regionName,
                'local_code' => $localCode,
                'branch_code' => $branchCode,
                'branch_name' => $branchName,
                'total' => $group->count(),
                'open' => $group->whereIn('status_id', [1, 2, 3])->count(),
                'completed' => $group->whereIn('status_id', [7, 8])->count(),
                'breached' => $breached,
                'sla_percent' => round(($group->count() - $breached) / $group->count() * 100, 1),
            ];
        }

        // Barcha 14 ta viloyat va ularning filiallari / IT bo'limlari jadvalda to'liq ko'rinishi shart
        $coveredRegionIds = [];
        foreach ($rows as $r) {
            if ($r['region_id'] !== null) {
                $coveredRegionIds[(int) $r['region_id']] = true;
            }
        }

        $regionalTeams = DB::table('teams')->where('organization_id', $org)
            ->whereNotNull('region_id')->whereNull('deleted_at')->get()->keyBy('region_id');

        foreach ($regions as $reg) {
            $regId = (int) $reg->id;
            $regBranches = $branches->where('region_id', $regId);

            if ($regBranches->isNotEmpty()) {
                foreach ($regBranches as $b) {
                    $alreadyInRows = false;
                    foreach ($rows as $r) {
                        if ($r['branch_code'] === $b->code) {
                            $alreadyInRows = true;
                            break;
                        }
                    }
                    if (! $alreadyInRows) {
                        $rows[] = [
                            'region_id' => $regId,
                            'region_name' => $regId === 1 ? 'Respublika' : $reg->name,
                            'local_code' => $reg->local_code ?? ($regId === 1 ? '00000' : null),
                            'branch_code' => $b->code,
                            'branch_name' => $b->name,
                            'total' => 0,
                            'open' => 0,
                            'completed' => 0,
                            'breached' => 0,
                            'sla_percent' => 100.0,
                        ];
                    }
                }
            } elseif (! isset($coveredRegionIds[$regId])) {
                // Viloyatda alohida filial bo'lmasa, viloyatning IT bo'limi chiqadi
                $team = $regionalTeams->get($regId);
                $rows[] = [
                    'region_id' => $regId,
                    'region_name' => $regId === 1 ? 'Respublika' : $reg->name,
                    'local_code' => $reg->local_code ?? ($regId === 1 ? '00000' : null),
                    'branch_code' => $reg->local_code ?? ($regId === 1 ? 'HQ' : null),
                    'branch_name' => $team?->name ?? ($reg->name." IT bo'limi"),
                    'total' => 0,
                    'open' => 0,
                    'completed' => 0,
                    'breached' => 0,
                    'sla_percent' => 100.0,
                ];
            }
        }

        usort($rows, static function (array $a, array $b) {
            if ($a['region_id'] === 1 && $b['region_id'] !== 1) return -1;
            if ($b['region_id'] === 1 && $a['region_id'] !== 1) return 1;
            if ($a['region_id'] === 1 && $b['region_id'] === 1) {
                if ($a['branch_code'] === 'HQ') return -1;
                if ($b['branch_code'] === 'HQ') return 1;
            }
            return [$a['region_name'], (string) $a['branch_code']]
                <=> [$b['region_name'], (string) $b['branch_code']];
        });

        return response()->json([
            'data' => $rows,
            'regions' => $regions->values()->map(fn ($r) => ['id' => (int) $r->id, 'name' => (int) $r->id === 1 ? 'Respublika' : $r->name]),
        ]);
    }

    public function stats(Request $request)
    {
        abort_unless($request->user()->isSupportStaff(), 403);
        $tickets = Ticket::query()->get();
        $sla = app(TicketSlaService::class);
        return response()->json(['data' => $tickets->groupBy(fn ($t) => $t->support_scope === 'regional' ? (string) $t->region_id : $t->support_scope)
            ->map(function ($group, $key) use ($sla) {
                $stats = $sla->breachStatsByTeam($group);
                $breached = array_sum(array_column($stats, 'breached'));
                return ['scope' => $group->first()->support_scope, 'region_id' => $group->first()->support_scope === 'regional' ? (int) $key : null,
                    'name' => is_numeric($key) ? DB::table('regions')->where('id', $key)->value('name') : ($key === 'republic' ? 'Respublika' : 'Biriktirilmagan'),
                    'total' => $group->count(), 'open' => $group->whereIn('status_id', [1, 2, 3])->count(),
                    'completed' => $group->whereIn('status_id', [7, 8])->count(), 'breached' => $breached,
                    'sla_percent' => round(($group->count() - $breached) / $group->count() * 100, 1)];
            })->values()]);
    }
}
