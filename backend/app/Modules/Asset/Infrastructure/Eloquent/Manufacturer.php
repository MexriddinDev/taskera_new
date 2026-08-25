<?php

namespace App\Modules\Asset\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Manufacturer extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Organization::class);
    }

    public function assetModels()
    {
        return $this->hasMany(AssetModel::class);
    }
}
