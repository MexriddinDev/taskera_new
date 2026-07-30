<?php

namespace App\Modules\SLA\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'is_working' => 'boolean',
    ];

    public function calendar()
    {
        return $this->belongsTo(BusinessCalendar::class, 'calendar_id');
    }
}
