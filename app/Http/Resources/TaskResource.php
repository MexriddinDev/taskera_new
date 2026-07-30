<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority_id' => $this->priority_id,
            'assignee_user_id' => $this->assignee_user_id,
            'taskable_type' => $this->taskable_type,
            'taskable_id' => $this->taskable_id,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
