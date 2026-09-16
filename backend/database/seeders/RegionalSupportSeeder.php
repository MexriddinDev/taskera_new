<?php

namespace Database\Seeders;

use App\Models\Itms\SlaRule;
use App\Modules\Organization\Infrastructure\Eloquent\Region;
use App\Modules\Organization\Infrastructure\Eloquent\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionalSupportSeeder extends Seeder
{
    public const REGIONS = [
        'UZ-AN' => 'Andijon viloyati', 'UZ-BU' => 'Buxoro viloyati',
        'UZ-FA' => 'Fargʻona viloyati', 'UZ-JI' => 'Jizzax viloyati',
        'UZ-NG' => 'Namangan viloyati', 'UZ-NW' => 'Navoiy viloyati',
        'UZ-QA' => 'Qashqadaryo viloyati', 'UZ-SA' => 'Samarqand viloyati',
        'UZ-SI' => 'Sirdaryo viloyati', 'UZ-SU' => 'Surxondaryo viloyati',
        'UZ-TO' => 'Toshkent viloyati', 'UZ-XO' => 'Xorazm viloyati',
        'UZ-TK' => 'Toshkent shahri', 'UZ-QR' => 'Qoraqalpogʻiston Respublikasi',
    ];

    public function run(): void
    {
        foreach (DB::table('organizations')->whereNull('deleted_at')->pluck('id') as $orgId) {
            $this->seedOrganization((int) $orgId);
        }
    }

    public function seedOrganization(int $orgId): void
    {
        DB::transaction(function () use ($orgId) {
            foreach (self::REGIONS as $code => $name) {
                $region = Region::where('organization_id', $orgId)->where('name', $name)->first()
                    ?? Region::firstOrCreate(['organization_id' => $orgId, 'code' => $code], ['name' => $name, 'is_active' => true]);
                $team = Team::firstOrCreate(['organization_id' => $orgId, 'code' => $code.'-IT-1'], [
                    'name' => $name.' — IT bo‘lim', 'region_id' => $region->id, 'is_active' => true,
                ]);
                SlaRule::ensureDefaultFor($orgId, (int) $team->id);
            }
            foreach (['Regional Support' => ['tickets.view', 'tickets.view_own', 'tickets.create', 'tickets.transition'],
                'Regional Admin' => ['tickets.view', 'tickets.view_own', 'tickets.create', 'tickets.transition', 'tickets.assign']] as $name => $permissions) {
                $roleId = DB::table('roles')->where('organization_id', $orgId)->where('name', $name)->where('guard_name', 'web')->value('id');
                $roleId ??= DB::table('roles')->insertGetId(['organization_id' => $orgId, 'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
                foreach ($permissions as $permission) {
                    $permissionId = DB::table('permissions')->where('name', $permission)->where('guard_name', 'web')->value('id');
                    $permissionId ??= DB::table('permissions')->insertGetId(['name' => $permission, 'guard_name' => 'web', 'module' => 'TICKETING', 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
                foreach (['my_tasks.view', 'monitoring.view'] as $permission) {
                    $permissionId = DB::table('permissions')->where('name', $permission)->where('guard_name', 'web')->value('id');
                    $permissionId ??= DB::table('permissions')->insertGetId(['name' => $permission, 'guard_name' => 'web', 'module' => 'TICKETING', 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
                \App\Models\User::forgetPermissionsCacheForRole((int) $roleId);
            }
        });
    }
}
