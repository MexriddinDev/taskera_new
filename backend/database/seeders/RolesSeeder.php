<?php

namespace Database\Seeders;

use App\Support\CurrentOrg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tizimning 5 ta asosiy roli va ularning huquqlari.
 *
 *   super admin — hamma narsa
 *   admin       — rollar va foydalanuvchilarni boshqarishdan tashqari hammasi
 *   spectator   — faqat kuzatuv: monitoring va statistika
 *   support     — zayavkalarni olish va bajarish
 *   user        — zayavka yuborish
 *
 * Seeder IDEMPOTENT: qayta-qayta ishga tushirsa bo'ladi. Rol huquqlari har
 * safar shu yerdagi ro'yxatga TENGLASHTIRILADI — ya'ni qo'lda qo'shilgan
 * ortiqcha huquq olib tashlanadi. Foydalanuvchilarning rollariga TEGILMAYDI.
 *
 * DIQQAT: 'Super Admin' nomi aynan shu ko'rinishda saqlanadi — frontend
 * (useCan, Navbar) va User::isSuperAdmin() shu nomga tayanadi.
 */
class RolesSeeder extends Seeder
{
    /** Rol nomi => huquqlar ro'yxati. `['*']` — barcha huquqlar. */
    private const ROLE_PERMISSIONS = [
        'Super Admin' => ['*'],

        // Rollar va foydalanuvchilarni boshqarishdan tashqari hammasi
        'admin' => ['* except' => ['roles.manage', 'users.manage']],

        // Faqat kuzatuv. Zayavka huquqlari BERILMAYDI — aks holda
        // User::isSupportStaff() uni xodim deb hisoblab, zayavka navbatini ochib qo'yardi.
        'spectator' => [
            'monitoring.view',
            'stats.view',
        ],

        // Zayavkalarni olish va bajarish.
        // `tickets.view_own` ataylab yo'q: support xodim boshqalarning
        // zayavkalari bilan ishlaydi, "Zayavkalarim" bo'limi unga kerak emas.
        // Kerak bo'lsa RBAC dan qo'shib qo'yiladi.
        // `dashboard.view` support uchun ham ochiq: u boshqaruv panelidagi
        // umumiy zayavkalar ko'rinishi va navbat bilan ishlaydi.
        // ITSM bo'limi huquqlari (`knowledge.view`, `catalog.view`,
        // `approvals.view`, `assets.view`, `problems.view`, `changes.view`)
        // ham ataylab yo'q — support uchun bu bo'lim yopiq.
        'support' => [
            'dashboard.view',
            'tasks.view',
            'my_tasks.view',
            'tickets.view',
            'tickets.create',
            // `tickets.assign` ATAYLAB yo'q: support xodim navbatdan egasiz
            // zayavkani o'ziga oladi (buning uchun `tickets.transition`
            // yetarli), lekin ishni boshqa xodimga taqsimlay olmaydi — bu
            // dispetcher/admin amali.
            'tickets.transition',
            'tickets.export',
            'support_panel.view',
        ],

        // Faqat zayavka yuborish va o'zinikini ko'rish.
        // `knowledge.view` yo'q: ITSM bo'limi oddiy foydalanuvchiga ko'rinmaydi.
        'user' => [
            'tickets.create',
            'tickets.view_own',
        ],
    ];

    /** Eski nom => yangi nom. Nomi o'zgargan rollar qayta yaratilmaydi. */
    private const RENAMES = [
        'standard user' => 'user',
    ];

    public function run(): void
    {
        $orgId = CurrentOrg::id();

        $this->applyRenames($orgId);

        $allPermissions = DB::table('permissions')->pluck('id', 'name')->toArray();

        if ($allPermissions === []) {
            $this->command?->warn('permissions jadvali bo\'sh — avval PermissionsSeeder ni ishga tushiring.');

            return;
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $spec) {
            $roleId = $this->ensureRole($orgId, $roleName);
            $names = $this->resolvePermissionNames($spec, array_keys($allPermissions));

            $this->syncRolePermissions($roleId, $names, $allPermissions);

            $this->command?->info(sprintf('%-12s -> %d ta huquq', $roleName, count($names)));
        }

        $this->assignDefaultRoleToRoleless($orgId);

        // Huquqlar keshlangan (User::getAllPermissions 10 daqiqa) — tozalanmasa
        // foydalanuvchilar eski huquqlar bilan yurib qoladi.
        $this->flushPermissionCache();
    }

    /**
     * Rolsiz qolgan foydalanuvchilarga 'user' rolini beradi.
     *
     * Rolsiz foydalanuvchi tushunchasi olib tashlandi — har kimda rol bo'lishi
     * kerak. Yangi kelganlar uchun bu AdUserProvisionService da ham ta'minlangan.
     */
    private function assignDefaultRoleToRoleless(int $orgId): void
    {
        $defaultRoleId = DB::table('roles')
            ->where('organization_id', $orgId)
            ->whereRaw('LOWER(name) = ?', ['user'])
            ->value('id');

        if (! $defaultRoleId) {
            return;
        }

        $modelType = \App\Models\User::class;

        $roleless = DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotIn('id', DB::table('model_has_roles')->where('model_type', $modelType)->pluck('model_id'))
            ->pluck('id');

        foreach ($roleless as $userId) {
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $defaultRoleId,
                    'model_type' => $modelType,
                    'model_id' => $userId,
                    'organization_id' => $orgId,
                ],
                []
            );
        }

        if (count($roleless) > 0) {
            $this->command?->info(sprintf("rolsiz %d foydalanuvchiga 'user' roli berildi", count($roleless)));
        }
    }

    private function applyRenames(int $orgId): void
    {
        foreach (self::RENAMES as $from => $to) {
            $old = DB::table('roles')
                ->where('organization_id', $orgId)
                ->whereRaw('LOWER(name) = ?', [$from])
                ->first();

            if (! $old) {
                continue;
            }

            $conflict = DB::table('roles')
                ->where('organization_id', $orgId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($to)])
                ->where('id', '<>', $old->id)
                ->exists();

            if ($conflict) {
                continue;
            }

            DB::table('roles')->where('id', $old->id)->update(['name' => $to, 'updated_at' => now()]);
            $this->command?->info("rol nomi o'zgartirildi: {$old->name} -> {$to}");
        }
    }

    private function ensureRole(int $orgId, string $name): int
    {
        $existing = DB::table('roles')
            ->where('organization_id', $orgId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('roles')->insertGetId([
            'organization_id' => $orgId,
            'name' => $name,
            'guard_name' => 'web',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $spec
     * @param  string[]  $all
     * @return string[]
     */
    private function resolvePermissionNames(array $spec, array $all): array
    {
        if ($spec === ['*']) {
            return $all;
        }

        if (isset($spec['* except'])) {
            return array_values(array_diff($all, (array) $spec['* except']));
        }

        return array_values(array_intersect($all, $spec));
    }

    /**
     * @param  string[]  $names
     * @param  array<string, int>  $allPermissions
     */
    private function syncRolePermissions(int $roleId, array $names, array $allPermissions): void
    {
        $wantIds = [];
        foreach ($names as $name) {
            if (isset($allPermissions[$name])) {
                $wantIds[] = (int) $allPermissions[$name];
            }
        }

        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->when($wantIds !== [], fn ($q) => $q->whereNotIn('permission_id', $wantIds))
            ->delete();

        foreach ($wantIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                []
            );
        }
    }

    private function flushPermissionCache(): void
    {
        foreach (DB::table('users')->pluck('id') as $id) {
            \Illuminate\Support\Facades\Cache::forget("user_permissions_{$id}");
        }
    }
}
