<?php

namespace App\Modules\SLA\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BusinessCalendar extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'is_24x7' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function businessHours()
    {
        return $this->hasMany(BusinessHour::class, 'calendar_id');
    }

    public function holidays()
    {
        return $this->hasMany(CalendarHoliday::class, 'calendar_id');
    }
}
