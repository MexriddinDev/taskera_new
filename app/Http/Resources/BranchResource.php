<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'branch_type' => $this->branch_type,
            'region' => $this->whenLoaded('region', fn() => new RegionResource($this->region)),
            'address' => $this->address,
            'manager_employee_id' => $this->manager_employee_id,
            'is_active' => $this->is_active,
        ];
    }
}
