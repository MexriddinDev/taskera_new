<?php
namespace App\Modules\Organization\Domain\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class EmployeeDeactivated {
    use Dispatchable, SerializesModels;
    public function __construct(public int $employeeId, public int $organizationId) {}
}
