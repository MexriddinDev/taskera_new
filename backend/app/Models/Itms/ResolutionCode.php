<?php

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ResolutionCode extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'resolution_codes';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'requires_note',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'requires_note' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
