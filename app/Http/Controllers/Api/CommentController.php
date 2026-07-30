<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Modules\Ticketing\Domain\Services\AddCommentService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Comment;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request, int $ticketId): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $comments = Comment::query()
            ->where('commentable_type', Ticket::class)
            ->where('commentable_id', $ticketId)
            ->with('authorUser')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'data' => CommentResource::collection($comments),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function store(Request $request, int $ticketId, AddCommentService $service): JsonResponse
    {
        $ticket = Ticket::findOrFail($ticketId);

        $validated = $request->validate([
            'body' => 'required|string',
            'body_format' => 'nullable|string|max:16',
            'type_id' => 'nullable|integer|exists:comment_types,id',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        $comment = $service->execute([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'commentable_type' => Ticket::class,
            'commentable_id' => $ticket->id,
            'author_user_id' => $request->user()->id,
            'type_id' => $validated['type_id'] ?? 1,
            'source_id' => 1,
            'body' => $validated['body'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        if (!empty($validated['body_format'])) {
            $comment->body_format = $validated['body_format'];
            $comment->save();
        }

        $comment->load('authorUser');

        return response()->json([
            'data' => new CommentResource($comment),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        if ($comment->author_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($comment->created_at->diffInMinutes(now()) > 15) {
            return response()->json(['message' => 'Editing window has expired'], 422);
        }

        $validated = $request->validate([
            'body' => 'required|string',
            'body_format' => 'nullable|string|max:16',
        ]);

        $comment->body = $validated['body'];
        $comment->body_format = $validated['body_format'] ?? $comment->body_format;
        $comment->edited_at = now();
        $comment->save();

        $comment->load('authorUser');

        return response()->json([
            'data' => new CommentResource($comment),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        if ($comment->author_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
