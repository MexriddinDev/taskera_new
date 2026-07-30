<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BusinessCalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->name,
            'timezone_id' => $this->timezone_id,
            'is_24x7' => $this->is_24x7,
            'is_active' => $this->is_active,
            'business_hours' => BusinessHourResource::collection($this->whenLoaded('businessHours')),
            'holidays' => CalendarHolidayResource::collection($this->whenLoaded('holidays')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
