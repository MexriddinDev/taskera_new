<?php

namespace App\Modules\SLA\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class CalendarHoliday extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'is_working_override' => 'boolean',
        'holiday_date' => 'date',
    ];

    public function calendar()
    {
        return $this->belongsTo(BusinessCalendar::class, 'calendar_id');
    }
}
