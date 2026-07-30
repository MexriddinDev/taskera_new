<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'event_type' => $this->event_type,
            'notifiable_type' => $this->notifiable_type,
            'notifiable_id' => $this->notifiable_id,
            'template_code' => $this->template_code,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'correlation_id' => $this->correlation_id,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'deliveries' => NotificationDeliveryResource::collection($this->whenLoaded('deliveries')),
        ];
    }
}
