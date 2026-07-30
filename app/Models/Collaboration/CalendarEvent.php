<?php

declare(strict_types=1);

namespace App\Models\Collaboration;

use App\Models\User;
use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CalendarEvent extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'calendar_events';

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function eventable()
    {
        return $this->morphTo();
    }
}
