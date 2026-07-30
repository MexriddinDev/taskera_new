<?php

namespace App\Modules\Knowledge\Infrastructure\Eloquent;

use App\Models\User;
use App\Models\Reference\ArticleType;
use App\Models\Itms\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class KnowledgeArticle extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'knowledge_articles';

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'review_due_at' => 'datetime',
            'view_count' => 'integer',
            'helpful_count' => 'integer',
            'version' => 'integer',
        ];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function articleType()
    {
        return $this->belongsTo(ArticleType::class, 'article_type_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
