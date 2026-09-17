<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrEmployeeCondition extends Model
{
    protected $table = 'hr_employee_conditions';

    protected $fillable = [
        'id',
        'code',
        'name_ru',
        'name_uz',
        'condition_type',
        'is_working',
        'can_open_ad',
        'order_by',
        'rowid_ref',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_working' => 'boolean',
            'can_open_ad' => 'boolean',
            'order_by' => 'integer',
        ];
    }

    /** Faol ishlayotgan xodimlar holatlari */
    public function scopeWorking($query)
    {
        return $query->where('is_working', true);
    }

    /** AD ochishga ruxsat berilgan holatlar */
    public function scopeCanOpenAd($query)
    {
        return $query->where('can_open_ad', true);
    }
}
