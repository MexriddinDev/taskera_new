<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model
{
    public $timestamps = false;

    protected $table = 'ticket_statuses';

    protected $fillable = [
        'code',
        'name',
        'status_group',
        'is_initial',
        'is_terminal',
        'pauses_sla',
        'customer_visible',
        'sort_order',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_initial' => 'boolean',
            'is_terminal' => 'boolean',
            'pauses_sla' => 'boolean',
            'customer_visible' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
