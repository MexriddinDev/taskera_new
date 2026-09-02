<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Presentation\Console;

use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use App\Modules\Telegram\Infrastructure\Jobs\ProcessTelegramUpdateJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Telegram yangilanishlarini long polling orqali olib keladi.
 *
 * Nega webhook emas: webhook Telegram bizga murojaat qila olishini, ya'ni
 * ochiq HTTPS manzilni talab qiladi. Bu o'rnatmada bunday manzil yo'q
 * (APP_URL=http://localhost), avvalgi ngrok tunnel esa manzili o'zgarib
 * ketgani uchun ishlamay qolgan. Polling'da ulanishni biz boshlaymiz —
 * hech qanday domen, sertifikat yoki tunnel kerak emas.
 *
 * Yangilanish webhook bilan AYNAN bir xil qayta ishlanadi: avval
 * telegram_updates ga yoziladi (update_id bo'yicha idempotent), keyin
 * ProcessTelegramUpdateJob bajariladi.
 */
class PollTelegramUpdatesCommand extends Command
{
    protected $signature = 'telegram:poll
                            {--bot= : Bot username (bir nechta bot bo\'lsa)}
                            {--timeout=30 : Telegram so\'rovni necha soniya ushlab tursin}
                            {--once : Bir marta so\'rab, to\'xtaydi (sinov uchun)}';

    protected $description = 'Telegram botni long polling rejimida ishga tushiradi';

    /** Ketma-ket xatolarda kutish vaqti (soniya). */
    private const ERROR_BACKOFF = 5;

    private bool $shouldStop = false;

    public function handle(): int
    {
        $bot = $this->resolveBot();
        if (! $bot) {
            return self::FAILURE;
        }

        $api = new TelegramApiClient((string) $bot->token_secret_ref);

        // Webhook yoqiq qolgan bo'lsa Telegram getUpdates'ga 409 Conflict beradi.
        // Buyruq har ishga tushganda uni o'chirib qo'yamiz — shunda bot qaysi
        // rejimda ro'yxatdan o'tganidan qat'i nazar polling ishlaydi.
        $api->deleteWebhook();

        $this->registerSignalHandlers();

        $offset = $this->initialOffset((int) $bot->id);
        $timeout = max(1, (int) $this->option('timeout'));

        $this->info("🤖 @{$bot->username} polling rejimida ishga tushdi (timeout {$timeout}s).");
        $this->line('To\'xtatish uchun Ctrl+C.');

        do {
            try {
                $updates = $api->getUpdates($offset, $timeout);
            } catch (\Throwable $e) {
                $this->error('getUpdates: '.$e->getMessage());
                Log::error('Telegram polling xatosi', ['error' => $e->getMessage()]);

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(self::ERROR_BACKOFF);

                continue;
            }

            foreach ($updates as $update) {
                $updateId = (int) ($update['update_id'] ?? 0);
                if ($updateId === 0) {
                    continue;
                }

                // Offset yangilanish qayta ishlanishidan QAT'I NAZAR suriladi.
                // Aks holda bitta buzuq xabar siklni abadiy bloklab qo'yardi.
                $offset = $updateId + 1;

                $this->processUpdate($bot, $update, $updateId);
            }
        } while (! $this->shouldStop && ! $this->option('once'));

        $this->info('Polling to\'xtatildi.');

        return self::SUCCESS;
    }

    private function processUpdate(object $bot, array $update, int $updateId): void
    {
        try {
            // Webhook controlleridagi bilan bir xil yozuv — ikkala yo'l ham
            // bir xil qatorni hosil qiladi va update_id bo'yicha takrorlanmaydi.
            $payload = json_encode($update);

            DB::table('telegram_updates')->insertOrIgnore([
                'organization_id' => $bot->organization_id,
                'bot_id' => $bot->id,
                'update_id' => $updateId,
                'update_type' => isset($update['message']) ? 'message' : 'callback_query',
                'chat_id' => (string) ($update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? ''),
                'message_id' => isset($update['message'])
                    ? ($update['message']['message_id'] ?? null)
                    : ($update['callback_query']['message']['message_id'] ?? null),
                'payload' => $payload,
                'payload_hash' => hash('sha256', $payload),
                'received_at' => now(),
                'status' => 'PENDING',
            ]);

            $stored = DB::table('telegram_updates')
                ->where('bot_id', $bot->id)
                ->where('update_id', $updateId)
                ->first();

            if (! $stored || $stored->status === 'PROCESSED') {
                return;
            }

            // Sinxron bajaramiz: bot bitta jarayonda ishlasin, alohida
            // `queue:work` kuzatish shart bo'lmasin. Zayavka hodisalaridan
            // kelib chiqadigan bildirishnomalar baribir navbatga tushadi.
            ProcessTelegramUpdateJob::dispatchSync($stored->id);

            $this->line('  ✓ update '.$updateId.' qayta ishlandi');
        } catch (\Throwable $e) {
            $this->warn('  ✗ update '.$updateId.': '.$e->getMessage());
            Log::error('Telegram update qayta ishlashda xato', [
                'update_id' => $updateId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveBot(): ?object
    {
        $query = DB::table('telegram_bots')->where('is_active', true)->whereNull('deleted_at');

        if ($username = $this->option('bot')) {
            $query->where('username', ltrim((string) $username, '@'));
        }

        $bots = $query->orderBy('id')->get();

        if ($bots->isEmpty()) {
            $this->error('Aktiv bot topilmadi. Avval: php artisan telegram:register-bot --polling');

            return null;
        }

        // Bir nechta bot bo'lsa, polling uchun ro'yxatdan o'tganini afzal ko'ramiz —
        // aks holda har safar --bot yozishga to'g'ri kelardi.
        if ($bots->count() > 1 && ! $this->option('bot')) {
            $pollingBots = $bots->filter(
                fn ($b) => (json_decode((string) ($b->settings ?? '{}'), true)['mode'] ?? null) === 'polling'
            );

            if ($pollingBots->count() === 1) {
                return $pollingBots->first();
            }

            $bots = $pollingBots->isNotEmpty() ? $pollingBots : $bots;
        }

        if ($bots->count() > 1) {
            $this->error('Bir nechta aktiv bot bor — qaysi biri kerakligini --bot bilan ko\'rsating:');
            foreach ($bots as $b) {
                $this->line('  --bot='.$b->username);
            }

            return null;
        }

        return $bots->first();
    }

    /**
     * Qayta ishga tushganda o'z joyidan davom etish uchun oxirgi update_id.
     * Alohida holat jadvali kerak emas — telegram_updates o'zi manba.
     */
    private function initialOffset(int $botId): int
    {
        $last = (int) DB::table('telegram_updates')->where('bot_id', $botId)->max('update_id');

        return $last > 0 ? $last + 1 : 0;
    }

    private function registerSignalHandlers(): void
    {
        // pcntl Windows'da mavjud emas — u yerda Ctrl+C jarayonni to'g'ridan-to'g'ri uzadi.
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function (): void {
                $this->shouldStop = true;
                $this->line('');
                $this->line('To\'xtatish signali qabul qilindi, joriy siklni yakunlayapman...');
            });
        }
    }
}
