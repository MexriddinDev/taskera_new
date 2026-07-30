<?php

namespace App\Modules\Automation\Infrastructure\Eloquent;

use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AutomationExecution extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'executed_at' => 'datetime',
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

    public function rule()
    {
        return $this->belongsTo(AutomationRule::class);
    }
}
