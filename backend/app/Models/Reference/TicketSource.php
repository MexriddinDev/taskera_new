<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class TicketSource extends Model
{
    public $timestamps = false;

    protected $table = 'ticket_sources';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
