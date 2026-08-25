<?php

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Infrastructure\Eloquent\Comment;
use App\Modules\Ticketing\Domain\Events\CommentAdded;
use Illuminate\Support\Facades\DB;

class AddCommentService
{
    public function execute(array $data): Comment
    {
        return DB::transaction(function () use ($data) {
            $comment = new Comment();
            $comment->organization_id = $data['organization_id'];
            $comment->commentable_type = $data['commentable_type'];
            $comment->commentable_id = $data['commentable_id'];
            $comment->author_user_id = $data['author_user_id'] ?? null;
            $comment->type_id = $data['type_id'] ?? 1; // PUBLIC
            $comment->source_id = $data['source_id'] ?? 1; // WEB
            $comment->body = $data['body'];
            $comment->save();

            event(new CommentAdded($comment));
            return $comment;
        });
    }
}
