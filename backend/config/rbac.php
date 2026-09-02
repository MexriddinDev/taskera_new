<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bo'lim admini deb sanaladigan qo'shimcha rol nomlari
    |--------------------------------------------------------------------------
    |
    | `User::isDepartmentAdmin()` rol nomida birlik so'zi (department / bo'lim)
    | VA lavozim so'zi (admin / manager / boshliq ...) birga kelishini talab
    | qiladi. Bu "Department Viewer" yoki "Account Manager" kabi nomlar noto'g'ri
    | admin bo'lib qolishining oldini oladi.
    |
    | Ammo real bazadagi rollar erkin nomlanadi: "IT Manager", "Menejer" kabi
    | nomlar birlik so'zisiz bo'lib, qoida kuchayganda jimgina huquqdan mahrum
    | bo'lishi mumkin. Ularni bu yerda ANIQ ko'rsatib qo'yish mumkin.
    |
    | .env: RBAC_DEPARTMENT_ADMIN_ROLES="IT Manager,Menejer"
    |
    */

    'department_admin_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('RBAC_DEPARTMENT_ADMIN_ROLES', ''))
    ), static fn ($name) => $name !== '')),

];
