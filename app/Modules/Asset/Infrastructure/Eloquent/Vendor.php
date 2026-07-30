<?php

namespace App\Modules\Asset\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Vendor extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function softwareLicenses()
    {
        return $this->hasMany(SoftwareLicense::class);
    }
}
