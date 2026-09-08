<?php

namespace App\Modules\SLA\Infrastructure\Listeners;

use App\Modules\Ticketing\Domain\Events\CommentAdded;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Support\Facades\DB;

final class SlaFirstResponseListener
{
    public function handle(CommentAdded $event): void
    {
        $comment = $event->comment;
        if ($comment->commentable_type !== Ticket::class || trim(strip_tags($comment->body)) === '') {
            return;
        }
        $visible = DB::table('comment_types')->where('id', $comment->type_id)->where('code', 'PUBLIC')->value('customer_visible');
        if (!$visible) {
            return;
        }
        DB::transaction(function () use ($comment) {
            $ticket = Ticket::whereKey($comment->commentable_id)->lockForUpdate()->first();
            if ($ticket && !$ticket->first_response_at && $ticket->assigned_user_id &&
                (int) $ticket->assigned_user_id === (int) $comment->author_user_id &&
                (int) $ticket->requester_user_id !== (int) $comment->author_user_id) {
                $ticket->first_response_at = $comment->created_at;
                $ticket->save();
            }
        });
    }
}
