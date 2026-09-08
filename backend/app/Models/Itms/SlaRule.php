<?php

declare(strict_types=1);

namespace App\Models\Itms;

use App\Modules\Organization\Infrastructure\Eloquent\Team;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SlaRule extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'team_id',
        'name',
        'description',
        'accept_minutes',
        'work_minutes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'accept_minutes' => 'integer',
            'work_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
