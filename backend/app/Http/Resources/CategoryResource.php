<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'parent' => $this->whenLoaded('parent', fn() => new CategoryResource($this->parent)),
            'description' => $this->description,
            'default_team_id' => $this->default_team_id,
            'default_priority_id' => $this->default_priority_id,
            // SLA — uch bosqich, daqiqalarda.
            'sla_accept_minutes' => (int) $this->sla_accept_minutes,
            'sla_work_minutes' => (int) $this->sla_work_minutes,
            'sla_close_minutes' => (int) $this->sla_close_minutes,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
