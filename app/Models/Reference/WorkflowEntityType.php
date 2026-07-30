<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class WorkflowEntityType extends Model
{
    public $timestamps = false;

    protected $table = 'workflow_entity_types';

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
