<?php

namespace App\Modules\Asset\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ip_addresses' => 'array',
            'mac_addresses' => 'array',
            'attributes' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'model_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Employee::class, 'owner_employee_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Employee::class, 'custodian_employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Department::class, 'department_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Branch::class, 'branch_id');
    }
}
