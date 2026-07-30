<?php

declare(strict_types=1);

namespace App\Models\Collaboration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model
{
    protected $table = 'chat_participants';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
