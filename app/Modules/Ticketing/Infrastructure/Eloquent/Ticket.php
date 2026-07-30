<?php

namespace App\Modules\Ticketing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use App\Modules\Organization\Infrastructure\Eloquent\Department;
use App\Modules\Organization\Infrastructure\Eloquent\Employee;
use App\Modules\Identity\Infrastructure\Eloquent\User;

class Ticket extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function requesterUser()
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function requesterEmployee()
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function statusHistory()
    {
        return $this->hasMany(TicketStatusHistory::class);
    }
}
