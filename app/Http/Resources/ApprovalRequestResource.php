<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'approvable_type' => $this->approvable_type,
            'approvable_id' => $this->approvable_id,
            'workflow_run_id' => $this->workflow_run_id,
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'requested_at' => $this->requested_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'steps' => $this->whenLoaded('steps', fn() => $this->steps),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
