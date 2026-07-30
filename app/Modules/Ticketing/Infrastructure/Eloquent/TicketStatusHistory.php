<?php

namespace App\Modules\Ticketing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Identity\Infrastructure\Eloquent\User;

class TicketStatusHistory extends Model
{
    public $timestamps = false;
    protected $table = 'ticket_status_history';
    protected $guarded = ['id'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
