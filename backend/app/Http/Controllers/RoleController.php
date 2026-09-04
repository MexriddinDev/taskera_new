<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $roles = DB::table('roles')
            ->leftJoin('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select('roles.id', 'roles.organization_id', 'roles.name', 'roles.guard_name', 'roles.description', 'roles.created_at', 'roles.updated_at', DB::raw('COUNT(DISTINCT model_has_roles.model_id) as users_count'))
            ->groupBy('roles.id', 'roles.organization_id', 'roles.name', 'roles.guard_name', 'roles.description', 'roles.created_at', 'roles.updated_at')
            ->orderBy('roles.id')
            ->get();

        $roleIds = $roles->pluck('id')->toArray();

        // 1. Pre-fetch all role users in ONE query
        $usersByRoleId = [];
        if (!empty($roleIds)) {
            $allUsers = DB::table('model_has_roles')
                ->join('users', 'model_has_roles.model_id', '=', 'users.id')
                ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
                ->whereIn('model_has_roles.role_id', $roleIds)
                ->where('model_has_roles.model_type', 'like', '%User')
                ->select('model_has_roles.role_id', 'users.id', 'users.username', 'employees.first_name', 'employees.last_name')
                ->get()
                ->groupBy('role_id');

            foreach ($allUsers as $rId => $items) {
                $usersByRoleId[$rId] = $items->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'username' => $u->username,
                        'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username,
                    ];
                })->values();
            }
        }

        // 2. Pre-fetch all role permissions in ONE query
        $permsByRoleId = [];
        if (!empty($roleIds)) {
            $allPerms = DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->whereIn('role_has_permissions.role_id', $roleIds)
                ->select('role_has_permissions.role_id', 'permissions.id', 'permissions.name', 'permissions.module', 'permissions.description')
                ->get()
                ->groupBy('role_id');

            foreach ($allPerms as $rId => $items) {
                $permsByRoleId[$rId] = $items->values();
            }
        }

        $roles = $roles->map(function ($role) use ($usersByRoleId, $permsByRoleId) {
            $users = isset($usersByRoleId[$role->id]) ? $usersByRoleId[$role->id] : collect([]);
            $permissions = isset($permsByRoleId[$role->id]) ? $permsByRoleId[$role->id] : collect([]);

            $role->users = $users;
            $role->permissions = $permissions;
            $role->permission_ids = $permissions->pluck('id')->toArray();

            return $role;
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $roles]);
        }

        return view('settings.roles', compact('roles'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:125',
            'guard_name' => 'required|string|max:125',
            'description' => 'nullable|string',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $roleId = DB::transaction(function () use ($validated) {
            $newRoleId = DB::table('roles')->insertGetId([
                'organization_id' => $validated['organization_id'] ?? 1,
                'name' => $validated['name'],
                'guard_name' => $validated['guard_name'],
                'description' => $validated['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (isset($validated['permissions'])) {
                foreach ($validated['permissions'] as $pId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'role_id' => $newRoleId,
                        'permission_id' => $pId,
                    ]);
                }
            }

            return $newRoleId;
        });

        $createdRole = DB::table('roles')->find($roleId);

        \App\Modules\Audit\Domain\Services\AuditLogger::log($request, 'ROLE_CREATED', "Yangi rol yaratildi: {$validated['name']} (#{$roleId})", [
            'actor_user_id' => auth()->id(),
            'auditable_type' => 'Role',
            'auditable_id' => $roleId,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $createdRole], 201);
        }

        return redirect()->back()->with('success', 'Role created successfully');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:125',
            'guard_name' => 'sometimes|string|max:125',
            'description' => 'nullable|string',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = DB::table('roles')->where('id', $id)->first();
        if (!$role) {
            return response()->json(['message' => 'Role topilmadi'], 404);
        }

        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['guard_name'])) {
            $updateData['guard_name'] = $validated['guard_name'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (isset($validated['organization_id'])) {
            $updateData['organization_id'] = $validated['organization_id'];
        }
        $updateData['updated_at'] = now();

        $permList = $request->input('permissions') ?? $request->input('permission_ids') ?? null;

        // Kesh bekor qilinadigan foydalanuvchilar ro'yxati tranzaksiyadan OLDIN olinadi —
        // model_has_roles o'zgarmaydi, lekin ro'yxatni oldindan olish tartibni sodda qiladi.
        $affectedUserIds = DB::table('model_has_roles')
            ->where('role_id', $id)
            ->pluck('model_id')
            ->all();

        DB::transaction(function () use ($id, $updateData, $permList) {
            DB::table('roles')->where('id', $id)->update($updateData);

            if (is_array($permList)) {
                DB::table('role_has_permissions')->where('role_id', $id)->delete();
                foreach ($permList as $pId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'role_id' => $id,
                        'permission_id' => (int) $pId,
                    ]);
                }
            }
        });

        // Rol huquqlari o'zgardi — egalarining keshlangan huquqlari darhol bekor qilinadi,
        // aks holda `permission:` middleware 10 daqiqagacha eski huquqlar bilan ishlaydi.
        foreach ($affectedUserIds as $affectedUserId) {
            \App\Models\User::forgetPermissionsCache((int) $affectedUserId);
        }

        \App\Modules\Audit\Domain\Services\AuditLogger::log($request, 'ROLE_UPDATED', "Rol #{$id} ({$role->name}) nomi va huquqlari tahrirlandi", [
            'actor_user_id' => auth()->id(),
            'auditable_type' => 'Role',
            'auditable_id' => $id,
            'old_values' => ['name' => $role->name],
            'new_values' => $updateData,
            'changed_fields' => array_keys($updateData),
        ]);

        return response()->json(['data' => DB::table('roles')->find($id)]);
    }

    public function destroy(Request $request, $id): JsonResponse|RedirectResponse
    {
        $role = DB::table('roles')->where('id', $id)->first();
        $roleName = $role?->name ?? "#{$id}";

        // Egalar ro'yxati model_has_roles o'chirilishidan OLDIN olinadi.
        $affectedUserIds = DB::table('model_has_roles')
            ->where('role_id', $id)
            ->pluck('model_id')
            ->all();

        DB::transaction(function () use ($id) {
            DB::table('model_has_roles')->where('role_id', $id)->delete();
            DB::table('role_has_permissions')->where('role_id', $id)->delete();
            DB::table('roles')->where('id', $id)->delete();
        });

        foreach ($affectedUserIds as $affectedUserId) {
            \App\Models\User::forgetPermissionsCache((int) $affectedUserId);
        }

        \App\Modules\Audit\Domain\Services\AuditLogger::log($request, 'ROLE_DELETED', "Rol o'chirildi: {$roleName}", [
            'actor_user_id' => auth()->id(),
            'auditable_type' => 'Role',
            'auditable_id' => $id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Deleted']);
        }

        return redirect()->back()->with('success', 'Role deleted successfully');
    }

    public function permissions(): JsonResponse
    {
        $permissions = DB::table('permissions')->get();
        return response()->json(['data' => $permissions]);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:125',
            'guard_name' => 'nullable|string|max:125',
            'module' => 'nullable|string|max:64',
            'description' => 'nullable|string',
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
            'module' => $validated['module'] ?? 'CORE',
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Permission muvaffaqiyatli yaratildi',
            'data' => DB::table('permissions')->find($permissionId),
        ], 201);
    }

    public function updatePermission(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:125',
            'guard_name' => 'nullable|string|max:125',
            'module' => 'nullable|string|max:64',
            'description' => 'nullable|string',
        ]);

        $permission = DB::table('permissions')->where('id', $id)->first();
        if (!$permission) {
            return response()->json(['message' => 'Permission topilmadi'], 404);
        }

        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (array_key_exists('guard_name', $validated)) {
            $updateData['guard_name'] = $validated['guard_name'] ?? 'web';
        }
        if (array_key_exists('module', $validated)) {
            $updateData['module'] = $validated['module'] ?? 'CORE';
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        $updateData['updated_at'] = now();

        DB::table('permissions')->where('id', $id)->update($updateData);

        // Nomi o'zgargan huquq keshdagi eski nom bilan yotib qolmasligi kerak.
        \App\Models\User::forgetPermissionsCacheForPermission((int) $id);

        return response()->json(['data' => DB::table('permissions')->find($id)]);
    }

    public function destroyPermission(Request $request, $id): JsonResponse
    {
        // Ta'sirlangan foydalanuvchilar ro'yxati bog'lanishlar o'chirilishidan OLDIN
        // yig'iladi (keyin ularni topib bo'lmaydi), lekin kesh o'chirish tranzaksiyadan
        // KEYIN bajariladi: aks holda o'chirish va forget orasida boshqa so'rov keshni
        // eski (hali mavjud) huquq bilan qayta to'ldirib qo'yishi mumkin edi.
        $affectedUserIds = $this->userIdsAffectedByPermission((int) $id);

        DB::transaction(function () use ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        });

        foreach ($affectedUserIds as $affectedUserId) {
            \App\Models\User::forgetPermissionsCache((int) $affectedUserId);
        }

        return response()->json(['message' => 'Permission o\'chirildi']);
    }

    /**
     * Huquq (permission) ta'sir qiladigan foydalanuvchi id'lari:
     * rol orqali olganlar + to'g'ridan-to'g'ri berilganlar.
     *
     * @return int[]
     */
    private function userIdsAffectedByPermission(int $permissionId): array
    {
        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->pluck('role_id')
            ->all();

        $viaRoles = empty($roleIds)
            ? []
            : DB::table('model_has_roles')->whereIn('role_id', $roleIds)->pluck('model_id')->all();

        $direct = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->pluck('model_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($viaRoles, $direct))));
    }

    public function usersWithRoles(): JsonResponse
    {
        $users = DB::table('users')
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->leftJoin('branches', 'employees.branch_id', '=', 'branches.id')
            ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
            ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select(
                'users.id',
                'users.employee_id',
                'users.username',
                'users.email',
                'employees.first_name',
                'employees.last_name',
                'employees.department_id',
                'departments.name as department_name',
                'employees.branch_id',
                'branches.name as branch_name',
                'employees.position_id',
                'positions.name as position_name',
                'roles.id as role_id',
                'roles.name as role_name'
            )
            ->get();

        // 1. Pre-fetch all role permissions in ONE query grouped by role_id
        $roleIds = $users->pluck('role_id')->filter()->unique()->toArray();
        $rolePermissionsByRoleId = [];
        if (!empty($roleIds)) {
            $rolePerms = DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->whereIn('role_has_permissions.role_id', $roleIds)
                ->select('role_has_permissions.role_id', 'permissions.name')
                ->get()
                ->groupBy('role_id');

            foreach ($rolePerms as $rId => $items) {
                $rolePermissionsByRoleId[$rId] = $items->pluck('name')->toArray();
            }
        }

        // 2. Pre-fetch all direct user permissions in ONE query grouped by user_id
        $userIds = $users->pluck('id')->toArray();
        $directPermsByUserId = [];
        if (!empty($userIds)) {
            $directPerms = DB::table('model_has_permissions')
                ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
                ->whereIn('model_has_permissions.model_id', $userIds)
                ->select('model_has_permissions.model_id', 'permissions.name')
                ->get()
                ->groupBy('model_id');

            foreach ($directPerms as $uId => $items) {
                $directPermsByUserId[$uId] = $items->pluck('name')->toArray();
            }
        }

        // 3. Pre-fetch all team memberships in ONE query grouped by user_id
        $teamsByUserId = [];
        if (!empty($userIds)) {
            $teams = DB::table('team_members')
                ->join('teams', 'team_members.team_id', '=', 'teams.id')
                ->whereIn('team_members.user_id', $userIds)
                ->whereNull('team_members.left_at')
                ->select('team_members.user_id', 'teams.id', 'teams.name', 'teams.code', 'team_members.is_lead')
                ->get()
                ->groupBy('user_id');

            foreach ($teams as $uId => $items) {
                $teamsByUserId[$uId] = $items->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'code' => $t->code,
                        'is_lead' => (bool) $t->is_lead,
                    ];
                })->values();
            }
        }

        // Map without running any queries in the loop
        $result = $users->map(function ($u) use ($rolePermissionsByRoleId, $directPermsByUserId, $teamsByUserId) {
            $rolePerms = ($u->role_id && isset($rolePermissionsByRoleId[$u->role_id])) ? $rolePermissionsByRoleId[$u->role_id] : [];
            $directPerms = isset($directPermsByUserId[$u->id]) ? $directPermsByUserId[$u->id] : [];
            $mergedPermissions = array_values(array_unique(array_merge($rolePerms, $directPerms)));
            $userTeams = isset($teamsByUserId[$u->id]) ? $teamsByUserId[$u->id] : collect([]);

            return [
                'id' => $u->id,
                'employeeId' => $u->employee_id,
                'username' => $u->username,
                'email' => $u->email,
                'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username,
                'departmentId' => $u->department_id,
                'departmentName' => $u->department_name ?? 'Bo\'limsiz',
                'branchId' => $u->branch_id,
                'branchName' => $u->branch_name ?? 'Filialsiz',
                'positionId' => $u->position_id,
                'positionName' => $u->position_name ?? 'Lavozimsiz',
                'roleId' => $u->role_id,
                'roleName' => $u->role_name,
                // `permissions` — amaldagi to'liq ro'yxat (rol + shaxsiy).
                // Ikkalasi alohida ham qaytariladi: RBAC sahifasidagi belgilash
                // katakchalari FAQAT shaxsiy huquqlarga tegishli. Ilgari u
                // birlashtirilgan ro'yxatdan to'ldirilar edi — natijada rolning
                // barcha huquqlari belgilangan ko'rinar, saqlanganda esa ular
                // shaxsiy huquq sifatida yozilib, rol o'zgargandan keyin ham
                // foydalanuvchida qolib ketardi.
                'permissions' => $mergedPermissions,
                'rolePermissions' => array_values($rolePerms),
                'directPermissions' => array_values($directPerms),
                'teams' => $userTeams,
                'teamIds' => $userTeams->pluck('id')->toArray(),
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function assignUserRole(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            // Rolsiz foydalanuvchi bo'lmaydi. Ilgari `min:0` edi va role_id=0
            // rolni butunlay olib tashlash degani edi.
            'role_id' => 'required|integer|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'integer|exists:teams,id',
        ]);

        $roleId = (int) $validated['role_id'];
        $userId = (int) $id;

        // Barcha o'zgarishlar bitta tranzaksiyada — o'rtada xato bo'lsa
        // foydalanuvchi rolsiz/huquqsiz qolib ketmaydi.
        DB::transaction(function () use ($validated, $roleId, $userId) {
            // 1. Rolni almashtirish — foydalanuvchi doim aynan bitta rolda bo'ladi
            DB::table('model_has_roles')->where('model_id', $userId)->delete();
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $userId,
                'organization_id' => \App\Support\CurrentOrg::id($request ?? null),
            ]);

            // 2. Direct permissions for this user (stored in model_has_permissions)
            DB::table('model_has_permissions')->where('model_id', $userId)->delete();
            if (! empty($validated['permissions']) && is_array($validated['permissions'])) {
                foreach ($validated['permissions'] as $pId) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $pId,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $userId,
                        'organization_id' => \App\Support\CurrentOrg::id($request ?? null),
                    ]);
                }
            }

            // 3. Update employee department, branch, position
            $user = DB::table('users')->where('id', $userId)->first();
            if ($user && $user->employee_id) {
                $employeeUpdates = [];
                if (array_key_exists('department_id', $validated)) {
                    $employeeUpdates['department_id'] = $validated['department_id'];
                }
                if (array_key_exists('branch_id', $validated)) {
                    $employeeUpdates['branch_id'] = $validated['branch_id'];
                }
                if (array_key_exists('position_id', $validated)) {
                    $employeeUpdates['position_id'] = $validated['position_id'];
                }
                if (!empty($employeeUpdates)) {
                    $employeeUpdates['updated_at'] = now();
                    DB::table('employees')->where('id', $user->employee_id)->update($employeeUpdates);
                }
            }

            // 4. Sync team/group assignments
            if (array_key_exists('team_ids', $validated)) {
                $newTeamIds = is_array($validated['team_ids']) ? array_map('intval', $validated['team_ids']) : [];

                // Mark teams not in new set as left
                DB::table('team_members')
                    ->where('user_id', $userId)
                    ->whereNotIn('team_id', $newTeamIds)
                    ->whereNull('left_at')
                    ->update(['left_at' => now()]);

                // Add or reactivate new teams
                foreach ($newTeamIds as $teamId) {
                    $existing = DB::table('team_members')
                        ->where('team_id', $teamId)
                        ->where('user_id', $userId)
                        ->first();

                    if ($existing) {
                        if ($existing->left_at !== null) {
                            DB::table('team_members')
                                ->where('team_id', $teamId)
                                ->where('user_id', $userId)
                                ->update(['left_at' => null, 'joined_at' => now()]);
                        }
                    } else {
                        DB::table('team_members')->insert([
                            'team_id' => $teamId,
                            'user_id' => $userId,
                            'is_lead' => false,
                            'joined_at' => now(),
                        ]);
                    }
                }
            }

            return $user;
        });

        $targetUser = DB::table('users')->where('id', $id)->value('username') ?? "user #{$id}";
        $performer = auth()->user()?->username ?? 'Tizim';

        $roleName = DB::table('roles')->where('id', $roleId)->value('name') ?? "role #{$roleId}";
        $auditDescription = "{$performer} foydalanuvchi {$targetUser} ga rol biriktirdi: {$roleName}";
        $auditValues = ['role_id' => $roleId];

        \App\Modules\Audit\Domain\Services\AuditLogger::log($request, 'USER_ROLE_CHANGED', $auditDescription, [
            'actor_user_id' => auth()->id(),
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $id,
            'new_values' => $auditValues,
            'changed_fields' => ['role_id'],
        ]);

        \App\Models\User::forgetPermissionsCache((int) $id);

        return response()->json(['message' => 'Xodimga rol, bo\'lim, guruhlar va huquqlar biriktirildi']);
    }
}

