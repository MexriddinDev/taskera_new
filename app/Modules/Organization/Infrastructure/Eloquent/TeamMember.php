<?php

namespace App\Modules\Organization\Infrastructure\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
