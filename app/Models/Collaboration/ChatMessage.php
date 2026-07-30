<?php

declare(strict_types=1);

namespace App\Models\Collaboration;

use App\Models\User;
use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ChatMessage extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'chat_messages';

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }
}
