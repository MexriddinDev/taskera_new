<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KnowledgeArticleResource;
use App\Modules\Knowledge\Infrastructure\Eloquent\KnowledgeArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $articles = KnowledgeArticle::query()
            ->with('author')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('visibility'), fn($q) => $q->where('visibility', $request->visibility))
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('article_type_id'), fn($q) => $q->where('article_type_id', $request->article_type_id))
            ->when($request->filled('is_active'), fn($q) => $request->boolean('is_active') ? $q->whereNull('deleted_at') : $q->onlyTrashed())
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('summary', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => KnowledgeArticleResource::collection($articles),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_type_id' => 'required|integer|exists:article_types,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'required|string',
            'content_format' => 'required|string|in:MARKDOWN,HTML',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            'visibility' => 'nullable|string|in:INTERNAL,PUBLIC,CUSTOMER',
            'expires_at' => 'nullable|date',
            'review_due_at' => 'nullable|date',
        ]);

        $orgId = (int) $request->header('X-Organization-Id', 1);

        $article = KnowledgeArticle::create([
            'organization_id' => $orgId,
            'article_no' => 'KB-' . strtoupper(Str::random(10)),
            'article_type_id' => $validated['article_type_id'],
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'content' => $validated['content'],
            'content_format' => $validated['content_format'],
            'status' => $validated['status'] ?? 'DRAFT',
            'visibility' => $validated['visibility'] ?? 'INTERNAL',
            'author_user_id' => $request->user()->id,
            'version' => 1,
            'expires_at' => $validated['expires_at'] ?? null,
            'review_due_at' => $validated['review_due_at'] ?? null,
        ]);

        return response()->json([
            'data' => new KnowledgeArticleResource($article->load('author')),
        ], 201)->header('X-Organization-Id', (string) $orgId);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $article = KnowledgeArticle::with('author')->findOrFail($id);
        $article->increment('view_count');

        return response()->json([
            'data' => new KnowledgeArticleResource($article),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $article = KnowledgeArticle::findOrFail($id);

        $validated = $request->validate([
            'article_type_id' => 'sometimes|required|integer|exists:article_types,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'title' => 'sometimes|required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'sometimes|required|string',
            'content_format' => 'sometimes|required|string|in:MARKDOWN,HTML',
            'status' => 'nullable|string|in:DRAFT,PUBLISHED,ARCHIVED',
            'visibility' => 'nullable|string|in:INTERNAL,PUBLIC,CUSTOMER',
            'expires_at' => 'nullable|date',
            'review_due_at' => 'nullable|date',
        ]);

        $originalContent = $article->getOriginal('content');
        $article->update($validated);

        if ($article->content !== $originalContent) {
            $article->increment('version');
            $article->refresh();
        }

        return response()->json([
            'data' => new KnowledgeArticleResource($article->load('author')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $article = KnowledgeArticle::findOrFail($id);
        $article->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function publish(Request $request, $id): JsonResponse
    {
        $article = KnowledgeArticle::findOrFail($id);
        $article->update([
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);

        return response()->json([
            'data' => new KnowledgeArticleResource($article->load('author')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function archive(Request $request, $id): JsonResponse
    {
        $article = KnowledgeArticle::findOrFail($id);
        $article->update(['status' => 'ARCHIVED']);

        return response()->json([
            'data' => new KnowledgeArticleResource($article->load('author')),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function feedback(Request $request, $id): JsonResponse
    {
        $request->validate([
            'is_helpful' => 'required|boolean',
            'comment' => 'nullable|string|max:1000',
        ]);

        KnowledgeArticle::findOrFail($id);

        DB::table('knowledge_feedback')->insert([
            'article_id' => $id,
            'user_id' => $request->user()->id,
            'is_helpful' => $request->boolean('is_helpful'),
            'comment' => $request->input('comment'),
            'created_at' => now(),
        ]);

        if ($request->boolean('is_helpful')) {
            KnowledgeArticle::where('id', $id)->increment('helpful_count');
        }

        return response()->json([
            'message' => 'Feedback recorded',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $orgId = (int) $request->header('X-Organization-Id', 1);

        $articles = KnowledgeArticle::with('author')
            ->where('organization_id', $orgId)
            ->where('status', 'PUBLISHED')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('summary', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->orderBy('view_count', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => KnowledgeArticleResource::collection($articles),
        ])->header('X-Organization-Id', (string) $orgId);
    }
}
