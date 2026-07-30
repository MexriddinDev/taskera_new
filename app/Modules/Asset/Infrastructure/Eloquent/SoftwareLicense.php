<?php

namespace App\Modules\Asset\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SoftwareLicense extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cost' => 'decimal:4',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Organization::class);
    }

    public function softwareProduct()
    {
        return $this->belongsTo(SoftwareProduct::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
