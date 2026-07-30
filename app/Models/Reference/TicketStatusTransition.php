<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class TicketStatusTransition extends Model
{
    public $timestamps = false;

    protected $table = 'ticket_status_transitions';

    protected $fillable = [
        'from_status_id',
        'to_status_id',
        'required_permission',
        'requires_comment',
        'requires_resolution',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_comment' => 'boolean',
            'requires_resolution' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
