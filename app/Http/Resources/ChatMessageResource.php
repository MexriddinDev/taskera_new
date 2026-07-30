<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'conversation_id' => $this->conversation_id,
            'sender_user_id' => $this->sender_user_id,
            'reply_to_id' => $this->reply_to_id,
            'body' => $this->body,
            'source' => $this->source,
            'external_message_id' => $this->external_message_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'reply_to' => new self($this->whenLoaded('replyTo')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
