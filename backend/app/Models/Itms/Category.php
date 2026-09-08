<?php

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'categories';

    protected $fillable = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'description',
        'default_team_id',
        'default_priority_id',
        'sla_accept_minutes',
        'sla_work_minutes',
        'sla_close_minutes',
        'is_active',
        'sort_order',
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
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'default_priority_id' => 'integer',
            'sla_accept_minutes' => 'integer',
            'sla_work_minutes' => 'integer',
            'sla_close_minutes' => 'integer',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
