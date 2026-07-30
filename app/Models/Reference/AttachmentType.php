<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class AttachmentType extends Model
{
    public $timestamps = false;

    protected $table = 'attachment_types';

    protected $fillable = [
        'code',
        'name',
        'mime_patterns',
        'max_size_bytes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mime_patterns' => 'array',
            'max_size_bytes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
