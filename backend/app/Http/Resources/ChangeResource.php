<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'change_no' => $this->change_no,
            'title' => $this->title,
            'description' => $this->description,
            'change_type' => $this->change_type,
            'risk_level' => $this->risk_level,
            'impact' => $this->impact,
            'status' => $this->status,
            'requester_user' => $this->whenLoaded('requesterUser', fn() => new UserResource($this->requesterUser)),
            'owner_user' => $this->whenLoaded('ownerUser', fn() => new UserResource($this->ownerUser)),
            'planned_start_at' => $this->planned_start_at?->toISOString(),
            'planned_end_at' => $this->planned_end_at?->toISOString(),
            'actual_start_at' => $this->actual_start_at?->toISOString(),
            'actual_end_at' => $this->actual_end_at?->toISOString(),
            'backout_plan' => $this->backout_plan,
            'test_plan' => $this->test_plan,
            'implementation_plan' => $this->implementation_plan,
            'approval_status' => $this->approval_status,
            'maintenance_windows' => $this->whenLoaded('maintenanceWindows', fn() => MaintenanceWindowResource::collection($this->maintenanceWindows)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
