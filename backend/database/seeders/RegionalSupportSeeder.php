<?php

namespace Database\Seeders;

use App\Models\Itms\SlaRule;
use App\Modules\Organization\Infrastructure\Eloquent\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            // Hududlar yaratiladi, GURUH esa yaratilMAYDI: zayavkada
            // foydalanuvchi qaysi XIZMATNI so'rayotganini tanlaydi (Texnik
            // guruh, NOC), viloyat esa faqat MUDDATNI belgilaydi. Ilgari bu
            // yerda har viloyatga IT guruhi ochilardi va ular zayavka
            // formasida asosiy guruhlarni siqib chiqarardi.
            //
            // Viloyat muddati mavjud respublika guruhlariga hudud kesimida
            // biriktiriladi — shuning uchun har (guruh + hudud) juftligiga
            // "Default holat" qoidasi tayyorlanadi.
            // BI guruhi chetda qoladi: hisobot xizmati viloyatlarga
            // bo'linmaydi va uning zayavkasi doim respublikaga boradi.
            $teamIds = DB::table('teams')->where('organization_id', $orgId)
                ->whereNull('region_id')->whereNull('deleted_at')
                ->where('republic_only', false)->pluck('id');

            foreach (self::REGIONS as $code => $name) {
                $region = Region::where('organization_id', $orgId)->where('name', $name)->first()
                    ?? Region::firstOrCreate(['organization_id' => $orgId, 'code' => $code], ['name' => $name, 'is_active' => true]);

                foreach ($teamIds as $teamId) {
                    SlaRule::ensureDefaultFor($orgId, (int) $teamId, (int) $region->id);
                    // Boshlang'ich nusxa: keyin viloyat qoidalari mustaqil yuritiladi.
                    SlaRule::copyRepublicTemplates($orgId, (int) $teamId, (int) $region->id);
                }
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
