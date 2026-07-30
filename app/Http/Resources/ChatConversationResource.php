<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'type' => $this->type,
            'title' => $this->title,
            'linked_type' => $this->linked_type,
            'linked_id' => $this->linked_id,
            'created_by' => $this->created_by,
            'participants' => ChatParticipantResource::collection($this->whenLoaded('participants')),
            'last_message' => $this->whenLoaded('messages', fn() => ChatMessageResource::collection($this->messages->take(1))),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
