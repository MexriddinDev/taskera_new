<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramApiClient
{
    public const API_BASE = 'https://api.telegram.org';

    private const DEFAULT_TIMEOUT = 30;

    /**
     * Long poll'da HTTP timeout Telegram ushlab turadigan vaqtdan katta bo'lishi shart,
     * aks holda mijoz javob kelishidan oldin ulanishni uzib yuboradi.
     */
    private const READ_TIMEOUT_MARGIN = 15;

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

    /**
     * Long polling: Telegram so'rovni $timeout soniyagacha ushlab turadi va
     * yangilanish paydo bo'lishi bilan qaytaradi (bo'sh sikl aylanmaydi).
     *
     * Webhook bilan bir xil turdagi yangilanishlar so'raladi — ikkala yo'l ham
     * ProcessTelegramUpdateJob orqali bir xil qayta ishlanadi.
     *
     * @return array<int, array<string, mixed>> update'lar ro'yxati
     */
    public function getUpdates(int $offset = 0, int $timeout = 30, int $limit = 100): array
    {
        $result = $this->get('/getUpdates', array_filter([
            'offset' => $offset > 0 ? $offset : null,
            'timeout' => $timeout,
            'limit' => $limit,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ], fn ($v) => $v !== null), $timeout + self::READ_TIMEOUT_MARGIN);

        if (array_is_list($result)) {
            return $result;
        }

        // Xatoda decode() javob tanasini qaytaradi. Uni jimgina bo'sh ro'yxatga
        // aylantirish mumkin emas: masalan webhook yoqiq bo'lsa Telegram
        // "409 Conflict: can't use getUpdates while webhook is active" deydi va
        // polling sikli sababini ko'rsatmay abadiy bo'sh aylanardi.
        throw new \RuntimeException(
            (string) ($result['description'] ?? 'getUpdates muvaffaqiyatsiz: '.json_encode($result)),
            (int) ($result['error_code'] ?? 0)
        );
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

    /**
     * Media yuborish. $file — yo Telegram file_id (bot orqali kelgan fayl uchun,
     * qayta yuklash shart emas), yo ['contents' => binar, 'filename' => nom]
     * (saytdan yuklangan fayl uchun).
     *
     * @param  string|array{contents: string, filename: string}  $file
     */
    public function sendMedia(string $chatId, string $method, string $field, string|array $file, ?string $caption = null): array
    {
        $params = array_filter([
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => $caption !== null ? 'HTML' : null,
        ], fn ($v) => $v !== null);

        if (is_string($file)) {
            return $this->post('/'.$method, $params + [$field => $file]);
        }

        // Fayl tanasi bilan yuborish — multipart
        $response = Http::timeout(120)
            ->attach($field, $file['contents'], $file['filename'])
            ->post($this->url('/'.$method), $params);

        return $this->decode($response, $method);
    }

    public function getFile(string $fileId): array
    {
        return $this->get('/getFile', ['file_id' => $fileId]);
    }

    public function downloadFile(string $filePath): ?string
    {
        // DIQQAT: fayl yuklab olish manzili method manzilidan BOSHQACHA.
        //   metodlar: https://api.telegram.org/bot<token>/<method>
        //   fayllar:  https://api.telegram.org/file/bot<token>/<file_path>
        //
        // Ilgari bu yerda url() helperi ishlatilardi va u o'z navbatida
        // '/bot<token>' qo'shardi — natijada token ikki marta tushib, manzil
        // buzilardi. Fayl har doim bo'sh qaytar, biriktirmalar esa jimgina
        // yo'qolardi.
        $url = self::API_BASE.'/file/bot'.$this->token.'/'.ltrim($filePath, '/');

        $response = Http::timeout(60)->get($url);

        if (! $response->successful()) {
            Log::error('Telegram fayl yuklab olinmadi', [
                'status' => $response->status(),
                'file_path' => $filePath,
            ]);

            return null;
        }

        return $response->body();
    }

    private function get(string $method, array $params = [], ?int $timeout = null): array
    {
        return $this->decode(Http::timeout($timeout ?? self::DEFAULT_TIMEOUT)->get($this->url($method), $params), $method);
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
