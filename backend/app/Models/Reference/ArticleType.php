<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class ArticleType extends Model
{
    public $timestamps = false;

    protected $table = 'article_types';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
