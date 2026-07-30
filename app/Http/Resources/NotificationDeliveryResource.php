<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'notification_id' => $this->notification_id,
            'channel_id' => $this->channel_id,
            'recipient' => $this->recipient,
            'status' => $this->status,
            'provider_message_id' => $this->provider_message_id,
            'attempt_count' => $this->attempt_count,
            'next_attempt_at' => $this->next_attempt_at,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'failed_at' => $this->failed_at,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'provider_response' => $this->provider_response,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
