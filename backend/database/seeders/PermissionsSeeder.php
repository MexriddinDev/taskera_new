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
            // NAVBAR & PAGES
            ['name' => 'dashboard.view', 'module' => 'NAVBAR', 'description' => 'Dashboard sahifasiga kirish'],
            ['name' => 'tasks.view', 'module' => 'NAVBAR', 'description' => 'Barcha topshiriqlar (Tasks) sahifasiga kirish'],
            ['name' => 'my_tasks.view', 'module' => 'NAVBAR', 'description' => 'Mening topshiriqlarim (My Tasks) sahifasiga kirish'],
            ['name' => 'monitoring.view', 'module' => 'NAVBAR', 'description' => 'Superadmin Monitoring (Command Center) sahifasiga kirish'],
            ['name' => 'team_workload.view', 'module' => 'NAVBAR', 'description' => 'Xodimlar zayavkalari (Team Workload) sahifasiga kirish'],
            ['name' => 'stats.view', 'module' => 'NAVBAR', 'description' => 'Statistika sahifasiga kirish'],
            ['name' => 'roles.manage', 'module' => 'NAVBAR', 'description' => 'Rollar & Bo\'limlar (RBAC) sahifasiga kirish'],

            // TICKETS & OPERATIONS
            ['name' => 'tickets.view', 'module' => 'TICKETS', 'description' => 'Barcha zayavkalarni va topshiriqlarni ko\'rish'],
            ['name' => 'tickets.create', 'module' => 'TICKETS', 'description' => 'Yangi zayavka va murojaat yaratish'],
            ['name' => 'tickets.assign', 'module' => 'TICKETS', 'description' => 'Zayavkani ijrochiga / xodimga biriktirish'],
            ['name' => 'tickets.transition', 'module' => 'TICKETS', 'description' => 'Zayavka holatini o\'zgartirish (yopish, ijro etish)'],
            ['name' => 'tickets.delete', 'module' => 'TICKETS', 'description' => 'Zayavkalarni o\'chirish'],
            ['name' => 'tickets.view_own', 'module' => 'TICKETS', 'description' => 'Faqat o\'ziga tegishli zayavkalarni ko\'rish'],
            ['name' => 'tickets.export', 'module' => 'TICKETS', 'description' => 'Zayavkalarni Excel / PDF ga eksport qilish'],
            
            // RBAC & SECURITY
            ['name' => 'users.manage', 'module' => 'RBAC', 'description' => 'Foydalanuvchilar va xodimlarni boshqarish'],
            ['name' => 'departments.manage', 'module' => 'ORG', 'description' => 'Bo\'limlar, filiallar va xizmat guruhlarini boshqarish'],
            
            // KNOWLEDGE BASE & ASSETS
            ['name' => 'knowledge.view', 'module' => 'KNOWLEDGE', 'description' => 'Bilimlar bazasi va ko\'rsatmalarni ko\'rish'],
            ['name' => 'knowledge.manage', 'module' => 'KNOWLEDGE', 'description' => 'Maqolalar yaratish va nashr etish'],
            ['name' => 'assets.view', 'module' => 'CMDB', 'description' => 'IT uskunalar va dasturiy ta\'minot aktivlarini ko\'rish'],
            ['name' => 'assets.manage', 'module' => 'CMDB', 'description' => 'Aktivlarni ro\'yxatdan o\'tkazish va inventarizatsiya'],
            ['name' => 'sla.manage', 'module' => 'SLA', 'description' => 'SLA qoidalari va ish kalendarlarini sozlash'],
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
