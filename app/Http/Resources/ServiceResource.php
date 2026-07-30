<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'owner_user_id' => $this->owner_user_id,
            'support_team_id' => $this->support_team_id,
            'criticality' => $this->criticality,
            'is_active' => $this->is_active,
        ];
    }
}
