<?php

namespace App\Modules\SLA\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TicketSla extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'calculation_snapshot' => 'array',
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'warning_at' => 'datetime',
        'completed_at' => 'datetime',
        'breached_at' => 'datetime',
        'paused_at' => 'datetime',
        'paused_seconds' => 'integer',
        'remaining_seconds' => 'integer',
        'lock_version' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function slaTarget()
    {
        return $this->belongsTo(SlaTarget::class, 'sla_target_id');
    }
}
