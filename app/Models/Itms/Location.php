<?php

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Location extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'locations';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'parent_id',
        'code',
        'name',
        'floor',
        'room',
        'address',
        'latitude',
        'longitude',
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
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }
}
