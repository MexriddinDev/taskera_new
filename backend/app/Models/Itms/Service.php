<?php

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Service extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'services';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'owner_user_id',
        'support_team_id',
        'criticality',
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
            'criticality' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
