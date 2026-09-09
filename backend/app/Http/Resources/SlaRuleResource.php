<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SlaRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'code' => $this->team->code,
            ]),
            'priority_id' => $this->priority_id,
            'priority' => $this->whenLoaded('priority', fn () => $this->priority ? [
                'id' => $this->priority->id,
                'name' => $this->priority->name,
                'code' => $this->priority->code,
                'color' => $this->priority->color,
            ] : null),
            'name' => $this->name,
            'description' => $this->description,
            'accept_minutes' => $this->accept_minutes,
            'work_minutes' => $this->work_minutes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
