<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Eloquent;

use App\Models\User;
use App\Modules\Organization\Infrastructure\Eloquent\Employee;
use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorEmployee()
    {
        return $this->belongsTo(Employee::class, 'actor_employee_id');
    }
}
