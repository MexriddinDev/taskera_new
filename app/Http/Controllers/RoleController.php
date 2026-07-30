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
            ->select('roles.id', 'roles.organization_id', 'roles.name', 'roles.guard_name', 'roles.description', 'roles.created_at', 'roles.updated_at', DB::raw('COUNT(model_has_roles.model_id) as users_count'))
            ->groupBy('roles.id', 'roles.organization_id', 'roles.name', 'roles.guard_name', 'roles.description', 'roles.created_at', 'roles.updated_at')
            ->orderBy('roles.id')
            ->get();

        $roles = $roles->map(function ($role) {
            $users = DB::table('model_has_roles')
                ->join('users', 'model_has_roles.model_id', '=', 'users.id')
                ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
                ->where('model_has_roles.role_id', $role->id)
                ->where('model_has_roles.model_type', 'like', '%User')
                ->select('users.id', 'users.username', 'employees.first_name', 'employees.last_name')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'username' => $u->username,
                        'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username,
                    ];
                });

            $role->users = $users;

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
        ]);

        $role = DB::table('roles')->insertGetId([
            'organization_id' => $validated['organization_id'] ?? 1,
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => DB::table('roles')->find($role)], 201);
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

        DB::table('roles')->where('id', $id)->update($updateData);

        return response()->json(['data' => DB::table('roles')->find($id)]);
    }

    public function destroy(Request $request, $id): JsonResponse|RedirectResponse
    {
        DB::table('model_has_roles')->where('role_id', $id)->delete();
        DB::table('role_has_permissions')->where('role_id', $id)->delete();
        DB::table('roles')->where('id', $id)->delete();

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

        return response()->json(['data' => DB::table('permissions')->find($id)]);
    }

    public function destroyPermission(Request $request, $id): JsonResponse
    {
        DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        DB::table('model_has_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();

        return response()->json(['message' => 'Permission o\'chirildi']);
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

        $result = $users->map(function ($u) {
            $permissions = [];
            if ($u->role_id) {
                $permissions = DB::table('role_has_permissions')
                    ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_has_permissions.role_id', $u->role_id)
                    ->pluck('permissions.name')
                    ->toArray();
            }

            // Also check direct permissions assigned to model_has_permissions
            $directPermissions = DB::table('model_has_permissions')
                ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
                ->where('model_has_permissions.model_id', $u->id)
                ->pluck('permissions.name')
                ->toArray();

            $mergedPermissions = array_unique(array_merge($permissions, $directPermissions));

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
                'roleName' => $u->role_name ?? 'Standard User',
                'permissions' => array_values($mergedPermissions),
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function assignUserRole(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'position_id' => 'nullable|integer|exists:positions,id',
        ]);

        $roleId = $validated['role_id'];

        // 1. Assign role to user
        DB::table('model_has_roles')->where('model_id', $id)->delete();
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => 'App\\Modules\\Identity\\Infrastructure\\Eloquent\\User',
            'model_id' => $id,
            'organization_id' => 1,
        ]);

        // 2. Sync permissions for role if passed
        if (isset($validated['permissions'])) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            foreach ($validated['permissions'] as $pId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $pId,
                ]);
            }
        }

        // 3. Update employee department, branch, and position if user has an associated employee
        $user = DB::table('users')->where('id', $id)->first();
        if ($user && $user->employee_id) {
            $employeeUpdates = [];
            if (isset($validated['department_id'])) {
                $employeeUpdates['department_id'] = $validated['department_id'];
            }
            if (isset($validated['branch_id'])) {
                $employeeUpdates['branch_id'] = $validated['branch_id'];
            }
            if (isset($validated['position_id'])) {
                $employeeUpdates['position_id'] = $validated['position_id'];
            }
            if (!empty($employeeUpdates)) {
                $employeeUpdates['updated_at'] = now();
                DB::table('employees')->where('id', $user->employee_id)->update($employeeUpdates);
            }
        }

        return response()->json(['message' => 'Xodimgaga rol, bo\'lim va huquqlar biriktirildi']);
    }
}

