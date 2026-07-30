<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserNotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'event_type' => $this->event_type,
            'channel_id' => $this->channel_id,
            'is_enabled' => $this->is_enabled,
            'quiet_hours' => $this->quiet_hours,
        ];
    }
}
