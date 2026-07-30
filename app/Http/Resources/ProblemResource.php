<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProblemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'problem_no' => $this->problem_no,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority_id' => $this->priority_id,
            'owner_user' => $this->whenLoaded('ownerUser', fn() => new UserResource($this->ownerUser)),
            'known_error' => $this->known_error,
            'root_cause' => $this->root_cause,
            'workaround' => $this->workaround,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'tickets' => $this->whenLoaded('tickets', fn() => TicketResource::collection($this->tickets)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
