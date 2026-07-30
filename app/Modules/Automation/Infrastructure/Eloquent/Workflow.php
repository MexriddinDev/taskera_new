<?php

namespace App\Modules\Automation\Infrastructure\Eloquent;

use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Workflow extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'version' => 'integer',
            'published_at' => 'datetime',
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

    public function runs()
    {
        return $this->hasMany(WorkflowRun::class);
    }
}
