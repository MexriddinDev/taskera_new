<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Re-seed permissions: Clear old permissions safely
        //
        // `module` maydoni navbar bo'limlarini AYNAN takrorlaydi — RBAC
        // sahifasi huquqlarni shu bo'yicha guruhlaydi, ya'ni qaysi huquq qaysi
        // bo'limni ochishi bir qarashda ko'rinadi. Modul nomlari
        // RbacManagementPage dagi MODULE_NAMES bilan mos bo'lishi shart.
        $systemPermissions = [
            // ——— Zayavkalar va operatsiyalar bo'limi ———
            ['name' => 'dashboard.view', 'module' => 'OPERATIONS', 'description' => 'Boshqaruv paneli — barcha zayavkalar taxtasi'],
            ['name' => 'tasks.view', 'module' => 'OPERATIONS', 'description' => 'Ochiq topshiriqlar (Tasks) sahifasiga kirish'],
            ['name' => 'my_tasks.view', 'module' => 'OPERATIONS', 'description' => 'Mening topshiriqlarim (My Tasks) sahifasiga kirish'],
            ['name' => 'monitoring.view', 'module' => 'OPERATIONS', 'description' => 'Monitoring (Command Center) sahifasiga kirish'],
            ['name' => 'team_workload.view', 'module' => 'OPERATIONS', 'description' => 'Xodimlar zayavkalari (Team Workload) sahifasiga kirish'],
            ['name' => 'users.view', 'module' => 'OPERATIONS', 'description' => 'Foydalanuvchilar va bo\'limlar statistikasi sahifasiga kirish'],
            ['name' => 'stats.view', 'module' => 'OPERATIONS', 'description' => 'Statistika sahifasiga kirish'],

            // ——— Zayavka ustidagi amallar ———
            ['name' => 'tickets.view', 'module' => 'TICKETS', 'description' => 'Barcha zayavkalarni va topshiriqlarni ko\'rish (xodim / support huquqi)'],
            ['name' => 'tickets.create', 'module' => 'TICKETS', 'description' => 'Yangi zayavka va murojaat yaratish'],
            ['name' => 'tickets.assign', 'module' => 'TICKETS', 'description' => 'Zayavkani ijrochiga / xodimga biriktirish'],
            ['name' => 'tickets.transition', 'module' => 'TICKETS', 'description' => 'Zayavka holatini o\'zgartirish (yopish, ijro etish)'],
            ['name' => 'tickets.delete', 'module' => 'TICKETS', 'description' => 'Zayavkalarni o\'chirish'],
            ['name' => 'tickets.view_own', 'module' => 'TICKETS', 'description' => 'Faqat o\'ziga tegishli zayavkalarni ko\'rish'],
            ['name' => 'tickets.export', 'module' => 'TICKETS', 'description' => 'Zayavkalarni Excel / PDF ga eksport qilish'],

            // ——— ITSM xizmatlari bo'limi ———
            ['name' => 'knowledge.view', 'module' => 'ITSM', 'description' => 'Bilimlar bazasi va ko\'rsatmalarni ko\'rish'],
            ['name' => 'knowledge.manage', 'module' => 'ITSM', 'description' => 'Maqolalar yaratish va nashr etish'],
            ['name' => 'catalog.view', 'module' => 'ITSM', 'description' => 'Xizmatlar katalogi sahifasiga kirish'],
            ['name' => 'approvals.view', 'module' => 'ITSM', 'description' => 'Tasdiqlashlar sahifasiga kirish'],
            ['name' => 'assets.view', 'module' => 'ITSM', 'description' => 'IT uskunalar va dasturiy ta\'minot aktivlarini ko\'rish'],
            ['name' => 'assets.manage', 'module' => 'ITSM', 'description' => 'Aktivlarni ro\'yxatdan o\'tkazish va inventarizatsiya'],
            ['name' => 'problems.view', 'module' => 'ITSM', 'description' => 'Muammolar (Problems) ro\'yxatini ko\'rish'],
            ['name' => 'problems.manage', 'module' => 'ITSM', 'description' => 'Muammolarni yaratish, tahrirlash va yechim kiritish'],
            ['name' => 'changes.view', 'module' => 'ITSM', 'description' => 'O\'zgarishlar (Changes) ro\'yxatini ko\'rish'],
            ['name' => 'changes.manage', 'module' => 'ITSM', 'description' => 'O\'zgarishlarni yaratish va tahrirlash'],
            ['name' => 'changes.approve', 'module' => 'ITSM', 'description' => 'O\'zgarishlarni (CAB) tasdiqlash yoki rad etish'],

            // ——— Administratsiya va sozlamalar bo'limi ———
            ['name' => 'sla.manage', 'module' => 'ADMIN', 'description' => 'SLA qoidalari va ish kalendarlarini sozlash'],
            ['name' => 'automation.manage', 'module' => 'ADMIN', 'description' => 'Avtomatlashtirish qoidalari (Triggers/Rules) ni boshqarish'],
            ['name' => 'services.manage', 'module' => 'ADMIN', 'description' => 'Xizmatlar katalogi va master ma\'lumotlarni boshqarish'],
            ['name' => 'workflows.manage', 'module' => 'ADMIN', 'description' => 'Biznes jarayonlar va workflowlarni boshqarish'],
            ['name' => 'integrations.manage', 'module' => 'ADMIN', 'description' => 'Tashqi integratsiyalar va Webhooklarni sozlash'],
            ['name' => 'roles.manage', 'module' => 'ADMIN', 'description' => 'Rollar va bo\'limlar (RBAC) sahifasiga kirish'],
            ['name' => 'users.manage', 'module' => 'ADMIN', 'description' => 'Foydalanuvchilar va xodimlarni boshqarish'],
            ['name' => 'departments.manage', 'module' => 'ADMIN', 'description' => 'Bo\'limlar, filiallar va xizmat guruhlarini boshqarish'],
            ['name' => 'audit.view', 'module' => 'ADMIN', 'description' => 'Tizim amallari loglari va audit yozuvlarini ko\'rish'],
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
