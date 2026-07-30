<?php

namespace App\Modules\Ticketing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Identity\Infrastructure\Eloquent\User;

class Comment extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
