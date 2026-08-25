<?php

namespace App\Modules\Problem\Infrastructure\Eloquent;

use App\Models\User;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Problem extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'known_error' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function tickets()
    {
        return $this->belongsToMany(Ticket::class, 'problem_tickets')
            ->withPivot('relation_type', 'created_at');
    }
}
