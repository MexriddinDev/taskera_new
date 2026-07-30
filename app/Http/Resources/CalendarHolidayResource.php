<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CalendarHolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendar_id' => $this->calendar_id,
            'holiday_date' => $this->holiday_date,
            'name' => $this->name,
            'is_working_override' => $this->is_working_override,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ];
    }
}
