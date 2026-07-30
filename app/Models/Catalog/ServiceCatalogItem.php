<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ServiceCatalogItem extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'form_schema' => 'array',
            'estimated_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
