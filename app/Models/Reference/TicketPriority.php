<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class TicketPriority extends Model
{
    public $timestamps = false;

    protected $table = 'ticket_priorities';

    protected $fillable = [
        'code',
        'name',
        'weight',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
