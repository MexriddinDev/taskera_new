<?php

namespace App\Modules\Telegram\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telegram\Infrastructure\Jobs\ProcessTelegramUpdateJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelegramWebhookController extends Controller
{
    public function handleWebhook(Request $request, string $botUsername): JsonResponse
    {
        $payload = $request->all();
        $updateId = $payload['update_id'] ?? null;
        if (! $updateId) {
            return response()->json(['status' => 'invalid'], 400);
        }

        $bot = DB::table('telegram_bots')->where('username', $botUsername)->whereNull('deleted_at')->first();
        if (! $bot) {
            return response()->json(['status' => 'bot_not_found'], 404);
        }

        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($bot->webhook_secret_hash && hash('sha256', (string) $secret) !== $bot->webhook_secret_hash) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        DB::table('telegram_updates')->insertOrIgnore([
            'organization_id' => $bot->organization_id,
            'bot_id' => $bot->id,
            'update_id' => $updateId,
            'update_type' => isset($payload['message']) ? 'message' : 'callback_query',
            'chat_id' => (string) ($payload['message']['chat']['id'] ?? $payload['callback_query']['message']['chat']['id'] ?? ''),
            'message_id' => isset($payload['message'])
                ? ($payload['message']['message_id'] ?? null)
                : ($payload['callback_query']['message']['message_id'] ?? null),
            'payload' => json_encode($payload),
            'payload_hash' => hash('sha256', json_encode($payload)),
            'received_at' => now(),
            'status' => 'PENDING',
        ]);

        $stored = DB::table('telegram_updates')
            ->where('bot_id', $bot->id)
            ->where('update_id', $updateId)
            ->first();

        if (!$stored) {
            return response()->json(['status' => 'error'], 500);
        }

        if ($stored->status === 'PROCESSED') {
            return response()->json(['status' => 'duplicate']);
        }

        dispatch(new ProcessTelegramUpdateJob($stored->id));

        return response()->json(['status' => 'ok']);
    }
}
