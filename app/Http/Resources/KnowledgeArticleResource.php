<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class KnowledgeArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'article_no' => $this->article_no,
            'article_type_id' => $this->article_type_id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'content_format' => $this->content_format,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'author' => new UserResource($this->whenLoaded('author')),
            'version' => $this->version,
            'published_at' => $this->published_at,
            'expires_at' => $this->expires_at,
            'review_due_at' => $this->review_due_at,
            'view_count' => $this->view_count,
            'helpful_count' => $this->helpful_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
