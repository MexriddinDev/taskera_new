<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Re-seed permissions: Clear old permissions safely
        $systemPermissions = [
            // TICKETS
            ['name' => 'tickets.view', 'module' => 'TICKETS', 'description' => 'Barcha zayavkalarni va topshiriqlarni ko\'rish'],
            ['name' => 'tickets.create', 'module' => 'TICKETS', 'description' => 'Yangi zayavka va murojaat yaratish'],
            ['name' => 'tickets.assign', 'module' => 'TICKETS', 'description' => 'Zayavkani ijrochiga / xodimga biriktirish'],
            ['name' => 'tickets.transition', 'module' => 'TICKETS', 'description' => 'Zayavka holatini o\'zgartirish (yopish, ijro etish)'],
            ['name' => 'tickets.delete', 'module' => 'TICKETS', 'description' => 'Zayavkalarni o\'chirish'],
            ['name' => 'tickets.view_own', 'module' => 'TICKETS', 'description' => 'Faqat o\'ziga tegishli zayavkalarni ko\'rish'],
            
            // RBAC & SECURITY
            ['name' => 'roles.manage', 'module' => 'RBAC', 'description' => 'Rollar, permissionlar va xodimlarga huquq biriktirish'],
            
            // ORGANIZATION & STRUCTURE
            ['name' => 'departments.manage', 'module' => 'ORG', 'description' => 'Bo\'limlar, filiallar va xizmat guruhlarini boshqarish'],
            
            // MONITORING & ANALYTICS
            ['name' => 'stats.view', 'module' => 'ANALYTICS', 'description' => 'Statistika va ijro intizomi monitoringini ko\'rish'],
            
            // KNOWLEDGE BASE
            ['name' => 'knowledge.view', 'module' => 'KNOWLEDGE', 'description' => 'Bilimlar bazasi va ko\'rsatmalarni ko\'rish'],
            ['name' => 'knowledge.manage', 'module' => 'KNOWLEDGE', 'description' => 'Maqolalar yaratish va nashr etish'],
            
            // ASSET & CMDB
            ['name' => 'assets.view', 'module' => 'CMDB', 'description' => 'IT uskunalar va dasturiy ta\'minot aktivlarini ko\'rish'],
            ['name' => 'assets.manage', 'module' => 'CMDB', 'description' => 'Aktivlarni ro\'yxatdan o\'tkazish va inventarizatsiya'],
            
            // SLA
            ['name' => 'sla.manage', 'module' => 'SLA', 'description' => 'SLA qoidalari va ish kalendarlarini sozlash'],
            
            // AUDIT LOGS
            ['name' => 'audit.view', 'module' => 'SECURITY', 'description' => 'Tizim amallari loglari va audit yozuvlarini ko\'rish'],
        ];

        foreach ($systemPermissions as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $p['name'], 'guard_name' => 'web'],
                [
                    'module' => $p['module'],
                    'description' => $p['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 2. Ensure Super Admin role has all permissions
        $superAdminRole = DB::table('roles')->whereRaw('LOWER(name) = ?', ['super admin'])->first();
        if ($superAdminRole) {
            $allPermIds = DB::table('permissions')->pluck('id');
            foreach ($allPermIds as $pId) {
                DB::table('role_has_permissions')->updateOrInsert(
                    ['role_id' => $superAdminRole->id, 'permission_id' => $pId],
                    []
                );
            }
        }
    }
}
