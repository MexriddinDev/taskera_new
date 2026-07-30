<?php

namespace App\Modules\Automation\Infrastructure\Eloquent;

use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AutomationRule extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'stop_processing' => 'boolean',
            'priority' => 'integer',
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

    public function executions()
    {
        return $this->hasMany(AutomationExecution::class, 'rule_id');
    }
}
