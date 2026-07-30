<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class IntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'integration_type_id' => $this->integration_type_id,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'config' => $this->config,
            'is_active' => $this->is_active,
            'last_success_at' => $this->last_success_at?->toISOString(),
            'last_failure_at' => $this->last_failure_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
