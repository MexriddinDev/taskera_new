<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkflowRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'workflow_id' => $this->workflow_id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'status' => $this->status,
            'context' => $this->context,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'error_message' => $this->error_message,
        ];
    }
}
