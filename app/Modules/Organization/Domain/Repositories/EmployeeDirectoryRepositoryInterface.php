<?php

namespace App\Modules\Organization\Domain\Repositories;

use App\Modules\Organization\Infrastructure\Eloquent\Employee;

interface EmployeeDirectoryRepositoryInterface
{
    public function verifyActiveEmployee(int $organizationId, string $employeeNo): ?Employee;
    public function syncIdentity(int $employeeId, array $data): bool;
}
