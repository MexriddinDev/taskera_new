<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class EmploymentStatus extends Model
{
    public $timestamps = false;

    protected $table = 'employment_statuses';

    protected $fillable = [
        'code',
        'name',
        'can_login',
        'is_terminal',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'can_login' => 'boolean',
            'is_terminal' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
