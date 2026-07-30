<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BusinessHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendar_id' => $this->calendar_id,
            'weekday' => $this->weekday,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_working' => $this->is_working,
        ];
    }
}
