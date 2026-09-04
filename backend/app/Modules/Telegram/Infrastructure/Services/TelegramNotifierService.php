<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Services;

use App\Models\User;
use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Telegram orqali foydalanuvchilarga bildirishnoma yuborish.
 */
class TelegramNotifierService
{
    /**
     * @param  array<string, mixed>|null  $replyMarkup  inline tugmalar (masalan baholash/qaytarish)
     */
    public function sendToUser(int $organizationId, int $userId, string $text, ?array $replyMarkup = null): bool
    {
        $account = DB::table('telegram_accounts')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNotNull('verified_at')
            ->whereNull('blocked_at')
            ->orderByDesc('updated_at')
            ->first();

        if (! $account || empty($account->private_chat_id)) {
            // Sabab aniqlanadi: hisob umuman yo'qmi, tasdiqlanmaganmi, bloklanganmi
            // yoki chat id yo'qmi. Bu so'rov faqat xatolik yo'lida bajariladi.
            // Tashkilot bo'yicha filtrlanmaydi — aks holda organization_id mos
            // kelmagan holat ham "hisob yo'q" bo'lib ko'rinardi.
            $any = DB::table('telegram_accounts')
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->first();

            $reason = match (true) {
                $any === null => "telegram hisobi yo'q",
                (int) $any->organization_id !== $organizationId => 'hisob boshqa tashkilotga tegishli (organization_id='.$any->organization_id.')',
                $any->blocked_at !== null => 'hisob bloklangan',
                $any->verified_at === null => 'hisob tasdiqlanmagan (/start qilinmagan yoki /logout)',
                default => "private_chat_id bo'sh",
            };

            Log::info('Telegram bildirishnoma yuborilmadi', [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'reason' => $reason,
            ]);

            return false;
        }

        $bot = DB::table('telegram_bots')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (! $bot) {
            Log::warning('Telegram bildirishnoma yuborilmadi', [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'reason' => 'faol bot topilmadi',
            ]);

            return false;
        }

        try {
            (new TelegramApiClient((string) $bot->token_secret_ref))
                ->sendMessage((string) $account->private_chat_id, $text, $replyMarkup);
        } catch (\Throwable $e) {
            Log::error('Telegram bildirishnoma yuborish xatosi', [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Barcha xodimlarga (ruxsati bor) xabar yuboradi.
     */
    public function sendToStaff(int $organizationId, string $text, ?int $excludeUserId = null): void
    {
        $userIds = DB::table('telegram_accounts')
            ->where('organization_id', $organizationId)
            ->whereNotNull('verified_at')
            ->whereNull('blocked_at')
            ->whereNotNull('private_chat_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($excludeUserId !== null && $userId === $excludeUserId) {
                continue;
            }

            $user = User::query()->find($userId);
            if (! $user || ! $this->isStaff($user)) {
                continue;
            }

            $this->sendToUser($organizationId, $userId, $text);
        }
    }

    public function isStaff(User $user): bool
    {
        return $user->isSupportStaff();
    }
}
