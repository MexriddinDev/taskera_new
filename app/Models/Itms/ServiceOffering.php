<?php

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ServiceOffering extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'service_offerings';

    protected $fillable = [
        'organization_id',
        'service_id',
        'code',
        'name',
        'description',
        'category_id',
        'default_sla_policy_id',
        'is_requestable',
        'is_active',
        'form_schema',
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
            'is_requestable' => 'boolean',
            'is_active' => 'boolean',
            'form_schema' => 'array',
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
