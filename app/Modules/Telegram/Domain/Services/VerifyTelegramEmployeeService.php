<?php

namespace App\Modules\Telegram\Domain\Services;

use App\Modules\Organization\Domain\Repositories\EmployeeDirectoryRepositoryInterface;
use App\Modules\Identity\Infrastructure\Eloquent\TelegramAccount;

class VerifyTelegramEmployeeService
{
    public function __construct(
        private EmployeeDirectoryRepositoryInterface $employeeDirectoryRepository
    ) {}

    public function verify(int $organizationId, string $employeeNo, string $telegramUserId): bool
    {
        $employee = $this->employeeDirectoryRepository->verifyActiveEmployee($organizationId, $employeeNo);
        if (!$employee) {
            return false;
        }

        $telegramAccount = TelegramAccount::where('organization_id', $organizationId)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if (!$telegramAccount) {
            return false;
        }

        $telegramAccount->employee_id = $employee->id;
        $telegramAccount->verified_at = now();
        return $telegramAccount->save();
    }
}
