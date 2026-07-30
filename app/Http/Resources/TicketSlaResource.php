<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TicketSlaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'ticket_id' => $this->ticket_id,
            'sla_target_id' => $this->sla_target_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'warning_at' => $this->warning_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'breached_at' => $this->breached_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'paused_seconds' => $this->paused_seconds,
            'remaining_seconds' => $this->remaining_seconds,
            'status' => $this->status,
            'sla_target' => $this->whenLoaded('slaTarget', fn() => new SlaTargetResource($this->slaTarget)),
        ];
    }
}
