<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'employee_no' => $this->employee_no,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department' => $this->whenLoaded('department', fn() => new DepartmentResource($this->department)),
            'branch' => $this->whenLoaded('branch', fn() => new BranchResource($this->branch)),
            'position' => $this->whenLoaded('position', fn() => new PositionResource($this->position)),
            'manager' => $this->whenLoaded('manager', fn() => new EmployeeResource($this->manager)),
            'employment_status_id' => $this->employment_status_id,
            'is_active' => $this->is_active,
        ];
    }
}
