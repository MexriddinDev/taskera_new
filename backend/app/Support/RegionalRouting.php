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

    /**
     * Xodim/zayavka qaysi hududga yo'naltiriladi.
     *
     * Ikki bosqich:
     *  1. `office_support_routes` — ALOHIDA sozlangan istisno. Bitta filialni
     *     (BXM) o'z viloyatidan boshqasiga biriktirish kerak bo'lganda yoki
     *     unga aniq IT guruhi (`team_id`) ko'rsatilganda ishlatiladi.
     *  2. Viloyat local kodi — ASOSIY yo'l. `local_code` bankda viloyatni
     *     bildiradi (XM000 — Xorazm), shuning uchun har bir filial uchun
     *     alohida qator kiritish shart emas: 14 ta viloyat yozuvi yetadi.
     *
     * Ikkinchi bosqichda `team_id` bo'lmaydi — hudud aniq, lekin unga qaysi
     * guruh xizmat qilishi zayavkada tanlangan xizmat guruhi bilan belgilanadi
     * (qarang: `stamp()` izohi).
     */
    public static function route(int $orgId, ?string $bxm, ?string $local): ?object
    {
        if ($bxm !== null) {
            $office = DB::table('office_support_routes')->where('organization_id', $orgId)
                ->where('bxm_code', $bxm)->whereIn('local_code', array_unique([$local ?? '', '']))
                ->orderByRaw("CASE WHEN local_code = '' THEN 1 ELSE 0 END")
                ->first();

            if ($office) {
                return $office;
            }
        }

        if ($local === null) {
            return null;
        }

        $regionId = DB::table('regions')->where('organization_id', $orgId)
            ->where('local_code', $local)->whereNull('deleted_at')->where('is_active', true)
            ->value('id');

        return $regionId === null ? null : (object) ['region_id' => (int) $regionId, 'team_id' => null];
    }

    /**
     * Zayavkaga so'rovchining BXM/local kodi va hududini muhrlaydi.
     *
     * TANLANGAN GURUH O'ZGARTIRILMAYDI. Ilgari bu metod guruhni BXM
     * yo'nalishidagi viloyat IT guruhiga almashtirib qo'yardi — ya'ni
     * foydalanuvchi "Texnik guruh" deb tanlagani "Andijon IT bo'lim" ga
     * aylanardi va qaysi XIZMAT so'ralgani yo'qolardi. Endi guruh xizmatni,
     * hudud esa MUDDATNI (SLA) belgilaydi.
     */
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

        // BI guruhi — barcha hududlardan respublikaga: hisobot xizmati
        // viloyatlarga bo'linmaydi (talab: "BI faqat respublikaga").
        if ($selected?->republic_only) {
            $ticket->support_scope = 'republic';
        } elseif ($route?->region_id) {
            $ticket->support_scope = 'regional';
        } elseif ($ticket->bxm_code !== null && $route === null) {
            // Kod bor, lekin qaysi hududga tegishli ekani sozlanmagan.
            // Zayavka bazada qoladi va superadmin uni yo'naltiradi — lekin
            // guruh saqlanadi: qaysi xizmat so'ralgani ma'lumot sifatida kerak.
            $ticket->support_scope = 'unmapped';
        } else {
            $ticket->support_scope = 'republic';
        }

        // Tanlangan shablon guruhga ham, hududga ham mos kelishi kerak.
        if ($ticket->sla_rule_id && ! DB::table('sla_rules')->where('id', $ticket->sla_rule_id)
            ->where('organization_id', $ticket->organization_id)->where('team_id', $ticket->assigned_team_id)
            ->where(fn ($q) => $q->whereNull('region_id')->orWhere('region_id', $ticket->region_id))
            ->where('is_active', true)->whereNull('deleted_at')->exists()) {
            $ticket->sla_rule_id = null;
        }

        if (! $ticket->branch_id) {
            $ticket->branch_id = $user->employee?->branch_id
                ?? ($ticket->support_scope === 'republic' ? DB::table('branches')->where('id', 1)->value('id') : null);
        }
        if (! $ticket->region_id && $ticket->support_scope === 'republic') {
            $ticket->region_id = DB::table('regions')->where('id', 1)->value('id');
        }
    }

    /**
     * Xodim qaysi hududga tegishli.
     *
     * Avval o'zining BXM/local kodi bo'yicha, u sozlanmagan bo'lsa — qo'lda
     * ochilgan viloyat guruhiga a'zoligi bo'yicha. Ilgari viloyat qamrovi
     * FAQAT guruh a'zoligidan aniqlanardi; viloyat guruhlari endi avtomatik
     * yaratilmagani uchun bu yo'l o'zi yetarli emas.
     */
    public static function userRegionId(User $user): ?int
    {
        $route = self::route((int) $user->organization_id, ...array_values(self::identity($user)));
        if ($route?->region_id) {
            return (int) $route->region_id;
        }

        $fromTeam = DB::table('teams')->join('team_members', 'team_members.team_id', '=', 'teams.id')
            ->where('teams.organization_id', $user->organization_id)
            ->whereNotNull('teams.region_id')->whereNull('teams.deleted_at')->where('teams.is_active', true)
            ->where('team_members.user_id', $user->id)->whereNull('team_members.left_at')
            ->value('teams.region_id');

        return $fromTeam === null ? null : (int) $fromTeam;
    }

    /** Xodim a'zo bo'lgan viloyat guruhlari — qo'lda ochilganlari bo'lsa. */
    public static function regionalTeamIds(User $user): array
    {
        return DB::table('teams')->where('organization_id', $user->organization_id)
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
                        // Viloyat xodimi O'Z hududining zayavkalarini ko'radi —
                        // guruhdan qat'i nazar, chunki guruh endi xizmatni
                        // bildiradi (Texnik guruh, NOC), hududni emas.
                        $region = self::userRegionId($user);
                        $work->where('tickets.support_scope', 'regional')
                            ->when($region === null, fn ($q) => $q->whereRaw('1 = 0'))
                            ->when($region !== null, fn ($q) => $q->where('tickets.region_id', $region));
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
        if (! self::isRegional($user)) {
            return $ticket->support_scope === 'republic';
        }

        $region = self::userRegionId($user);

        return $region !== null
            && $ticket->support_scope === 'regional'
            && (int) $ticket->region_id === $region;
    }

    public static function visibleTeams($query, User $user): mixed
    {
        $query->where('teams.organization_id', $user->organization_id);
        if ($user->isSuperAdmin()) {
            return $query;
        }
        $identity = self::identity($user);
        $route = self::route((int) $user->organization_id, ...array_values($identity));
        return $query->where(function ($q) use ($route, $user) {
            // ASOSIY (respublika) guruhlar hammaga ko'rinadi: zayavkada
            // foydalanuvchi qaysi XIZMATNI so'rayotganini tanlaydi. Ilgari
            // BXM kodi sozlangan xodim faqat o'z viloyat guruhini ko'rardi va
            // "Texnik guruh"ni umuman tanlay olmasdi.
            $q->whereNull('teams.region_id');
            if ($route?->team_id) {
                $q->orWhere('teams.id', $route->team_id);
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
