<?php

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SlaPolicy extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'sla_policies';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'calendar_id',
        'applies_to_type',
        'conditions',
        'is_active',
        'version',
        'effective_from',
        'effective_to',
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
            'conditions' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }
}
