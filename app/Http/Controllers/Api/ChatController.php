<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\Collaboration\ChatConversation;
use App\Models\Collaboration\ChatMessage;
use App\Models\Collaboration\ChatParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $conversations = ChatConversation::query()
            ->with('participants')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->type))
            ->when($request->filled('user_id'), fn($q) => $q->whereHas('participants', fn($p) => $p->where('user_id', $request->user_id)))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => ChatConversationResource::collection($conversations),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:DIRECT,GROUP',
            'title' => 'nullable|string|max:255',
            'linked_type' => 'nullable|string|max:100',
            'linked_id' => 'nullable|integer',
            'participant_user_ids' => 'required|array|min:1',
            'participant_user_ids.*' => 'integer|exists:users,id',
        ]);

        $conversation = ChatConversation::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'type' => $validated['type'],
            'title' => $validated['title'] ?? null,
            'linked_type' => $validated['linked_type'] ?? null,
            'linked_id' => $validated['linked_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        foreach ($validated['participant_user_ids'] as $userId) {
            ChatParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'role' => $userId === $request->user()->id ? 'OWNER' : 'MEMBER',
                'joined_at' => now(),
            ]);
        }

        $conversation->load('participants');

        return response()->json([
            'data' => new ChatConversationResource($conversation),
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $conversation = ChatConversation::with('participants')->findOrFail($id);

        return response()->json([
            'data' => new ChatConversationResource($conversation),
        ]);
    }

    public function messages(Request $request, $id): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 200);

        $messages = ChatMessage::with('sender')
            ->where('conversation_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => ChatMessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function sendMessage(Request $request, $id): JsonResponse
    {
        $conversation = ChatConversation::findOrFail($id);

        $validated = $request->validate([
            'body' => 'required|string',
            'reply_to_id' => 'nullable|integer|exists:chat_messages,id',
            'source' => 'nullable|string|max:32',
            'external_message_id' => 'nullable|string|max:128',
        ]);

        $message = ChatMessage::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'sender_user_id' => $request->user()->id,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
            'body' => $validated['body'],
            'source' => $validated['source'] ?? 'WEB',
            'external_message_id' => $validated['external_message_id'] ?? null,
        ]);

        $message->load('sender');

        return response()->json([
            'data' => new ChatMessageResource($message),
        ], 201);
    }
}
