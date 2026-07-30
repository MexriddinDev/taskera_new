<?php

namespace App\Modules\Telegram\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\Telegram\Infrastructure\Jobs\ProcessTelegramUpdateJob;
use Illuminate\Support\Facades\DB;

class TelegramWebhookController extends Controller
{
    public function handleWebhook(Request $request, string $botUsername): JsonResponse
    {
        $secretHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');
        // Validate webhook secret hash
        
        $payload = $request->all();
        $updateId = $payload['update_id'] ?? null;
        if (!$updateId) {
            return response()->json(['status' => 'invalid'], 400);
        }

        $bot = DB::table('telegram_bots')->where('username', $botUsername)->first();
        if (!$bot) {
            return response()->json(['status' => 'bot_not_found'], 404);
        }

        // Deduplication insert
        $updateDbId = DB::table('telegram_updates')->insertGetId([
            'organization_id' => $bot->organization_id,
            'bot_id' => $bot->id,
            'update_id' => $updateId,
            'update_type' => isset($payload['message']) ? 'message' : 'callback_query',
            'chat_id' => (string) ($payload['message']['chat']['id'] ?? ''),
            'payload' => json_encode($payload),
            'payload_hash' => hash('sha256', json_encode($payload)),
            'received_at' => now(),
            'status' => 'PENDING',
        ]);

        dispatch(new ProcessTelegramUpdateJob($updateDbId));

        return response()->json(['status' => 'ok']);
    }
}
