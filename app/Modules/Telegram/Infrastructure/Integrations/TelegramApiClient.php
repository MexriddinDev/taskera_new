<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramApiClient
{
    public const API_BASE = 'https://api.telegram.org';

    public function __construct(private readonly string $token) {}

    public function getMe(): array
    {
        return $this->get('/getMe');
    }

    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        return $this->get('/setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->get('/deleteWebhook');
    }

    public function sendMessage(
        string $chatId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): array {
        return $this->post('/sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'reply_markup' => $replyMarkup ? json_encode($replyMarkup) : null,
            'disable_web_page_preview' => true,
        ], fn ($v) => $v !== null));
    }

    public function editMessageText(
        string $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): array {
        return $this->post('/editMessageText', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'reply_markup' => $replyMarkup ? json_encode($replyMarkup) : null,
        ], fn ($v) => $v !== null));
    }

    public function deleteMessage(string $chatId, int $messageId): array
    {
        return $this->post('/deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        return $this->post('/answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ], fn ($v) => $v !== null));
    }

    public function getFile(string $fileId): array
    {
        return $this->get('/getFile', ['file_id' => $fileId]);
    }

    public function downloadFile(string $filePath): ?string
    {
        $response = Http::timeout(60)->get($this->url('/file/bot'.$this->token.'/'.$filePath));

        return $response->successful() ? $response->body() : null;
    }

    private function get(string $method, array $params = []): array
    {
        return $this->decode(Http::timeout(30)->get($this->url($method), $params), $method);
    }

    private function post(string $method, array $params = []): array
    {
        return $this->decode(Http::timeout(30)->asForm()->post($this->url($method), $params), $method);
    }

    private function url(string $method): string
    {
        return self::API_BASE.'/bot'.$this->token.$method;
    }

    private function decode(Response $response, string $method): array
    {
        $payload = $response->json() ?? [];
        if (! $response->successful() || ($payload['ok'] ?? false) !== true) {
            Log::error("Telegram API xatosi [{$method}]", [
                'status' => $response->status(),
                'response' => $payload,
            ]);
        }
        $result = $payload['result'] ?? null;

        return is_array($result) ? $result : $payload;
    }
}
