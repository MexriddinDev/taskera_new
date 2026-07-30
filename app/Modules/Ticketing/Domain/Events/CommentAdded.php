<?php
namespace App\Modules\Ticketing\Domain\Events;
use App\Modules\Ticketing\Infrastructure\Eloquent\Comment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class CommentAdded {
    use Dispatchable, SerializesModels;
    public function __construct(public Comment $comment) {}
}
