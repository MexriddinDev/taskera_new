<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterTelegramBot extends Command
{
    protected $signature = 'telegram:register-bot
                            {--token= : Telegram bot token (agar TELEGRAM_BOT_TOKEN env\'da bo\'lmasa)}
                            {--url= : Webhook uchun public URL (masalan https://xyz.ngrok-free.app)}';

    protected $description = 'Telegram botni telegram_bots jadvaliga yozadi va webhook o\'rnatadi';

    public function handle(): int
    {
        $token = $this->option('token') ?: env('TELEGRAM_BOT_TOKEN');
        if (! $token) {
            $this->error('Bot token topilmadi. --token parametri yoki TELEGRAM_BOT_TOKEN env ko\'rsating.');

            return self::FAILURE;
        }

        $api = new TelegramApiClient($token);
        $me = $api->getMe();

        if (empty($me['username']) || empty($me['id'])) {
            $this->error('getMe muvaffaqiyatsiz. Token noto\'g\'ri bo\'lishi mumkin.');
            $this->line(json_encode($me, JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        $username = $me['username'];
        $name = $me['first_name'] ?? $username;
        $webhookSecret = Str::random(48);

        $baseUrl = $this->option('url') ?: rtrim((string) config('app.url'), '/');
        $baseUrl = rtrim($baseUrl, '/');
        $webhookUrl = str_ends_with($baseUrl, '/api/v1/telegram/webhook/'.$username)
            ? $baseUrl
            : $baseUrl.'/api/v1/telegram/webhook/'.$username;

        DB::table('telegram_bots')->updateOrInsert(
            ['organization_id' => 1, 'username' => $username],
            [
                'public_id' => (string) Str::uuid(),
                'name' => $name,
                'token_secret_ref' => $token,
                'webhook_secret_hash' => hash('sha256', $webhookSecret),
                'is_active' => true,
                'settings' => json_encode(['env_token' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->info("Bot DB'ga yozildi: @{$username} ({$name})");

        $result = $api->setWebhook($webhookUrl, $webhookSecret);
        if (($result['ok'] ?? false) === true || ($result['description'] ?? '') === 'Webhook is already set') {
            $this->info("Webhook o'rnatildi: {$webhookUrl}");
        } else {
            $this->warn('Webhook o\'rnatilmadi: '.json_encode($result));
        }

        $this->line('');
        $this->line('Webhook secret (keyingi o\'rnatishda kerak bo\'lmaydi, faqat DB saqlanadi):');
        $this->line('  '.$webhookSecret);

        return self::SUCCESS;
    }
}
