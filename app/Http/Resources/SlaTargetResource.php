<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SlaTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sla_policy_id' => $this->sla_policy_id,
            'priority_id' => $this->priority_id,
            'metric_type' => $this->metric_type,
            'target_minutes' => $this->target_minutes,
            'warning_minutes' => $this->warning_minutes,
            'escalation_minutes' => $this->escalation_minutes,
            'pause_statuses' => $this->pause_statuses,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
