<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class AssetStatus extends Model
{
    public $timestamps = false;

    protected $table = 'asset_statuses';

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
