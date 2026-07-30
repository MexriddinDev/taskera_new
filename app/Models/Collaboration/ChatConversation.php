<?php

declare(strict_types=1);

namespace App\Models\Collaboration;

use App\Models\User;
use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ChatConversation extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'chat_conversations';

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
