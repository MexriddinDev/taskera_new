<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'branch_id' => $this->branch_id,
            'parent_id' => $this->parent_id,
            'floor' => $this->floor,
            'room' => $this->room,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
        ];
    }
}
