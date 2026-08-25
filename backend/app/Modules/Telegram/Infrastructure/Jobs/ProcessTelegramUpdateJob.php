<?php

namespace App\Modules\Telegram\Infrastructure\Jobs;

use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use App\Modules\Telegram\Infrastructure\Services\BotConversationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $telegramUpdateDbId) {}

    public function handle(): void
    {
        $update = DB::table('telegram_updates')->where('id', $this->telegramUpdateDbId)->first();
        if (! $update || $update->status === 'PROCESSED') {
            return;
        }

        try {
            $bot = DB::table('telegram_bots')
                ->where('id', $update->bot_id)
                ->where('is_active', true)
                ->first();

            if (! $bot) {
                $this->markProcessed($update);

                return;
            }

            $payload = json_decode((string) $update->payload, true) ?: [];

            if (isset($payload['message'])) {
                $message = $payload['message'];
                $chatId = (string) ($message['chat']['id'] ?? '');
                $telegramUserId = (string) ($message['from']['id'] ?? '');
                $firstName = (string) ($message['from']['first_name'] ?? '');
                $text = $message['text'] ?? null;

                if ($chatId === '' || $telegramUserId === '') {
                    $this->markProcessed($update);

                    return;
                }

                $api = new TelegramApiClient((string) $bot->token_secret_ref);
                $service = app(BotConversationService::class, ['api' => $api]);
                $service->handle($bot, $chatId, $telegramUserId, $firstName, $text, null, $message);

                DB::table('telegram_updates')->where('id', $update->id)->update([
                    'chat_id' => $chatId,
                    'telegram_account_id' => DB::table('telegram_accounts')
                        ->where('organization_id', $bot->organization_id)
                        ->where('telegram_user_id', $telegramUserId)
                        ->value('id'),
                    'message_id' => $message['message_id'] ?? null,
                ]);
            } elseif (isset($payload['callback_query'])) {
                $callback = $payload['callback_query'];
                $message = $callback['message'] ?? [];
                $chatId = (string) ($message['chat']['id'] ?? '');
                $telegramUserId = (string) ($callback['from']['id'] ?? '');
                $firstName = (string) ($callback['from']['first_name'] ?? '');

                if ($chatId === '' || $telegramUserId === '') {
                    $this->markProcessed($update);

                    return;
                }

                $api = new TelegramApiClient((string) $bot->token_secret_ref);
                $service = app(BotConversationService::class, ['api' => $api]);
                $service->handle($bot, $chatId, $telegramUserId, $firstName, null, $callback);
            }

            $this->markProcessed($update);
        } catch (\Throwable $e) {
            DB::table('telegram_updates')->where('id', $update->id)->update([
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'attempts' => DB::raw('attempts + 1'),
            ]);
            throw $e;
        }
    }

    private function markProcessed(object $update): void
    {
        DB::table('telegram_updates')->where('id', $update->id)->update([
            'status' => 'PROCESSED',
            'processed_at' => now(),
        ]);
    }
}
