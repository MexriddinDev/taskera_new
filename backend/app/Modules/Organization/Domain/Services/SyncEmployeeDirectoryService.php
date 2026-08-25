<?php

namespace App\Modules\Organization\Domain\Services;

use App\Modules\Organization\Infrastructure\Eloquent\Employee;
use Illuminate\Support\Facades\DB;

class SyncEmployeeDirectoryService
{
    public function sync(int $organizationId, array $employeeData): Employee
    {
        return DB::transaction(function () use ($organizationId, $employeeData) {
            return Employee::updateOrCreate(
                ['organization_id' => $organizationId, 'employee_no' => $employeeData['employee_no']],
                $employeeData
            );
        });
    }
}
