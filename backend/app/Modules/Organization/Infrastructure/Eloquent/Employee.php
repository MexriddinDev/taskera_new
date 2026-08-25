<?php

namespace App\Modules\Organization\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Employee extends Model
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

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function employmentStatus()
    {
        return $this->belongsTo(\App\Models\Reference\EmploymentStatus::class, 'employment_status_id');
    }

    public function reports()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->terminated_at === null && $this->deleted_at === null;
    }
}
