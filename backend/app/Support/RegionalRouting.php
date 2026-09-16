<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Support\Facades\DB;

final class RegionalRouting
{
    public static function identity(User $user): array
    {
        $employee = $user->employee;
        $attrs = $employee?->attributes ?? [];
        $adCode = $employee ? DB::table('ad_accounts')->where('employee_id', $employee->id)
            ->orderByDesc('id')->value('bxm_code') : null;
        $branch = $employee?->branch;
        return [
            'bxm_code' => self::code($employee?->bxm_code ?? $attrs['bxm_code'] ?? $adCode ?? ($branch?->branch_type !== 'HEADQUARTERS' ? $branch?->code : null)),
            'local_code' => self::code($employee?->local_code ?? $attrs['local_code'] ?? null),
        ];
    }

    private static function code(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    public static function route(int $orgId, ?string $bxm, ?string $local): ?object
    {
        if ($bxm === null) {
            return null;
        }
        return DB::table('office_support_routes')->where('organization_id', $orgId)
            ->where('bxm_code', $bxm)->whereIn('local_code', array_unique([$local ?? '', '']))
            ->orderByRaw("CASE WHEN local_code = '' THEN 1 ELSE 0 END")
            ->first();
    }

    public static function stamp(Ticket $ticket): void
    {
        $user = User::where('organization_id', $ticket->organization_id)->find($ticket->requester_user_id);
        if (! $user) {
            return;
        }
        $identity = self::identity($user);
        $ticket->bxm_code = $identity['bxm_code'];
        $ticket->local_code = $identity['local_code'];
        $route = self::route((int) $ticket->organization_id, $ticket->bxm_code, $ticket->local_code);
        $selected = DB::table('teams')->where('organization_id', $ticket->organization_id)
            ->whereNull('deleted_at')->where('is_active', true)->where('id', $ticket->assigned_team_id)->first();
        $ticket->region_id = $route?->region_id;
        if ($selected?->republic_only && $selected->region_id === null) {
            $ticket->support_scope = 'republic';
        } elseif ($route) {
            $team = DB::table('teams')->where('id', $route->team_id)->where('organization_id', $ticket->organization_id)
                ->whereNull('deleted_at')->where('is_active', true)->first();
            $ticket->support_scope = $team ? ($route->region_id ? 'regional' : 'republic') : 'unmapped';
            $ticket->assigned_team_id = $team?->id;
        } elseif ($ticket->bxm_code !== null || $selected?->region_id) {
            // Keep the request in the database for superadmin to configure routing.
            $ticket->support_scope = 'unmapped';
            $ticket->assigned_team_id = null;
        } else {
            $ticket->support_scope = 'republic';
        }
        if ($ticket->sla_rule_id && ! DB::table('sla_rules')->where('id', $ticket->sla_rule_id)
            ->where('organization_id', $ticket->organization_id)->where('team_id', $ticket->assigned_team_id)
            ->where('is_active', true)->whereNull('deleted_at')->exists()) {
            $ticket->sla_rule_id = null;
        }
    }

    public static function regionalTeamIds(User $user): array
    {
        $identity = self::identity($user);
        $route = self::route((int) $user->organization_id, ...array_values($identity));
        if (! $route?->region_id) {
            return [];
        }
        return DB::table('teams')->where('organization_id', $user->organization_id)
            ->where('region_id', $route->region_id)
            ->whereNotNull('region_id')->whereNull('deleted_at')->where('is_active', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('team_members')
                ->whereColumn('team_members.team_id', 'teams.id')->where('user_id', $user->id)->whereNull('left_at'))
            ->pluck('id')->all();
    }

    public static function isRegional(User $user): bool
    {
        return in_array('regional support', $user->getRoleNames(), true)
            || in_array('regional admin', $user->getRoleNames(), true)
            || DB::table('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('teams.organization_id', $user->organization_id)->whereNotNull('teams.region_id')
                ->where('team_members.user_id', $user->id)->whereNull('team_members.left_at')->exists();
    }

    /** Applies to both Eloquent and query builders; an explicit user also works in bot jobs. */
    public static function constrain($query, ?User $user = null, bool $includeOwn = true): mixed
    {
        $user ??= auth()->user();
        if (! $user) {
            return $query;
        }
        $query->where('tickets.organization_id', $user->organization_id);
        if ($user->isSuperAdmin()) {
            return $query;
        }
        return $query->where(function ($q) use ($user, $includeOwn) {
            if ($includeOwn) {
                $q->where('tickets.requester_user_id', $user->id);
            } else {
                $q->whereRaw('1 = 0');
            }
            if ($user->isSupportStaff()) {
                $q->orWhere(function ($work) use ($user) {
                    if (self::isRegional($user)) {
                        $work->where('tickets.support_scope', 'regional')
                            ->whereIn('tickets.assigned_team_id', self::regionalTeamIds($user));
                    } else {
                        $work->where('tickets.support_scope', 'republic');
                    }
                });
            }
        });
    }

    public static function canWork(User $user, object $ticket): bool
    {
        if ((int) $user->organization_id !== (int) $ticket->organization_id || ! $user->isSupportStaff() || $user->status !== 'ACTIVE') {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        return self::isRegional($user)
            ? $ticket->support_scope === 'regional' && in_array((int) $ticket->assigned_team_id, self::regionalTeamIds($user))
            : $ticket->support_scope === 'republic';
    }

    public static function visibleTeams($query, User $user): mixed
    {
        $query->where('teams.organization_id', $user->organization_id);
        if ($user->isSuperAdmin()) {
            return $query;
        }
        $identity = self::identity($user);
        $route = self::route((int) $user->organization_id, ...array_values($identity));
        return $query->where(function ($q) use ($route, $identity, $user) {
            $q->where('teams.republic_only', true)->whereNull('teams.region_id');
            if ($route) {
                $q->orWhere('teams.id', $route->team_id);
            } elseif ($identity['bxm_code'] === null && ! self::isRegional($user)) {
                $q->orWhereNull('teams.region_id');
            }
            if ($user->isSupportStaff()) {
                $q->orWhereIn('teams.id', self::regionalTeamIds($user));
            }
        });
    }

    public static function visibleStaff($query, User $user): mixed
    {
        $query->where('users.organization_id', $user->organization_id)->where('users.status', 'ACTIVE');
        if ($user->isSuperAdmin()) {
            return $query;
        }
        if (self::isRegional($user)) {
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('team_members')
                ->whereColumn('team_members.user_id', 'users.id')->whereNull('left_at')
                ->whereIn('team_id', self::regionalTeamIds($user)));
        }
        return $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->whereColumn('team_members.user_id', 'users.id')->whereNull('left_at')->whereNotNull('teams.region_id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereColumn('model_has_roles.model_id', 'users.id')->whereIn('roles.name', ['Regional Support', 'Regional Admin']));
    }
}
