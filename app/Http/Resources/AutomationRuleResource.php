<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AutomationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'event_type' => $this->event_type,
            'conditions' => $this->conditions,
            'actions' => $this->actions,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'stop_processing' => $this->stop_processing,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
