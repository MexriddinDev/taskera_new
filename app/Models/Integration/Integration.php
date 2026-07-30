<?php

namespace App\Models\Integration;

use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Integration extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'integration_type_id' => 'integer',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function logs()
    {
        return $this->hasMany(IntegrationLog::class);
    }
}
