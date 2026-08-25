<?php

namespace App\Modules\Organization\Infrastructure\Repositories;

use App\Modules\Organization\Domain\Repositories\EmployeeDirectoryRepositoryInterface;
use App\Modules\Organization\Infrastructure\Eloquent\Employee;

class EmployeeDirectoryRepository implements EmployeeDirectoryRepositoryInterface
{
    public function verifyActiveEmployee(int $organizationId, string $employeeNo): ?Employee
    {
        return Employee::where('organization_id', $organizationId)
            ->where('employee_no', $employeeNo)
            ->first();
    }

    public function syncIdentity(int $employeeId, array $data): bool
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return false;
        }

        return $employee->update($data);
    }
}
