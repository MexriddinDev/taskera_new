<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class CommentSource extends Model
{
    public $timestamps = false;

    protected $table = 'comment_sources';

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
