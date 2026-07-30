<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class CommentType extends Model
{
    public $timestamps = false;

    protected $table = 'comment_types';

    protected $fillable = [
        'code',
        'name',
        'customer_visible',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'customer_visible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
