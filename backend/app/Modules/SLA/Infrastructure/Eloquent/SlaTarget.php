<?php

namespace App\Modules\SLA\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class SlaTarget extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'pause_statuses' => 'array',
        'is_active' => 'boolean',
        'target_minutes' => 'integer',
        'warning_minutes' => 'integer',
        'escalation_minutes' => 'integer',
    ];

    public function ticketSlas()
    {
        return $this->hasMany(TicketSla::class, 'sla_target_id');
    }
}
