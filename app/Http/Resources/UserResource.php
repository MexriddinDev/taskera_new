<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;

        $roleObj = method_exists($this->resource, 'getRole') ? $this->resource->getRole() : null;
        $roleName = $roleObj ? $roleObj->name : 'Standard User';
        
        $permissions = method_exists($this->resource, 'getAllPermissions') ? $this->resource->getAllPermissions() : [];

        $isStaff = false;
        if (method_exists($this->resource, 'isSuperAdmin') && $this->resource->isSuperAdmin()) {
            $isStaff = true;
        } elseif (method_exists($this->resource, 'isDepartmentAdmin') && $this->resource->isDepartmentAdmin()) {
            $isStaff = true;
        } elseif (!empty($permissions) && array_intersect(['tickets.view', 'tickets.assign', 'tickets.transition', 'roles.manage', 'departments.manage', 'stats.view'], $permissions)) {
            $isStaff = true;
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'firstName' => $employee?->first_name ?? $this->username,
            'lastName' => $employee?->last_name ?? 'User',
            'image' => null,
            'phone' => $employee?->phone ?? null,
            'role' => $roleName,
            'permissions' => $permissions,
            'isStaff' => $isStaff,
        ];
    }
}

