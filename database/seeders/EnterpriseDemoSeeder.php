<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Tizimning minimal ishga tushirish (bootstrap) seederi.
 *
 * Bu seeder AD ulanmagan vaqtinchalik rejim uchun kerakli minimal
 * ma'lumotlarni yaratadi:
 *   1. Bitta organizatsiya (mavjud bo'lmasa)
 *   2. Standart region / branch / position (org struktura poydevori)
 *   3. Ticket (zayavka) tizimi uchun ruxsatlar (permissions)
 *   4. Super Admin roli + super admin foydalanuvchi
 *   5. Namunaviy bo'lim -> bo'linma -> guruh ierarxiyasi (IT bo'limi)
 *
 * Barcha operatsiyalar idempotent (qayta ishga tushirsa dublikat yaratmaydi).
 */
class EnterpriseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $orgId = $this->ensureOrganization();
        [$regionId, $branchId, $positionId] = $this->ensureBaseStructure($orgId);
        $this->ensurePermissions();
        $superRoleId = $this->ensureSuperAdminRole($orgId);
        $this->ensureSuperAdminUser($orgId, $superRoleId);
        $this->ensureSampleDepartmentsAndTeams($orgId, $branchId);
    }

    private function ensureOrganization(): int
    {
        $org = DB::table('organizations')->first();
        if ($org) {
            return $org->id;
        }

        return DB::table('organizations')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'code' => 'MAIN',
            'name' => 'Main Organization',
            'is_active' => true,
            'settings' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0:int,1:int,2:int} [regionId, branchId, positionId]
     */
    private function ensureBaseStructure(int $orgId): array
    {
        $regionId = DB::table('regions')->where('organization_id', $orgId)->value('id');
        if (!$regionId) {
            $regionId = DB::table('regions')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'code' => 'HQ-REGION',
                'name' => 'Bosh boshqarma hududi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $branchId = DB::table('branches')->where('organization_id', $orgId)->value('id');
        if (!$branchId) {
            $branchId = DB::table('branches')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'region_id' => $regionId,
                'code' => 'HQ',
                'name' => 'Bosh ofis',
                'branch_type' => 'HEADQUARTERS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $positionId = DB::table('positions')->where('organization_id', $orgId)->value('id');
        if (!$positionId) {
            $positionId = DB::table('positions')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'code' => 'STAFF',
                'name' => 'Xodim',
                'is_managerial' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$regionId, $branchId, $positionId];
    }

    /**
     * Zayavka (ticket) tizimi uchun ruxsatlar ro'yxati.
     * Super Admin ularning barchasiga ega bo'ladi; boshqa rollarga
     * super admin panel orqali dinamik biriktiriladi.
     */
    private function ensurePermissions(): void
    {
        $permissions = [
            ['name' => 'tickets.view', 'module' => 'TICKETS', 'description' => 'Guruhga kelgan zayavkalarni ko\'rish'],
            ['name' => 'tickets.create', 'module' => 'TICKETS', 'description' => 'Yangi zayavka yuborish'],
            ['name' => 'tickets.assign', 'module' => 'TICKETS', 'description' => 'Zayavkani qabul qilish / biriktirish'],
            ['name' => 'tickets.transition', 'module' => 'TICKETS', 'description' => 'Zayavka holatini o\'zgartirish (yopish, qaytarish)'],
            ['name' => 'tickets.view_own', 'module' => 'TICKETS', 'description' => 'Faqat o\'z zayavkalarini ko\'rish'],
            ['name' => 'roles.manage', 'module' => 'RBAC', 'description' => 'Rollar va ruxsatlarni boshqarish'],
            ['name' => 'departments.manage', 'module' => 'ORG', 'description' => 'Bo\'lim, bo\'linma va guruhlarni boshqarish'],
            ['name' => 'stats.view', 'module' => 'ANALYTICS', 'description' => 'Statistika va monitoringni ko\'rish'],
        ];

        foreach ($permissions as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $p['name'], 'guard_name' => 'web'],
                [
                    'module' => $p['module'],
                    'description' => $p['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function ensureSuperAdminRole(int $orgId): int
    {
        $role = DB::table('roles')
            ->where('organization_id', $orgId)
            ->whereRaw('LOWER(name) = ?', ['super admin'])
            ->first();

        if ($role) {
            $roleId = $role->id;
        } else {
            $roleId = DB::table('roles')->insertGetId([
                'organization_id' => $orgId,
                'name' => 'Super Admin',
                'guard_name' => 'web',
                'description' => 'Tizimning to\'liq huquqli administratori',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Super Admin ga barcha ruxsatlarni biriktiramiz.
        $permissionIds = DB::table('permissions')->pluck('id');
        foreach ($permissionIds as $pid) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $pid],
                []
            );
        }

        return $roleId;
    }

    private function ensureSuperAdminUser(int $orgId, int $superRoleId): void
    {
        $username = 'superadmin';

        $user = DB::table('users')->where('username', $username)->first();

        if (!$user) {
            $userId = DB::table('users')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'employee_id' => null,
                'username' => $username,
                'email' => 'superadmin@company.uz',
                'password' => Hash::make('superadmin'),
                'auth_source' => 'LOCAL',
                'status' => 'ACTIVE',
                'mfa_required' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $user->id;
        }

        // Super Admin rolini biriktiramiz (idempotent).
        $modelType = 'App\\Models\\User';
        DB::table('model_has_roles')->where('model_id', $userId)->delete();
        DB::table('model_has_roles')->insert([
            'role_id' => $superRoleId,
            'model_type' => $modelType,
            'model_id' => $userId,
            'organization_id' => $orgId,
        ]);
    }

    /**
     * Namunaviy tashkiliy ierarxiya (dinamik boshlanish nuqtasi):
     *
     *   IT Departament (bo'lim)
     *     ├── Texnik xatoliklar bo'linmasi (parent department)
     *     │      ├── Texnik guruh                 (team)
     *     │      └── NOC monitoring guruhi         (team)
     *     └── Dasturiy xatoliklar bo'linmasi (parent department)
     *            ├── Backend dasturchilar guruhi   (team)
     *            └── Frontend dasturchilar guruhi  (team)
     *
     * Super admin bularni keyinchalik UI orqali qo'shishi/o'chirishi mumkin —
     * bu faqat bo'sh tizimni ko'rsatib turish uchun boshlang'ich namuna.
     */
    private function ensureSampleDepartmentsAndTeams(int $orgId, int $branchId): void
    {
        // Agar allaqachon bo'lim mavjud bo'lsa, namunani takrorlamaymiz.
        if (DB::table('departments')->where('organization_id', $orgId)->exists()) {
            return;
        }

        $itDeptId = DB::table('departments')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'branch_id' => $branchId,
            'parent_id' => null,
            'code' => 'IT',
            'name' => 'IT Departament',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $divisions = [
            'TECH' => 'Texnik xatoliklar bo\'linmasi',
            'SOFT' => 'Dasturiy xatoliklar bo\'linmasi',
        ];
        $divisionIds = [];
        foreach ($divisions as $code => $name) {
            $divisionIds[$code] = DB::table('departments')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'branch_id' => $branchId,
                'parent_id' => $itDeptId,
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $teams = [
            ['code' => 'TECH-SUP', 'name' => 'Texnik guruh', 'dept' => 'TECH'],
            ['code' => 'NOC', 'name' => 'NOC monitoring guruhi', 'dept' => 'TECH'],
            ['code' => 'BACKEND', 'name' => 'Backend dasturchilar guruhi', 'dept' => 'SOFT'],
            ['code' => 'FRONTEND', 'name' => 'Frontend dasturchilar guruhi', 'dept' => 'SOFT'],
        ];
        foreach ($teams as $t) {
            DB::table('teams')->insert([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'department_id' => $divisionIds[$t['dept']],
                'code' => $t['code'],
                'name' => $t['name'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
