<?php

namespace App\Modules\Organization\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Branch extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
