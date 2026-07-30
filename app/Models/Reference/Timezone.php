<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class Timezone extends Model
{
    public $timestamps = false;

    protected $table = 'timezones';

    protected $fillable = [
        'name',
        'utc_offset_hint',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'utc_offset_hint' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
