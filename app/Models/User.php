<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'public_id',
        'organization_id',
        'employee_id',
        'username',
        'email',
        'password',
        'auth_source',
        'status',
        'image',
        'locale_id',
        'timezone_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function employee()
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Employee::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the assigned role object for the user.
     */
    public function getRole()
    {
        $roleAssoc = DB::table('model_has_roles')
            ->where('model_id', $this->id)
            ->first();

        if ($roleAssoc) {
            return DB::table('roles')->where('id', $roleAssoc->role_id)->first();
        }

        return null; // No role assigned (Minimum access)
    }

    /**
     * Check if user is Super Admin
     */
    public function isSuperAdmin(): bool
    {
        if (strtolower((string) $this->username) === 'superadmin') return true;
        $role = $this->getRole();
        if (!$role) return false;
        return strtolower($role->name) === 'super admin';
    }

    /**
     * Check if user is Department Admin / Manager
     */
    public function isDepartmentAdmin(): bool
    {
        $role = $this->getRole();
        if (!$role) return false;
        return str_contains(strtolower($role->name), 'department') || str_contains(strtolower($role->name), 'manager');
    }

    /**
     * Get all permission names assigned to this user (via role + direct)
     */
    public function getAllPermissions(): array
    {
        $roleIds = DB::table('model_has_roles')
            ->where('model_id', $this->id)
            ->pluck('role_id')
            ->toArray();

        $rolePermissions = [];
        if (!empty($roleIds)) {
            $rolePermissions = DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->whereIn('role_has_permissions.role_id', $roleIds)
                ->pluck('permissions.name')
                ->toArray();
        }

        $directPermissions = DB::table('model_has_permissions')
            ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_id', $this->id)
            ->pluck('permissions.name')
            ->toArray();

        return array_values(array_unique(array_merge($rolePermissions, $directPermissions)));
    }

    /**
     * Check dynamic permission
     */
    public function hasPermission(string $permissionName): bool
    {
        if ($this->isSuperAdmin()) {
            return true; // Super Admin has all dynamic permissions
        }

        $allPermissions = $this->getAllPermissions();
        if (empty($allPermissions)) {
            return in_array($permissionName, ['tickets.view_own', 'tickets.create']);
        }

        return in_array($permissionName, $allPermissions);
    }

    /**
     * Get query for tickets accessible to this user based on role:
     * - Super Admin: ALL tickets
     * - Dept Admin: Tickets in their department
     * - Regular User / No Role: Only their own tickets
     */
    public function getAccessibleTicketsQuery()
    {
        $query = DB::table('tickets')->whereNull('deleted_at');

        if ($this->isSuperAdmin()) {
            return $query; // Full access
        }

        if ($this->isDepartmentAdmin()) {
            // Get employee department
            $employee = DB::table('employees')->where('id', $this->employee_id)->first();
            $deptId = $employee ? $employee->department_id : 1;
            return $query->where('department_id', $deptId);
        }

        // Standard User / No Role: Minimum access (only own tickets)
        return $query->where('requester_user_id', $this->id);
    }
}
