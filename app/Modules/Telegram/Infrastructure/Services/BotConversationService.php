<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Services;

use App\Models\User;
use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use App\Modules\Ticketing\Domain\Services\AssignTicketService;
use App\Modules\Ticketing\Domain\Services\TransitionTicketService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Modules\Ticketing\Presentation\Http\Controllers\TicketController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bot suhbat logikasi (state machine).
 */
class BotConversationService
{
    private const STATE_IDLE = 'IDLE';

    private const STATE_AWAIT_USERNAME = 'AWAIT_USERNAME';

    private const STATE_AWAIT_PASSWORD = 'AWAIT_PASSWORD';

    private const STATE_AWAIT_TICKET_TEXT = 'AWAIT_TICKET_TEXT';

    private const STATE_AWAIT_TICKET_CATEGORY = 'AWAIT_TICKET_CATEGORY';

    private const STATE_AWAIT_TICKET_PRIORITY = 'AWAIT_TICKET_PRIORITY';

    private const STATE_AWAIT_TICKET_CONFIRM = 'AWAIT_TICKET_CONFIRM';

    private const STATE_AWAIT_TICKET_REASON = 'AWAIT_TICKET_REASON';

    private const STATE_AWAIT_TICKET_PHONE = 'AWAIT_TICKET_PHONE';

    private const MENU_BUTTONS = [
        '🆕 Yangi zayavka' => 'menu:new_ticket',
        '📋 Mening zayavkalarim' => 'menu:my_tickets',
        '📥 Ochiq zayavkalar' => 'menu:open_tickets',
        '🛠 Mening vazifalarim' => 'menu:my_tasks',
        '📊 Statistika' => 'menu:stats',
    ];

    private const CATEGORIES = [
        'hardware' => ['label' => 'Uskuna (kompyuter, printer va boshqalar)', 'emoji' => '🖥'],
        'software' => ['label' => 'Dasturiy ta\'minot (Windows, dasturlar)', 'emoji' => '💾'],
    ];

    private const PRIORITIES = [
        'low' => ['label' => 'Past', 'emoji' => '🟢'],
        'medium' => ['label' => 'O\'rta', 'emoji' => '🟡'],
        'high' => ['label' => 'Yuqori', 'emoji' => '🟠'],
        'critical' => ['label' => 'Kritik', 'emoji' => '🔴'],
    ];

    private const STATUS_EMOJI = [
        '1' => '🟦', '2' => '🟦', '3' => '🟦',
        '4' => '🟪', '5' => '🟪', '6' => '🟪',
        '7' => '🟩', '8' => '🟩',
        '9' => '🟥',
        '10' => '⬜',
    ];

    public function __construct(
        private readonly TelegramApiClient $api,
        private readonly VerifyBotLoginService $verifyLogin,
    ) {}

    public function handle(
        object $bot,
        string $chatId,
        string $telegramUserId,
        string $firstName,
        ?string $messageText,
        ?array $callback,
        ?array $message = null,
    ): void {
        $session = $this->session($bot, $chatId, $telegramUserId);

        if ($callback !== null) {
            $this->handleCallback($bot, $session, $chatId, $callback);

            return;
        }

        if ($message !== null && ($media = $this->extractMedia($message)) !== null) {
            $this->handleMedia($bot, $session, $chatId, $media);

            return;
        }

        $text = trim((string) $messageText);

        if ($text === '/start') {
            $this->handleStart($bot, $session, $chatId, $firstName);

            return;
        }

        if ($text === '/cancel') {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId, 'Amal bekor qilindi. Bosh menyu:');

            return;
        }

        if ($text === '/logout') {
            $this->logout($bot, $session, $chatId);

            return;
        }

        if ($text === '/help' || $text === 'ℹ️ Yordam') {
            $this->sendHelp($bot, $session, $chatId);

            return;
        }

        if ($text === '🚪 Chiqish') {
            $this->logout($bot, $session, $chatId);

            return;
        }

        if (isset(self::MENU_BUTTONS[$text])) {
            $this->dispatchMenuAction($bot, $session, $chatId, self::MENU_BUTTONS[$text]);

            return;
        }

        $state = $session->state;

        if ($state === self::STATE_AWAIT_USERNAME) {
            $this->onUsername($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_PASSWORD) {
            $this->onPassword($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_TICKET_REASON) {
            $this->onTicketReason($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_TICKET_PHONE) {
            $this->onTicketPhone($bot, $session, $chatId, $text);

            return;
        }

        if ($session->user_id === null) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        if ($state === self::STATE_AWAIT_TICKET_TEXT) {
            $this->onTicketText($bot, $session, $chatId, $text);

            return;
        }

        $this->sendMenu($bot, $session, $chatId, 'Quyidagi bo\'limlardan birini tanlang:');
    }

    private function handleStart(object $bot, object $session, string $chatId, string $firstName): void
    {
        if ($session->user_id !== null) {
            $this->sendMenu($bot, $session, $chatId, 'Xush kelibsiz, '.$this->user($session)?->username.'! 👋');

            return;
        }

        $this->setState($session, self::STATE_AWAIT_USERNAME, []);
        $this->api->sendMessage($chatId,
            '👋 Assalomu alaykum, <b>'.htmlspecialchars($firstName)."</b>!\n\n".
            "Kompyuteringizda muammo bo'lib saytga kira olmayapsizmi? Hechqisi yo'q — shu yerdan zayavka yuborishingiz mumkin.\n\n".
            "🔐 Avval tizimga kirishingiz kerak.\n".
            '📝 Saytdagi <b>loginingizni</b> (username) yozing:'
        );
    }

    private function onUsername(object $bot, object $session, string $chatId, string $text): void
    {
        if (preg_match('/\s+/', $text)) {
            $this->api->sendMessage($chatId, "⚠️ Login bo'sh joysiz bo'lishi kerak. Qaytadan yozing:");

            return;
        }

        $this->setState($session, self::STATE_AWAIT_PASSWORD, ['username' => $text]);
        $this->api->sendMessage($chatId, '🔑 Endi saytdagi <b>parolingizni</b> yozing:');
    }

    private function onPassword(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $username = $data['username'] ?? '';

        $user = $this->verifyLogin->verify($username, $text);

        if (! $user) {
            $this->setState($session, self::STATE_AWAIT_USERNAME, []);
            $this->api->sendMessage($chatId,
                "❌ Login yoki parol noto'g'ri yoki tizimda bunday foydalanuvchi mavjud emas.\n\n".
                "Eslatma: avval saytga kamida bir marta kirib chiqqan bo'lishingiz kerak.\n\n".
                '📝 Qaytadan loginingizni yozing yoki /start ni bosing:'
            );

            return;
        }

        if (strtolower((string) $user->status) !== 'active') {
            $this->setState($session, self::STATE_AWAIT_USERNAME, []);
            $this->api->sendMessage($chatId, "❌ Hisobingiz nofaol holatda. Administrator bilan bog'laning.");

            return;
        }

        $this->linkAccount($bot, $session, $user, $chatId);
        DB::table('telegram_chat_sessions')->where('id', $session->id)->update([
            'user_id' => $user->id,
            'updated_at' => now(),
        ]);
        $session->user_id = $user->id;
        $this->setState($session, self::STATE_IDLE, ['username' => $user->username]);
        $this->api->sendMessage($chatId,
            "✅ <b>Muvaffaqiyatli kirdingiz!</b>\n\n".
            '👤 Foydalanuvchi: <b>'.htmlspecialchars((string) $user->username)."</b>\n\n".
            'Endi zayavka yuborishingiz mumkin.'
        );
        $this->sendMenu($bot, $session, $chatId);
    }

    private function onTicketText(object $bot, object $session, string $chatId, string $text): void
    {
        if (mb_strlen($text) < 3) {
            $this->api->sendMessage($chatId, '⚠️ Muammo tavsifi juda qisqa (kamida 3 ta belgi). Qaytadan yozing:');

            return;
        }
        if (mb_strlen($text) > 2000) {
            $this->api->sendMessage($chatId, '⚠️ Tavsif 2000 belgidan oshib ketdi. Qisqartirib qaytadan yozing:');

            return;
        }

        $data = $this->sessionData($session);
        $data['ticket_text'] = $text;
        $this->setState($session, self::STATE_AWAIT_TICKET_PHONE, $data);

        $this->api->sendMessage($chatId,
            "📱 <b>Telefon raqamingizni</b> kiriting.\n\n".
            "Masalan: <i>+998 90 123 45 67</i> yoki <i>90 123 45 67</i>\n\n".
            '❌ Bekor qilish uchun /cancel yozing.'
        );
    }

    private function onTicketPhone(object $bot, object $session, string $chatId, string $text): void
    {
        $phone = trim($text);
        if (! preg_match('/^\+?[\d\s\-()]{7,32}$/', $phone)) {
            $this->api->sendMessage($chatId, "⚠️ Telefon raqam noto'g'ri. Faqat raqamlar, +, - va bo'sh joylardan foydalaning. Qaytadan:");

            return;
        }

        $data = $this->sessionData($session);
        $data['ticket_phone'] = $phone;
        $this->setState($session, self::STATE_AWAIT_TICKET_PRIORITY, $data);

        $rows = [];
        foreach (self::PRIORITIES as $pKey => $prio) {
            $rows[] = [
                ['text' => $prio['emoji'].' '.$prio['label'], 'callback_data' => 'prio:'.$pKey],
            ];
        }
        $rows[] = [
            ['text' => '❌ Bekor qilish', 'callback_data' => 'cancel'],
        ];

        $this->api->sendMessage($chatId, '⚡ Muammoning <b>muhimlik darajasini</b> tanlang:', [
            'inline_keyboard' => $rows,
        ]);
    }

    private function showCategoryButtons(string $chatId, string $text): void
    {
        $rows = [];
        foreach (self::CATEGORIES as $key => $cat) {
            $rows[] = [
                ['text' => $cat['emoji'].' '.$cat['label'], 'callback_data' => 'cat:'.$key],
            ];
        }
        $rows[] = [
            ['text' => '❌ Bekor qilish', 'callback_data' => 'cancel'],
        ];

        $this->api->sendMessage($chatId, $text, [
            'inline_keyboard' => $rows,
        ]);
    }

    private function extractMedia(array $message): ?array
    {
        if (! empty($message['photo'])) {
            $photo = end($message['photo']);

            return [
                'type' => 'photo',
                'file_id' => $photo['file_id'] ?? null,
                'unique_id' => $photo['file_unique_id'] ?? null,
                'mime' => 'image/jpeg',
                'ext' => 'jpg',
            ];
        }

        if (! empty($message['voice'])) {
            $voice = $message['voice'];

            return [
                'type' => 'voice',
                'file_id' => $voice['file_id'] ?? null,
                'unique_id' => $voice['file_unique_id'] ?? null,
                'mime' => $voice['mime_type'] ?? 'audio/ogg',
                'ext' => 'ogg',
            ];
        }

        if (! empty($message['document'])) {
            $doc = $message['document'];
            $name = $doc['file_name'] ?? null;
            $ext = $name ? (pathinfo($name, PATHINFO_EXTENSION) ?: 'bin') : 'bin';

            return [
                'type' => 'document',
                'file_id' => $doc['file_id'] ?? null,
                'unique_id' => $doc['file_unique_id'] ?? null,
                'mime' => $doc['mime_type'] ?? 'application/octet-stream',
                'ext' => $ext,
                'name' => $name,
            ];
        }

        if (! empty($message['video'])) {
            $video = $message['video'];

            return [
                'type' => 'video',
                'file_id' => $video['file_id'] ?? null,
                'unique_id' => $video['file_unique_id'] ?? null,
                'mime' => $video['mime_type'] ?? 'video/mp4',
                'ext' => 'mp4',
            ];
        }

        return null;
    }

    private function handleMedia(object $bot, object $session, string $chatId, array $media): void
    {
        $state = $session->state;
        $ticketStates = [
            self::STATE_AWAIT_TICKET_TEXT,
            self::STATE_AWAIT_TICKET_PHONE,
        ];

        if (! in_array($state, $ticketStates, true)) {
            $this->api->sendMessage($chatId,
                "🖼 Rasm, ovoz va fayllar faqat zayavka yaratish jarayonida qabul qilinadi.\n\n".
                "'🆕 Yangi zayavka' tugmasini bosing."
            );

            return;
        }

        if (empty($media['file_id'])) {
            return;
        }

        $data = $this->sessionData($session);
        $data['media'] = $data['media'] ?? [];
        $data['media'][] = $media;
        $this->setState($session, $state, $data);

        $typeLabels = ['photo' => 'Rasm 🖼', 'voice' => 'Ovozli xabar 🎤', 'document' => 'Fayl 📎', 'video' => 'Video 🎬'];
        $label = $typeLabels[$media['type']] ?? 'Fayl';
        $count = count($data['media']);

        $this->api->sendMessage($chatId,
            "✅ {$label} qo'shildi (jami: {$count}).\n\n".
            "Yana rasm/ovoz yuborishingiz yoki so'ralgan ma'lumotni yozib davom etishingiz mumkin."
        );
    }

    private function attachMedia(object $bot, User $user, int $ticketId, array $media): void
    {
        foreach ($media as $item) {
            try {
                $file = $this->api->getFile((string) ($item['file_id'] ?? ''));
                $filePath = $file['file_path'] ?? null;

                if (! $filePath) {
                    continue;
                }

                $content = $this->api->downloadFile((string) $filePath);

                if ($content === null || $content === '') {
                    continue;
                }

                $ext = (string) ($item['ext'] ?? 'bin');
                $safeName = Str::uuid().'.'.$ext;
                $storagePath = 'attachments/'.date('Y/m/d').'/'.$safeName;

                try {
                    Storage::disk('public')->put($storagePath, $content);
                    $disk = 'public';
                } catch (\Throwable) {
                    Storage::disk('local')->put($storagePath, $content);
                    $disk = 'local';
                }

                $typeCodeMap = ['photo' => 'IMAGE', 'voice' => 'AUDIO', 'document' => 'FILE', 'video' => 'VIDEO'];
                $attachmentTypeId = DB::table('attachment_types')
                    ->where('code', $typeCodeMap[$item['type'] ?? 'document'] ?? 'FILE')
                    ->value('id') ?? 1;

                DB::table('attachments')->insert([
                    'organization_id' => $bot->organization_id,
                    'public_id' => (string) Str::uuid(),
                    'attachable_type' => Ticket::class,
                    'attachable_id' => $ticketId,
                    'attachment_type_id' => $attachmentTypeId,
                    'uploaded_by' => $user->id,
                    'source_id' => 2,
                    'storage_disk' => $disk,
                    'storage_path' => $storagePath,
                    'original_name' => $item['name'] ?? ('telegram_'.($item['type'] ?? 'file').'.'.$ext),
                    'safe_name' => $safeName,
                    'mime_type' => $item['mime'] ?? 'application/octet-stream',
                    'extension' => $ext,
                    'size_bytes' => strlen($content),
                    'sha256' => hash('sha256', $content),
                    'telegram_file_id' => $item['file_id'] ?? null,
                    'telegram_file_unique_id' => $item['unique_id'] ?? null,
                    'antivirus_status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Bot media biriktirish xatosi', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
            }
        }
    }

    private function onPriority(object $bot, object $session, string $chatId, array $data, string $priority): void
    {
        $data['ticket_priority'] = $priority;
        $this->setState($session, self::STATE_AWAIT_TICKET_CONFIRM, $data);
        $this->showConfirm($bot, $session, $chatId, $data);
    }

    private function showConfirm(object $bot, object $session, string $chatId, array $data): void
    {
        $text = Str::limit((string) ($data['ticket_text'] ?? ''), 300);
        $category = self::CATEGORIES[$data['ticket_category'] ?? ''] ?? null;
        $priority = self::PRIORITIES[$data['ticket_priority'] ?? ''] ?? null;
        $phone = (string) ($data['ticket_phone'] ?? '');
        $media = $data['media'] ?? [];
        $mediaText = '';

        if (count($media) > 0) {
            $photos = count(array_filter($media, fn ($m) => $m['type'] === 'photo'));
            $voices = count(array_filter($media, fn ($m) => $m['type'] === 'voice'));
            $files = count($media) - $photos - $voices;
            $parts = [];
            if ($photos > 0) {
                $parts[] = "🖼 {$photos}";
            }
            if ($voices > 0) {
                $parts[] = "🎤 {$voices}";
            }
            if ($files > 0) {
                $parts[] = "📎 {$files}";
            }
            $mediaText = "\n📎 <b>Fayllar:</b> ".implode(' | ', $parts);
        }

        $message =
            "📋 <b>Zayavka ma'lumotlari</b>\n\n".
            "📝 <b>Tavsif:</b>\n".htmlspecialchars($text)."\n".
            '📂 <b>Soha:</b> '.($category ? $category['emoji'].' '.$category['label'] : '-')."\n".
            '📱 <b>Telefon:</b> '.htmlspecialchars($phone ?: '-')."\n".
            '⚡ <b>Muhimlik:</b> '.($priority ? $priority['emoji'].' '.$priority['label'] : '-').$mediaText."\n\n".
            "Hammasi to'g'rimi?";

        $this->api->sendMessage($chatId, $message, [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Tasdiqlash', 'callback_data' => 'confirm'],
                    ['text' => '❌ Bekor qilish', 'callback_data' => 'cancel'],
                ],
            ],
        ]);
    }

    private function createTicket(object $bot, object $session, string $chatId, array $data): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendMenu($bot, $session, $chatId, 'Sessiya tugagan. Qaytadan kiring:');

            return;
        }

        $todo = (string) ($data['ticket_text'] ?? '');
        $category = (string) ($data['ticket_category'] ?? 'hardware');
        $priority = (string) ($data['ticket_priority'] ?? 'medium');
        $phone = (string) ($data['ticket_phone'] ?? '');
        $media = $data['media'] ?? [];
        $categoryLabel = self::CATEGORIES[$category]['label'] ?? 'Boshqa';

        try {
            Auth::loginUsingId($user->id);
            $request = Request::create('/api/v1/tickets', 'POST', [
                'todo' => $todo,
                'targetDepartment' => $category,
                'category' => $categoryLabel,
                'priority' => $priority,
                'initiatorPhone' => $phone,
                'initiatorName' => $user->username,
            ]);

            $response = app(TicketController::class)->store($request);
            $json = $response->getData(true);
            $ticket = $json['data'] ?? $json;

            $ticketId = $ticket['id'] ?? null;
            $ticketNo = $ticket['ticketNumber'] ?? $ticket['ticket_no'] ?? null;

            if ($ticketNo) {
                if ($ticketId) {
                    DB::table('tickets')->where('id', (int) $ticketId)->update([
                        'source_id' => 2,
                        'telegram_chat_id' => (string) $chatId,
                        'updated_at' => now(),
                    ]);
                    DB::table('ticket_status_history')
                        ->where('ticket_id', (int) $ticketId)
                        ->where('action', 'TICKET_CREATED')
                        ->update(['source_id' => 2]);
                    $this->attachMedia($bot, $user, (int) $ticketId, $media);
                }

                $mediaHint = count($media) > 0 ? "\n📎 <b>Fayllar:</b> ".count($media).' ta biriktirildi' : '';
                $this->api->sendMessage($chatId,
                    "✅ <b>Zayavka muvaffaqiyatli yuborildi!</b>\n\n".
                    '🎫 <b>Raqam:</b> <code>'.htmlspecialchars((string) $ticketNo)."</code>\n".
                    '📝 <b>Tavsif:</b> '.htmlspecialchars(Str::limit($todo, 100))."\n".
                    '📂 <b>Soha:</b> '.htmlspecialchars($categoryLabel)."\n".
                    '📱 <b>Telefon:</b> '.htmlspecialchars($phone ?: '-')."\n".
                    '⚡ <b>Muhimlik:</b> '.self::PRIORITIES[$priority]['emoji'].' '.self::PRIORITIES[$priority]['label'].$mediaHint."\n".
                    "📌 <b>Holat:</b> 🟦 Yangi\n\n".
                    'Zayavkangiz IT xodimlariga yuborildi. Holatini sayt yoki shu bot orqali kuzatishingiz mumkin.'
                );
            } else {
                $message = $json['message'] ?? 'Zayavka yaratishda xatolik yuz berdi.';
                $this->api->sendMessage($chatId, '⚠️ '.htmlspecialchars((string) $message));
            }
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->api->sendMessage($chatId, '⚠️ '.htmlspecialchars((string) ($firstError ?? 'Ma\'lumotlar noto\'g\'ri.')));
        } catch (\Throwable $e) {
            Log::error('Bot zayavka yaratish xatosi', ['error' => $e->getMessage()]);
            $this->api->sendMessage($chatId, "⚠️ Zayavka yaratishda xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");
        } finally {
            Auth::logout();
            $this->resetSession($session);
        }

        $this->sendMenu($bot, $session, $chatId);
    }

    private function handleCallback(object $bot, object $session, string $chatId, array $callback): void
    {
        $callbackId = $callback['id'] ?? '';
        $data = $callback['data'] ?? '';
        $this->api->answerCallbackQuery($callbackId);

        if ($data === 'cancel') {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId, 'Zayavka bekor qilindi. Bosh menyu:');

            return;
        }

        $this->dispatchMenuAction($bot, $session, $chatId, $data, $callbackId);

        if ($session->user_id === null) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        if (str_starts_with($data, 'ticket:open:')) {
            $this->showTicketDetail($bot, $session, $chatId, (int) substr($data, 12));

            return;
        }

        if (str_starts_with($data, 'ticket:take:')) {
            $this->takeTicket($bot, $session, $chatId, (int) substr($data, 12));

            return;
        }

        if (str_starts_with($data, 'ticket:start:')) {
            $this->startTicket($bot, $session, $chatId, (int) substr($data, 13));

            return;
        }

        if (str_starts_with($data, 'ticket:resolve:')) {
            $this->resolveTicket($bot, $session, $chatId, (int) substr($data, 15));

            return;
        }

        if (str_starts_with($data, 'ticket:reject:')) {
            $this->rejectTicket($bot, $session, $chatId, (int) substr($data, 14));

            return;
        }

        if (str_starts_with($data, 'cat:')) {
            $key = substr($data, 4);
            if (! isset(self::CATEGORIES[$key])) {
                return;
            }
            $sessionData = $this->sessionData($session);
            $sessionData['ticket_category'] = $key;
            $this->setState($session, self::STATE_AWAIT_TICKET_TEXT, $sessionData);
            $this->api->sendMessage($chatId,
                "📝 Endi <b>muammoingizni yozing</b>.\n\n".
                "Masalan: <i>\"Kompyuterim yoqilmayapti, quvvat tugmasi ishlamayapti\"</i> yoki <i>\"Word dasturi ochilmayapti\"</i>\n\n".
                "🖼 Rasm yoki 🎤 ovozli xabar ham yuborishingiz mumkin — zayavkaga biriktiriladi.\n\n".
                '❌ Bekor qilish uchun /cancel yozing.',
                ['remove_keyboard' => true]
            );

            return;
        }

        if (str_starts_with($data, 'prio:')) {
            $key = substr($data, 5);
            if (! isset(self::PRIORITIES[$key])) {
                return;
            }
            $sessionData = $this->sessionData($session);
            $this->onPriority($bot, $session, $chatId, $sessionData, $key);

            return;
        }

        if ($data === 'confirm') {
            $sessionData = $this->sessionData($session);
            if (empty($sessionData['ticket_text']) || empty($sessionData['ticket_category']) || empty($sessionData['ticket_priority'])) {
                $this->setState($session, self::STATE_AWAIT_TICKET_TEXT, []);
                $this->api->sendMessage($chatId, "⚠️ Zayavka ma'lumotlari to'liq emas. Qaytadan muammoni yozing:");

                return;
            }
            $this->createTicket($bot, $session, $chatId, $sessionData);
        }
    }

    private function dispatchMenuAction(object $bot, object $session, string $chatId, string $data, string $callbackId = ''): void
    {
        if ($data === 'menu:logout') {
            $this->logout($bot, $session, $chatId);

            return;
        }

        if ($data === 'menu:new_ticket') {
            if ($session->user_id === null) {
                $this->sendLoginPrompt($bot, $chatId);

                return;
            }
            $this->setState($session, self::STATE_AWAIT_TICKET_CATEGORY, []);
            $this->showCategoryButtons($chatId, "📂 Muammo <b>qaysi sohaga</b> tegishli?\n\nBirinchi guruhni tanlang:");

            return;
        }

        if ($data === 'menu:my_tickets') {
            $this->showMyTickets($bot, $session, $chatId);

            return;
        }

        if (in_array($data, ['menu:open_tickets', 'menu:my_tasks', 'menu:stats'], true)) {
            $user = $this->user($session);

            if (! $user || ! $this->isStaff($user)) {
                if ($callbackId !== '') {
                    $this->api->answerCallbackQuery($callbackId, '❌ Bu boʻlim faqat xodimlar uchun.');
                } else {
                    $this->api->sendMessage($chatId, "❌ Bu bo'lim faqat xodimlar uchun.");
                }

                return;
            }

            if ($data === 'menu:open_tickets') {
                $this->showOpenTickets($bot, $session, $chatId);
            } elseif ($data === 'menu:my_tasks') {
                $this->showMyTasks($bot, $session, $chatId);
            } else {
                $this->showStats($bot, $session, $chatId);
            }

            return;
        }

        if ($data === 'menu:home') {
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        if ($data === 'menu:help') {
            $this->sendHelp($bot, $session, $chatId);
        }
    }

    private function showMyTickets(object $bot, object $session, string $chatId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        $tickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->whereNull('tickets.deleted_at')
            ->where('tickets.requester_user_id', $user->id)
            ->select(
                'tickets.id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.status_id',
                'tickets.created_at',
                'ticket_statuses.name as status_name',
                'ticket_priorities.name as priority_name'
            )
            ->orderByDesc('tickets.created_at')
            ->limit(10)
            ->get();

        if ($tickets->isEmpty()) {
            $this->sendMenu($bot, $session, $chatId, "📭 Sizda hozircha zayavkalar yo'q.\n\nBirinchi zayavkangizni yuborish uchun 🆕 <b>Yangi zayavka</b> tugmasini bosing.");

            return;
        }

        $lines = ['📋 <b>Sizning zayavkalaringiz (oxirgi '.$tickets->count()." ta):</b>\n"];
        foreach ($tickets as $ticket) {
            $statusEmoji = self::STATUS_EMOJI[(string) $ticket->status_id] ?? '▪️';
            $priorityEmoji = '';
            $priority = $ticket->priority_name ? (string) $ticket->priority_name : '';
            $priorityMap = ['Kritik' => '🔴', 'Yuqori' => '🟠', "O'rta" => '🟡', 'Past' => '🟢'];
            $priorityEmoji = $priorityMap[$priority] ?? '';
            $lines[] = $statusEmoji.' <b>'.htmlspecialchars((string) $ticket->ticket_no).'</b> '.$priorityEmoji."\n".
                '   '.htmlspecialchars(Str::limit((string) $ticket->subject, 80))."\n".
                '   🗓 '.Carbon::parse($ticket->created_at)->format('d.m.Y H:i').' — '.htmlspecialchars((string) $ticket->status_name);
        }

        $keyboard = [];
        foreach ($tickets as $ticket) {
            $keyboard[] = [['text' => '👁 '.$ticket->ticket_no, 'callback_data' => 'ticket:open:'.$ticket->id]];
        }
        $keyboard[] = [
            ['text' => '🆕 Yangi zayavka', 'callback_data' => 'menu:new_ticket'],
            ['text' => '🏠 Bosh menyu', 'callback_data' => 'menu:home'],
        ];

        $this->api->sendMessage($chatId, implode("\n", $lines), [
            'inline_keyboard' => $keyboard,
        ]);
    }

    private function showOpenTickets(object $bot, object $session, string $chatId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        $tickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->whereNull('tickets.deleted_at')
            ->whereIn('tickets.status_id', [1, 2, 3])
            ->select(
                'tickets.id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.status_id',
                'tickets.created_at',
                'ticket_statuses.name as status_name',
                'ticket_priorities.name as priority_name',
                'req_user.username as requester_username'
            )
            ->orderByDesc('tickets.created_at')
            ->limit(10)
            ->get();

        if ($tickets->isEmpty()) {
            $this->sendMenu($bot, $session, $chatId, "✅ Hozircha ochiq zayavkalar yo'q.");

            return;
        }

        $lines = ['📥 <b>Ochiq zayavkalar (oxirgi '.$tickets->count()." ta):</b>\n"];
        foreach ($tickets as $ticket) {
            $statusEmoji = self::STATUS_EMOJI[(string) $ticket->status_id] ?? '▪️';
            $priorityEmoji = '';
            $priority = $ticket->priority_name ? (string) $ticket->priority_name : '';
            $priorityMap = ['Kritik' => '🔴', 'Yuqori' => '🟠', "O'rta" => '🟡', 'Past' => '🟢'];
            $priorityEmoji = $priorityMap[$priority] ?? '';
            $lines[] = $statusEmoji.' <b>'.htmlspecialchars((string) $ticket->ticket_no).'</b> '.$priorityEmoji."\n".
                '   '.htmlspecialchars(Str::limit((string) $ticket->subject, 80))."\n".
                '   👤 '.htmlspecialchars((string) ($ticket->requester_username ?: '-')).' — 🗓 '.Carbon::parse($ticket->created_at)->format('d.m.Y H:i').' — '.htmlspecialchars((string) $ticket->status_name);
        }

        $keyboard = [];
        foreach ($tickets as $ticket) {
            $keyboard[] = [['text' => '👁 '.$ticket->ticket_no, 'callback_data' => 'ticket:open:'.$ticket->id]];
        }
        $keyboard[] = [
            ['text' => '🆕 Yangi zayavka', 'callback_data' => 'menu:new_ticket'],
            ['text' => '🏠 Bosh menyu', 'callback_data' => 'menu:home'],
        ];

        $this->api->sendMessage($chatId, implode("\n", $lines), [
            'inline_keyboard' => $keyboard,
        ]);
    }

    private function showMyTasks(object $bot, object $session, string $chatId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        $tickets = DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->whereNull('tickets.deleted_at')
            ->where('tickets.assigned_user_id', $user->id)
            ->whereIn('tickets.status_id', [1, 2, 3, 4, 5, 6])
            ->select(
                'tickets.id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.status_id',
                'tickets.created_at',
                'ticket_statuses.name as status_name',
                'ticket_priorities.name as priority_name',
                'req_user.username as requester_username'
            )
            ->orderByDesc('tickets.created_at')
            ->limit(10)
            ->get();

        if ($tickets->isEmpty()) {
            $this->sendMenu($bot, $session, $chatId, "🛠 Sizga biriktirilgan faol vazifalar yo'q.");

            return;
        }

        $lines = ['🛠 <b>Sizga biriktirilgan vazifalar ('.count($tickets)." ta):</b>\n"];
        foreach ($tickets as $ticket) {
            $statusEmoji = self::STATUS_EMOJI[(string) $ticket->status_id] ?? '▪️';
            $priorityEmoji = '';
            $priority = $ticket->priority_name ? (string) $ticket->priority_name : '';
            $priorityMap = ['Kritik' => '🔴', 'Yuqori' => '🟠', "O'rta" => '🟡', 'Past' => '🟢'];
            $priorityEmoji = $priorityMap[$priority] ?? '';
            $lines[] = $statusEmoji.' <b>'.htmlspecialchars((string) $ticket->ticket_no).'</b> '.$priorityEmoji."\n".
                '   '.htmlspecialchars(Str::limit((string) $ticket->subject, 80))."\n".
                '   👤 '.htmlspecialchars((string) ($ticket->requester_username ?: '-')).' — '.htmlspecialchars((string) $ticket->status_name);
        }

        $keyboard = [];
        foreach ($tickets as $ticket) {
            $keyboard[] = [['text' => '👁 '.$ticket->ticket_no, 'callback_data' => 'ticket:open:'.$ticket->id]];
        }
        $keyboard[] = [
            ['text' => '🆕 Yangi zayavka', 'callback_data' => 'menu:new_ticket'],
            ['text' => '🏠 Bosh menyu', 'callback_data' => 'menu:home'],
        ];

        $this->api->sendMessage($chatId, implode("\n", $lines), [
            'inline_keyboard' => $keyboard,
        ]);
    }

    private function showStats(object $bot, object $session, string $chatId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        $stats = DB::table('tickets')->whereNull('deleted_at')
            ->selectRaw('SUM(CASE WHEN status_id IN (1,2,3) THEN 1 ELSE 0 END) as open')
            ->selectRaw('SUM(CASE WHEN status_id IN (4,5,6) THEN 1 ELSE 0 END) as in_progress')
            ->selectRaw('SUM(CASE WHEN status_id IN (7,8) THEN 1 ELSE 0 END) as done')
            ->selectRaw('SUM(CASE WHEN status_id = 9 THEN 1 ELSE 0 END) as rejected')
            ->selectRaw('COUNT(*) as total')
            ->first();

        $myTasks = DB::table('tickets')->whereNull('deleted_at')
            ->where('assigned_user_id', $user->id)
            ->whereIn('status_id', [1, 2, 3, 4, 5, 6])
            ->count();

        $myCompleted = DB::table('tickets')->whereNull('deleted_at')
            ->where('assigned_user_id', $user->id)
            ->whereIn('status_id', [7, 8])
            ->count();

        $text =
            "📊 <b>Statistika</b>\n\n".
            '🗂 <b>Jami zayavkalar:</b> '.(int) ($stats->total ?? 0)."\n".
            '🟦 <b>Ochiq:</b> '.(int) ($stats->open ?? 0)."\n".
            '🟪 <b>Jarayonda:</b> '.(int) ($stats->in_progress ?? 0)."\n".
            '🟩 <b>Bajarilgan:</b> '.(int) ($stats->done ?? 0)."\n".
            '🟥 <b>Rad etilgan:</b> '.(int) ($stats->rejected ?? 0)."\n\n".
            "👤 <b>Sizning ko'rsatkichlaringiz:</b>\n".
            '🛠 <b>Faol vazifalarim:</b> '.$myTasks."\n".
            '✅ <b>Bajarganlarim:</b> '.$myCompleted;

        $this->api->sendMessage($chatId, $text, [
            'inline_keyboard' => [
                [['text' => '🆕 Yangi zayavka', 'callback_data' => 'menu:new_ticket']],
                [['text' => '🏠 Bosh menyu', 'callback_data' => 'menu:home']],
            ],
        ]);
    }

    private function canAssign(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isDepartmentAdmin()
            || $user->hasPermission('tickets.view')
            || $user->hasPermission('tickets.assign');
    }

    private function canTransition(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isDepartmentAdmin()
            || $user->hasPermission('tickets.view')
            || $user->hasPermission('tickets.transition');
    }

    private function fetchTicket(int $ticketId): ?object
    {
        return DB::table('tickets')
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('users as asg_user', 'tickets.assigned_user_id', '=', 'asg_user.id')
            ->whereNull('tickets.deleted_at')
            ->where('tickets.id', $ticketId)
            ->select(
                'tickets.id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.status_id',
                'tickets.assigned_user_id',
                'tickets.requester_user_id',
                'tickets.created_at',
                'ticket_statuses.name as status_name',
                'ticket_priorities.name as priority_name',
                'req_user.username as requester_username',
                'asg_user.username as assignee_username'
            )
            ->first();
    }

    private function showTicketDetail(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId);

        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        $isRequester = (int) $ticket->requester_user_id === $user->id;
        $isAssignee = (int) $ticket->assigned_user_id === $user->id;

        if (! $isRequester && ! $isAssignee && ! $this->canTransition($user)) {
            $this->api->sendMessage($chatId, "❌ Bu zayavkani ko'rishga ruxsatingiz yo'q.");

            return;
        }

        $statusEmoji = self::STATUS_EMOJI[(string) $ticket->status_id] ?? '▪️';
        $priorityMap = ['Kritik' => '🔴', 'Yuqori' => '🟠', "O'rta" => '🟡', 'Past' => '🟢'];
        $priority = (string) $ticket->priority_name;
        $priorityEmoji = $priorityMap[$priority] ?? '';

        $text =
            '🎫 <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
            '📝 '.htmlspecialchars(Str::limit((string) $ticket->subject, 200))."\n\n".
            '📊 Holat: '.$statusEmoji.' '.htmlspecialchars((string) $ticket->status_name)."\n".
            '⚡ Muhimlik: '.$priorityEmoji.' '.htmlspecialchars($priority ?: '-')."\n".
            '👤 So\'rovchi: <b>'.htmlspecialchars((string) ($ticket->requester_username ?: '-'))."</b>\n".
            '🔧 Ijrochi: '.htmlspecialchars((string) ($ticket->assignee_username ?: '-'))."\n".
            '🗓 Yaratilgan: '.Carbon::parse($ticket->created_at)->format('d.m.Y H:i');

        $keyboard = $this->ticketActionButtons($ticket, $user);
        $keyboard[] = [['text' => '🔁 Yangilash', 'callback_data' => 'ticket:open:'.$ticket->id]];
        $keyboard[] = [
            ['text' => '🆕 Yangi zayavka', 'callback_data' => 'menu:new_ticket'],
            ['text' => '🏠 Bosh menyu', 'callback_data' => 'menu:home'],
        ];

        $this->api->sendMessage($chatId, $text, [
            'inline_keyboard' => $keyboard,
        ]);
    }

    private function ticketActionButtons(object $ticket, User $user): array
    {
        $rows = [];
        $active = in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true);
        $isAssignee = (int) $ticket->assigned_user_id === $user->id;
        $actor = $isAssignee || $this->canTransition($user);

        if ($this->canAssign($user) && ! $isAssignee && $active) {
            $rows[] = [
                ['text' => '📥 O\'zimga olish', 'callback_data' => 'ticket:take:'.$ticket->id],
            ];
        }

        if ($actor && in_array((int) $ticket->status_id, [1, 2, 3], true)) {
            $rows[] = [
                ['text' => '▶️ Jarayonga o\'tkazish', 'callback_data' => 'ticket:start:'.$ticket->id],
            ];
        }

        if ($actor && in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true)) {
            $rows[] = [
                ['text' => '✅ Hal qilindi', 'callback_data' => 'ticket:resolve:'.$ticket->id],
                ['text' => '❌ Rad etish', 'callback_data' => 'ticket:reject:'.$ticket->id],
            ];
        }

        return $rows;
    }

    private function assignmentBlockReason(User $user, int $ticketId): ?string
    {
        $openRejected = DB::table('tickets')->whereNull('deleted_at')
            ->where('assigned_user_id', $user->id)
            ->where('status_id', 9)
            ->where('id', '!=', $ticketId)
            ->exists();

        if ($openRejected) {
            return "Sizda yopilmagan qaytarilgan (reject) zayavka bor. Avval uni yakunlang, so'ng yangi zayavka qabul qilishingiz mumkin!";
        }

        $activeCount = DB::table('tickets')->whereNull('deleted_at')
            ->where('assigned_user_id', $user->id)
            ->whereIn('status_id', [1, 2, 3, 4, 5, 6])
            ->where('id', '!=', $ticketId)
            ->count();

        if ($activeCount >= 3) {
            return "Siz bir vaqtning o'zida 'Ochiq' va 'Jarayonda' holatida jami 3 tadan ortiq zayavka ololmaysiz. Avval mavjud zayavkalardan birini yakunlang!";
        }

        return null;
    }

    private function assignToSelf(int $ticketId, User $user, ?string $reason = null): void
    {
        app(AssignTicketService::class)->execute($ticketId, null, $user->id, $user->id, $reason);

        DB::table('ticket_assignment_history')
            ->where('ticket_id', $ticketId)
            ->orderByDesc('id')
            ->limit(1)
            ->update(['source_id' => 2]);
    }

    private function transitionStatus(int $ticketId, int $toStatusId, User $user, ?string $reason = null): void
    {
        app(TransitionTicketService::class)->execute($ticketId, $toStatusId, $user->id, $reason);

        DB::table('ticket_status_history')
            ->where('ticket_id', $ticketId)
            ->orderByDesc('id')
            ->limit(1)
            ->update(['source_id' => 2]);
    }

    private function takeTicket(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user || ! $this->canAssign($user)) {
            $this->api->sendMessage($chatId, "❌ Sizda zayavka biriktirish huquqi yo'q.");

            return;
        }

        $ticket = $this->fetchTicket($ticketId);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        if ((int) $ticket->assigned_user_id === $user->id) {
            $this->api->sendMessage($chatId, 'ℹ️ Bu zayavka allaqachon sizga biriktirilgan.');

            return;
        }

        $block = $this->assignmentBlockReason($user, $ticketId);
        if ($block !== null) {
            $this->api->sendMessage($chatId, '⚠️ '.$block);

            return;
        }

        if ($ticket->assigned_user_id !== null) {
            $this->setState($session, self::STATE_AWAIT_TICKET_REASON, [
                'pending_action' => 'take',
                'ticket_id' => $ticketId,
            ]);
            $this->api->sendMessage($chatId,
                "📝 Zayavka boshqa xodimga biriktirilgan.\n\nQabul qilish <b>sababini</b> yozing (masalan: \"Xodim ta'tilda, men davom ettiraman\"):",
                ['remove_keyboard' => true]
            );

            return;
        }

        try {
            $this->assignToSelf($ticketId, $user);
        } catch (\Throwable $e) {
            Log::error('Bot zayavka qabul qilish xatosi', ['error' => $e->getMessage()]);
            $this->api->sendMessage($chatId, "⚠️ Zayavkani qabul qilishda xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");

            return;
        }

        $this->api->sendMessage($chatId, "✅ Zayavka o'zingizga qabul qilindi.");
        $this->showTicketDetail($bot, $session, $chatId, $ticketId);
    }

    private function onTicketReason(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $ticketId = (int) ($data['ticket_id'] ?? 0);

        if (($data['pending_action'] ?? null) !== 'take' || ! $ticketId) {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        if (mb_strlen($text) < 3) {
            $this->api->sendMessage($chatId, '⚠️ Sabab juda qisqa (kamida 3 ta belgi). Qaytadan yozing:');

            return;
        }

        try {
            $this->assignToSelf($ticketId, $user, $text);
        } catch (\Throwable $e) {
            Log::error('Bot zayavka qabul qilish xatosi', ['error' => $e->getMessage()]);
            $this->api->sendMessage($chatId, '⚠️ Zayavkani qabul qilishda xatolik yuz berdi.');
            $this->resetSession($session);

            return;
        }

        $this->resetSession($session);
        $this->api->sendMessage($chatId, "✅ Zayavka o'zingizga qabul qilindi.");
        $this->showTicketDetail($bot, $session, $chatId, $ticketId);
    }

    private function startTicket(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        $isAssignee = (int) $ticket->assigned_user_id === $user->id;
        if (! $isAssignee && ! $this->canTransition($user)) {
            $this->api->sendMessage($chatId, "❌ Bu amal uchun huquqingiz yo'q.");

            return;
        }

        if (! in_array((int) $ticket->status_id, [1, 2, 3], true)) {
            $this->api->sendMessage($chatId, "⚠️ Zayavkani hozirgi holatidan 'Jarayonda' holatiga o'tkazib bo'lmaydi.");

            return;
        }

        if (! $ticket->assigned_user_id) {
            $block = $this->assignmentBlockReason($user, $ticketId);
            if ($block !== null) {
                $this->api->sendMessage($chatId, '⚠️ '.$block);

                return;
            }
        }

        try {
            DB::transaction(function () use ($ticketId, $user, $ticket) {
                if (! $ticket->assigned_user_id) {
                    $this->assignToSelf($ticketId, $user);
                }
                $this->transitionStatus($ticketId, 4, $user, 'Telegram bot orqali jarayonga o\'tkazildi');
                DB::table('tickets')->where('id', $ticketId)->update(['started_at' => now()]);
            });
        } catch (\Throwable $e) {
            Log::error('Bot zayavka holatini o\'zgartirish xatosi', ['error' => $e->getMessage()]);
            $this->api->sendMessage($chatId, "⚠️ Holatni o'zgartirishda xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");

            return;
        }

        $this->api->sendMessage($chatId, "✅ Zayavka 'Jarayonda' holatiga o'tkazildi.");
        $this->showTicketDetail($bot, $session, $chatId, $ticketId);
    }

    private function resolveTicket(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        $isAssignee = (int) $ticket->assigned_user_id === $user->id;
        if (! $isAssignee && ! $this->canTransition($user)) {
            $this->api->sendMessage($chatId, "❌ Bu amal uchun huquqingiz yo'q.");

            return;
        }

        if (! in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true)) {
            $this->api->sendMessage($chatId, "⚠️ Zayavkani hozirgi holatidan 'Hal qilindi' holatiga o'tkazib bo'lmaydi.");

            return;
        }

        try {
            DB::transaction(function () use ($ticketId, $user) {
                $this->transitionStatus($ticketId, 7, $user, 'Telegram bot orqali hal qilindi');
                DB::table('tickets')->where('id', $ticketId)->update(['resolved_at' => now()]);
            });
        } catch (\Throwable $e) {
            Log::error('Bot zayavka holatini o\'zgartirish xatosi', ['error' => $e->getMessage()]);
            $this->api->sendMessage($chatId, "⚠️ Holatni o'zgartirishda xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");

            return;
        }

        $this->api->sendMessage($chatId, "✅ Zayavka 'Hal qilindi' holatiga o'tkazildi.");
        $this->showTicketDetail($bot, $session, $chatId, $ticketId);
    }

    private function rejectTicket(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user || ! $this->canTransition($user)) {
            $this->api->sendMessage($chatId, "❌ Bu amal uchun huquqingiz yo'q.");

            return;
        }

        $ticket = $this->fetchTicket($ticketId);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        if (! in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true)) {
            $this->api->sendMessage($chatId, "⚠️ Zayavkani hozirgi holatidan 'Rad etildi' holatiga o'tkazib bo'lmaydi.");

            return;
        }

        try {
            $this->transitionStatus($ticketId, 9, $user, 'Telegram bot orqali rad etildi');
        } catch (\Throwable $e) {
            Log::error('Bot zayavka holatini o\'zgartirish xatosi', ['error' => $e->getMessage()]);
            $this->api->sendMessage($chatId, "⚠️ Holatni o'zgartirishda xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");

            return;
        }

        $this->api->sendMessage($chatId, "✅ Zayavka 'Rad etildi' holatiga o'tkazildi.");
        $this->showTicketDetail($bot, $session, $chatId, $ticketId);
    }

    private function sendHelp(object $bot, object $session, string $chatId): void
    {
        $user = $this->user($session);
        $staff = $user && $this->isStaff($user);

        $text =
            "ℹ️ <b>Yordam</b>\n\n".
            "🆕 <b>Yangi zayavka</b> — muammongizni yozib, IT xodimlariga yuborasiz\n".
            "📋 <b>Mening zayavkalarim</b> — o'z zayavkalaringiz holatini ko'rasiz\n".
            "👁 <b>Zayavkani ochish</b> — ro'yxatdagi zayavka ustiga bosib, tafsilotini ko'rasiz";

        if ($staff) {
            $text .= "\n".
                "📥 <b>Ochiq zayavkalar</b> — barcha ochiq zayavkalar ro'yxati\n".
                "🛠 <b>Mening vazifalarim</b> — sizga biriktirilgan zayavkalar\n".
                "📊 <b>Statistika</b> — zayavkalar bo'yicha umumiy ko'rsatkichlar\n".
                "📥 <b>O'zimga olish</b> — zayavkani qabul qilish (huquq bo'yicha)\n".
                "▶️ <b>Jarayonga o'tkazish</b> — zayavkani ishga olish\n".
                '✅ <b>Hal qilindi</b> / ❌ <b>Rad etish</b> — zayavkani yakunlash';
        }

        $text .= "\n\nKomandalar:\n".
            "/start — asosiy menyu\n".
            "/cancel — amalni bekor qilish\n".
            "/logout — tizimdan chiqish\n".
            '/help — yordam';

        $this->api->sendMessage($chatId, $text, [
            'inline_keyboard' => $this->menuRows($user),
        ]);
    }

    private function sendMenu(object $bot, object $session, string $chatId, ?string $header = null): void
    {
        $user = $this->user($session);
        $header = $header ?? ($user ? 'Xush kelibsiz, '.$user->username.'! 👋' : 'Bosh menyu:');

        $this->api->sendMessage($chatId, $header, [
            'keyboard' => $this->menuRows($user),
            'resize_keyboard' => true,
        ]);
    }

    private function menuRows(?User $user): array
    {
        $rows = [
            [['text' => '🆕 Yangi zayavka']],
        ];

        if ($user && $this->isStaff($user)) {
            $rows[] = [
                ['text' => '📋 Mening zayavkalarim'],
                ['text' => '📥 Ochiq zayavkalar'],
            ];
            $rows[] = [
                ['text' => '🛠 Mening vazifalarim'],
                ['text' => '📊 Statistika'],
            ];
        } else {
            $rows[] = [
                ['text' => '📋 Mening zayavkalarim'],
            ];
        }

        $rows[] = [
            ['text' => 'ℹ️ Yordam'],
        ];

        if ($user) {
            $rows[] = [
                ['text' => '🚪 Chiqish'],
            ];
        }

        return $rows;
    }

    private function logout(object $bot, object $session, string $chatId): void
    {
        DB::table('telegram_chat_sessions')->where('id', $session->id)->update([
            'user_id' => null,
            'state' => self::STATE_AWAIT_USERNAME,
            'data' => json_encode([]),
            'last_activity_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('telegram_accounts')
            ->where('organization_id', $bot->organization_id)
            ->where('telegram_user_id', (string) $session->telegram_user_id)
            ->update([
                'verified_at' => null,
                'updated_at' => now(),
            ]);

        $this->api->sendMessage($chatId,
            "👋 Tizimdan chiqdingiz.\n\n".
            '🔐 Qayta kirish uchun saytdagi <b>loginingizni</b> yozing:',
            ['remove_keyboard' => true]
        );
    }

    private function isStaff(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isDepartmentAdmin()
            || $user->hasPermission('tickets.view')
            || $user->hasPermission('tickets.assign');
    }

    private function sendLoginPrompt(object $bot, string $chatId): void
    {
        $this->api->sendMessage($chatId,
            "🔐 Avval tizimga kirishingiz kerak.\n\n".
            '📝 Saytdagi <b>loginingizni</b> yozing:'
        );
    }

    private function linkAccount(object $bot, object $session, User $user, string $chatId): void
    {
        $existing = DB::table('telegram_accounts')
            ->where('organization_id', $bot->organization_id)
            ->where('telegram_user_id', (string) $session->telegram_user_id)
            ->first();

        if (! $existing) {
            $existing = DB::table('telegram_accounts')
                ->where('organization_id', $bot->organization_id)
                ->where('user_id', $user->id)
                ->first();
        }

        if ($existing) {
            DB::table('telegram_accounts')->where('id', $existing->id)->update([
                'telegram_user_id' => (string) $session->telegram_user_id,
                'employee_id' => $user->employee_id,
                'private_chat_id' => $chatId,
                'verified_at' => now(),
                'verification_source' => 'LOGIN',
                'last_seen_at' => now(),
                'blocked_at' => null,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('telegram_accounts')->insert([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $bot->organization_id,
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'telegram_user_id' => (string) $session->telegram_user_id,
            'telegram_username' => null,
            'private_chat_id' => $chatId,
            'verified_at' => now(),
            'verification_source' => 'LOGIN',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function session(object $bot, string $chatId, string $telegramUserId): object
    {
        $session = DB::table('telegram_chat_sessions')
            ->where('bot_id', $bot->id)
            ->where('chat_id', $chatId)
            ->first();

        if (! $session) {
            $id = DB::table('telegram_chat_sessions')->insertGetId([
                'organization_id' => $bot->organization_id,
                'bot_id' => $bot->id,
                'chat_id' => $chatId,
                'telegram_user_id' => $telegramUserId,
                'user_id' => null,
                'state' => self::STATE_IDLE,
                'data' => json_encode([]),
                'last_activity_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $session = DB::table('telegram_chat_sessions')->where('id', $id)->first();
        }

        if (empty($session->user_id)) {
            $accountUserId = DB::table('telegram_accounts')
                ->where('organization_id', $bot->organization_id)
                ->where('telegram_user_id', (string) $session->telegram_user_id)
                ->whereNotNull('verified_at')
                ->whereNull('blocked_at')
                ->value('user_id');

            if ($accountUserId) {
                DB::table('telegram_chat_sessions')->where('id', $session->id)->update(['user_id' => $accountUserId]);
                $session->user_id = $accountUserId;
            }
        }

        return $session;
    }

    private function sessionData(object $session): array
    {
        if (is_array($session->data)) {
            return $session->data;
        }
        $decoded = json_decode((string) ($session->data ?? '[]'), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function setState(object $session, string $state, array $data): void
    {
        DB::table('telegram_chat_sessions')->where('id', $session->id)->update([
            'state' => $state,
            'data' => json_encode($data),
            'last_activity_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resetSession(object $session): void
    {
        DB::table('telegram_chat_sessions')->where('id', $session->id)->update([
            'state' => self::STATE_IDLE,
            'data' => json_encode([]),
            'last_activity_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(object $session): ?User
    {
        if (empty($session->user_id)) {
            return null;
        }

        return User::query()->find($session->user_id);
    }
}
