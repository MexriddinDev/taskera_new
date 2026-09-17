<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Services;

use App\Http\Controllers\Api\AdAccountController;
use App\Models\User;
use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use App\Modules\Telegram\Support\TicketStatusEmoji;
use App\Modules\Ticketing\Domain\Events\TicketStatusChanged;
use App\Modules\Ticketing\Domain\Services\AddCommentService;
use App\Modules\Ticketing\Domain\Services\AssignTicketService;
use App\Modules\Ticketing\Domain\Services\TransitionTicketService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Comment;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Modules\Ticketing\Presentation\Http\Controllers\TicketController;
use App\Modules\Ticketing\Presentation\Http\Requests\StoreTicketRequest;
use App\Support\DeviceInfo;
use App\Support\RequesterPrefill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    /** Xabarlardagi vaqt shu zonada ko'rsatiladi (baza UTC da). */
    private const DISPLAY_TIMEZONE = 'Asia/Tashkent';

    private const STATE_IDLE = 'IDLE';

    private const STATE_AWAIT_CONTACT = 'AWAIT_CONTACT';

    // Ro'yxatdan o'tish (AD pochta ochish) — saytdagi /ad-account oqimining aynan o'zi
    private const STATE_AWAIT_USERNAME = 'AWAIT_USERNAME';

    /** Pochtadan keyin SMS orqali kelgan bir martalik kod kutiladi. */
    private const STATE_AWAIT_SMS_CODE = 'AWAIT_SMS_CODE';

    private const STATE_AWAIT_TICKET_TEXT = 'AWAIT_TICKET_TEXT';

    private const STATE_AWAIT_TICKET_TEAM = 'AWAIT_TICKET_TEAM';

    private const STATE_AWAIT_TICKET_TEMPLATE = 'AWAIT_TICKET_TEMPLATE';

    /** Matndan keyin rasm so'raladi (ixtiyoriy, o'tkazib yuborish mumkin). */
    /**
     * Zayavka matnini ko'rsatish chegarasi.
     *
     * `onTicketText` matnni 2000 belgi bilan cheklaydi, ya'ni bu qiymat bilan
     * matn HECH QACHON qirqilmaydi. Telegram xabari 4096 belgigacha, qolgan
     * maydonlar (raqam, holat, guruh...) ~600 belgidan oshmaydi — sig'adi.
     */
    private const TICKET_TEXT_MAX = 2000;

    private const STATE_AWAIT_TICKET_PHOTO = 'AWAIT_TICKET_PHOTO';

    /** Rasmdan keyin ovozli xabar so'raladi (ixtiyoriy). */
    private const STATE_AWAIT_TICKET_AUDIO = 'AWAIT_TICKET_AUDIO';

    private const STATE_AWAIT_TICKET_CONFIRM = 'AWAIT_TICKET_CONFIRM';

    private const STATE_AWAIT_TICKET_REASON = 'AWAIT_TICKET_REASON';

    private const STATE_AWAIT_TICKET_RETURN_REASON = 'AWAIT_TICKET_RETURN_REASON';

    private const STATE_AWAIT_RATING_FEEDBACK = 'AWAIT_RATING_FEEDBACK';

    /** Saytdagidek: zayavkani yopishdan oldin yechim matni so'raladi. */
    private const STATE_AWAIT_SOLUTION_COMMENT = 'AWAIT_SOLUTION_COMMENT';

    /** Saytdagidek: rad etishdan oldin sabab so'raladi. */
    private const STATE_AWAIT_REJECT_REASON = 'AWAIT_REJECT_REASON';

    private const MENU_BUTTONS = [
        '🆕 Yangi zayavka' => 'menu:new_ticket',
        '📋 Mening zayavkalarim' => 'menu:my_tickets',
        '📥 Ochiq zayavkalar' => 'menu:open_tickets',
        '🛠 Mening vazifalarim' => 'menu:my_tasks',
        '📊 Statistika' => 'menu:stats',
    ];

    /**
     * Muhimlik variantlari — saytdagi CreateTaskModal bilan bir xil (low/medium/high).
     * TicketController::store() validatsiyasi ham aynan shu uchtasini qabul qiladi.
     */
    /** Bir sahifada ko'rsatiladigan guruhlar soni. */
    private const TEAM_PAGE_SIZE = 8;

    public function __construct(
        private readonly TelegramApiClient $api,
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

        // Telefon raqam orqali kirish — media tekshiruvidan OLDIN, chunki
        // contact xabari ham message ichida keladi.
        if ($message !== null && isset($message['contact'])) {
            $this->handleContact($bot, $session, $chatId, $message);

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
            // Ro'yxatdan o'tish yarim yo'lda to'xtatilsa — kiritilganlar tozalanadi
            if (str_starts_with((string) $session->state, 'REG_')) {
                $this->setState($session, self::STATE_AWAIT_CONTACT, []);
                $this->api->sendMessage($chatId,
                    "Ro'yxatdan o'tish bekor qilindi.",
                    $this->contactKeyboard()
                );

                return;
            }

            // Kirmagan foydalanuvchida /cancel kirish qadamlarini bekor qilmaydi —
            // aks holda yuborilgan telefon raqam yo'qolib, jarayon boshidan boshlanardi.
            if ($session->user_id === null) {
                $this->sendLoginPrompt($bot, $session, $chatId);

                return;
            }

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

        // 1-qadam: telefon raqam faqat tugma orqali yuboriladi, qo'lda yozilmaydi.
        if ($state === self::STATE_AWAIT_CONTACT) {
            $this->api->sendMessage($chatId,
                "📱 Avval pastdagi <b>«Telefon raqamni yuborish»</b> tugmasini bosing.\n\n".
                "Raqamni qo'lda yozish kerak emas — Telegram uni o'zi yuboradi.",
                $this->contactKeyboard()
            );

            return;
        }

        if ($state === self::STATE_AWAIT_USERNAME) {
            $this->onUsername($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_SMS_CODE) {
            $this->onSmsCode($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_TICKET_REASON) {
            $this->onTicketReason($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_TICKET_RETURN_REASON) {
            $this->onTicketReturnReason($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_RATING_FEEDBACK) {
            $this->onRatingFeedback($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_SOLUTION_COMMENT) {
            $this->onSolutionComment($bot, $session, $chatId, $text);

            return;
        }

        if ($state === self::STATE_AWAIT_REJECT_REASON) {
            $this->onRejectReason($bot, $session, $chatId, $text);

            return;
        }

        if ($session->user_id === null) {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        // Guruh — saytdagidek majburiy, faqat tugma orqali tanlanadi
        if ($state === self::STATE_AWAIT_TICKET_TEAM) {
            $this->api->sendMessage($chatId, '👆 Iltimos, quyidagi ro\'yxatdan <b>guruhni tanlang</b>:');
            $this->showTeamButtons($bot, $session, $chatId, 0);

            return;
        }

        // Shablon ixtiyoriy — foydalanuvchi darrov matn yozsa, uni tavsif deb qabul qilamiz
        if ($state === self::STATE_AWAIT_TICKET_TEMPLATE || $state === self::STATE_AWAIT_TICKET_TEXT) {
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

        $this->setState($session, self::STATE_AWAIT_CONTACT, []);
        $this->api->sendMessage($chatId,
            '👋 Assalomu alaykum, <b>'.htmlspecialchars($firstName)."</b>!\n\n".
            "Kompyuteringizda muammo bo'lib saytga kira olmayapsizmi? Hechqisi yo'q — shu yerdan zayavka yuborishingiz mumkin.\n\n".
            "🔐 Kirish 3 bosqichda amalga oshiriladi:\n".
            "1️⃣ Telefon raqamingizni yuborasiz\n".
            "2️⃣ Pochtangizni yozasiz\n".
            "3️⃣ Raqamingizga kelgan bir martalik kodni kiritasiz\n\n".
            '📱 Boshlash uchun pastdagi tugmani bosing.',
            $this->contactKeyboard()
        );
    }

    /**
     * 1-qadam: pochta kiritildi -> raqamga bir martalik SMS kod yuboriladi.
     *
     * Parol so'ralmaydi. Kod FAQAT tizimda shu pochtaga biriktirilgan xodim
     * raqamiga ketadi, shuning uchun begona pochta bilan kirib bo'lmaydi.
     */
    private function onUsername(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $email = mb_strtolower(trim($text));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->sendMessage($chatId,
                "⚠️ Pochtani to'liq yozing (masalan: <code>ism.familiya@xb.uz</code>). Qaytadan yozing:"
            );

            return;
        }

        // 1-qadamda yuborilgan raqam SHU hisobga tegishlimi.
        //
        // Kod baribir hisob egasining raqamiga ketadi, ya'ni begona pochta
        // bilan kirib bo'lmaydi. Lekin bu tekshiruvsiz foydalanuvchi o'z
        // raqamini yuborib, keyin sherigining pochtasini yozsa — unga SMS
        // ketaverar va bu keraksiz shovqin bo'lardi.
        $candidate = app(BotLoginCodeService::class)->findByEmail($email);

        if ($candidate !== null && ! $this->phoneBelongsToUser($data, $candidate)) {
            $this->setState($session, self::STATE_AWAIT_CONTACT, []);
            $this->api->sendMessage($chatId,
                "❌ Bu pochta siz yuborgan telefon raqamga tegishli emas.\n\n".
                "Faqat o'z hisobingizga kirishingiz mumkin. Qaytadan boshlaymiz:",
                $this->contactKeyboard()
            );

            return;
        }

        $result = app(BotLoginCodeService::class)->send($email);

        if (! $result['ok']) {
            $this->api->sendMessage($chatId, '❌ '.$result['message']."\n\nPochtani qaytadan yozing yoki /start ni bosing.");

            return;
        }

        $data['login_user_id'] = $result['user_id'];
        $this->setState($session, self::STATE_AWAIT_SMS_CODE, $data);

        $this->api->sendMessage($chatId,
            '📲 <b>'.htmlspecialchars((string) $result['phone'])."</b> raqamiga 5 xonali kod yuborildi.\n\n".
            'Kodni shu yerga yozing. Kod 5 daqiqa amal qiladi.',
            ['remove_keyboard' => true]
        );
    }

    /** 2-qadam: SMS kod tekshiriladi va hisob bog'lanadi. */
    private function onSmsCode(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $userId = (int) ($data['login_user_id'] ?? 0);

        if ($userId === 0) {
            $this->setState($session, self::STATE_AWAIT_USERNAME, []);
            $this->api->sendMessage($chatId, '⚠️ Sessiya eskirgan. Pochtangizni qaytadan yozing:');

            return;
        }

        $code = preg_replace('/\D+/', '', trim($text)) ?? '';
        $result = app(BotLoginCodeService::class)->verify($userId, $code);

        if (! $result['ok']) {
            // Kod kuygan yoki muddati tugagan bo'lsa — pochtadan qayta boshlanadi.
            if (str_contains($result['message'], 'qaytadan yozing') || str_contains($result['message'], 'Qaytadan boshlang')) {
                $this->setState($session, self::STATE_AWAIT_USERNAME, []);
            }

            $this->api->sendMessage($chatId, '❌ '.$result['message']);

            return;
        }

        $user = $result['user'];
        $pendingAction = $data['pending_action'] ?? null;

        $this->linkAccount($bot, $session, $user, $chatId, 'SMS');
        DB::table('telegram_chat_sessions')->where('id', $session->id)->update([
            'user_id' => $user->id,
            'updated_at' => now(),
        ]);
        $session->user_id = $user->id;

        $this->setState($session, self::STATE_IDLE, ['username' => $user->username]);

        if ($pendingAction === 'new_ticket') {
            $this->api->sendMessage($chatId,
                "✅ <b>Muvaffaqiyatli kirdingiz!</b>\n\n".
                '👤 Foydalanuvchi: <b>'.htmlspecialchars((string) $user->username)."</b>\n\n".
                'Endi zayavka yaratishni davom ettiramiz.'
            );
            $this->startTicketFlow($bot, $session, $chatId, ['username' => $user->username]);

            return;
        }

        $this->api->sendMessage($chatId,
            "✅ <b>Muvaffaqiyatli kirdingiz!</b>\n\n".
            '👤 Foydalanuvchi: <b>'.htmlspecialchars((string) $user->username).'</b>'
        );
        $this->sendMenu($bot, $session, $chatId);
    }

    private function startTicketFlow(object $bot, object $session, string $chatId, array $data): void
    {
        // Baholanmagan yakunlangan zayavka bo'lsa, oqim BOSHLANMAYDI.
        //
        // Ilgari bu qoida faqat oxirida — TicketController::store javobidan
        // bilinardi: foydalanuvchi guruh, shablon, tavsif va fayllarni
        // to'ldirib, tasdiqlash tugmasini bosgandan keyingina "eski
        // zayavkangizni baholang" xabarini olardi va kiritgan hamma narsasi
        // bekorga ketardi. Endi tekshiruv tugma bosilgan zahoti bo'ladi.
        if ($this->blockedByUnratedTicket($session, $chatId)) {
            return;
        }

        $this->setState($session, self::STATE_AWAIT_TICKET_TEAM, $data);
        $this->showTeamButtons($bot, $session, $chatId, 0);
    }

    /**
     * Baholanmagan yakunlangan zayavka bormi? Bo'lsa — ogohlantiradi, baholash
     * tugmasini beradi va `true` qaytaradi.
     *
     * Shart saytdagi qoida bilan AYNI: TicketController::store — status 7/8
     * (bajarildi/yopildi) va `client_rating` bo'sh.
     */
    private function blockedByUnratedTicket(object $session, string $chatId): bool
    {
        if (empty($session->user_id)) {
            return false;
        }

        $unrated = DB::table('tickets')
            ->whereNull('deleted_at')
            ->where('requester_user_id', (int) $session->user_id)
            ->whereIn('status_id', [7, 8])
            ->whereNull('client_rating')
            ->first(['id', 'ticket_no']);

        if (! $unrated) {
            return false;
        }

        $this->resetSession($session);
        $this->api->sendMessage($chatId,
            "⚠️ <b>Eski zayavkangizni baholang!</b>\n\n".
            'Yangi zayavka yuborishdan oldin bajarilgan <code>'
            .htmlspecialchars((string) $unrated->ticket_no).'</code> zayavkangizga baho bering yoki qaytaring.',
            [
                'inline_keyboard' => [
                    [['text' => '⭐ Baholash', 'callback_data' => 'ticket:rate:'.$unrated->id]],
                    [['text' => '👁 Zayavkani ochish', 'callback_data' => 'ticket:open:'.$unrated->id]],
                ],
            ]
        );

        return true;
    }

    /**
     * Saytdagi /teams ro'yxati bilan bir xil manba. Guruh tanlash — majburiy.
     */
    private function fetchTeams(int $organizationId, User $user): array
    {
        return \App\Support\RegionalRouting::visibleTeams(DB::table('teams')->whereNull('region_id'), $user)
            ->whereNull('deleted_at')->where('is_active', true)
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->all();
    }

    private function showTeamButtons(object $bot, object $session, string $chatId, int $page): void
    {
        $teams = $this->fetchTeams((int) $bot->organization_id, $this->user($session));

        if (count($teams) === 0) {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId, "⚠️ Tizimda xizmat guruhlari sozlanmagan. Administrator bilan bog'laning.");

            return;
        }

        $pageCount = (int) ceil(count($teams) / self::TEAM_PAGE_SIZE);
        $page = max(0, min($page, $pageCount - 1));
        $slice = array_slice($teams, $page * self::TEAM_PAGE_SIZE, self::TEAM_PAGE_SIZE);

        $rows = [];
        foreach ($slice as $team) {
            $rows[] = [
                ['text' => '👥 '.Str::limit((string) $team->name, 55), 'callback_data' => 'team:'.$team->id],
            ];
        }

        if ($pageCount > 1) {
            $nav = [];
            if ($page > 0) {
                $nav[] = ['text' => '⬅️ Oldingi', 'callback_data' => 'team:page:'.($page - 1)];
            }
            $nav[] = ['text' => '· '.($page + 1).'/'.$pageCount.' ·', 'callback_data' => 'team:page:'.$page];
            if ($page < $pageCount - 1) {
                $nav[] = ['text' => 'Keyingi ➡️', 'callback_data' => 'team:page:'.($page + 1)];
            }
            $rows[] = $nav;
        }

        $rows[] = [
            ['text' => '❌ Bekor qilish', 'callback_data' => 'cancel'],
        ];

        $this->api->sendMessage($chatId,
            "👥 Zayavka <b>qaysi xizmat guruhiga</b> yuborilsin?\n\n".
            'Ro\'yxatdan birini tanlang:',
            ['inline_keyboard' => $rows]
        );
    }

    private function onTeamSelected(object $bot, object $session, string $chatId, int $teamId): void
    {
        $team = DB::table('teams')
            ->whereNull('deleted_at')
            ->where('organization_id', $bot->organization_id)
            ->where('id', $teamId)
            ->first(['id', 'name']);

        if (! $team) {
            $this->api->sendMessage($chatId, '⚠️ Bunday guruh topilmadi. Boshqasini tanlang:');
            $this->showTeamButtons($bot, $session, $chatId, 0);

            return;
        }

        $data = $this->sessionData($session);
        $data['ticket_team_id'] = (int) $team->id;
        $data['ticket_team_name'] = (string) $team->name;

        // Saytdagidek: shablonlar tanlangan guruh bo'yicha yuklanadi
        $templates = DB::table('ticket_templates')
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'content'])
            ->all();

        if (count($templates) === 0) {
            $this->setState($session, self::STATE_AWAIT_TICKET_TEXT, $data);
            $this->promptTicketText($chatId, (string) $team->name, null);

            return;
        }

        $this->setState($session, self::STATE_AWAIT_TICKET_TEMPLATE, $data);

        $rows = [];
        foreach (array_slice($templates, 0, 20) as $template) {
            $rows[] = [
                ['text' => '📄 '.Str::limit((string) $template->name, 55), 'callback_data' => 'tmpl:'.$template->id],
            ];
        }
        $rows[] = [['text' => '✍️ Shablonsiz — o\'zim yozaman', 'callback_data' => 'tmpl:skip']];
        $rows[] = [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel']];

        $this->api->sendMessage($chatId,
            '👥 Guruh: <b>'.htmlspecialchars((string) $team->name)."</b>\n\n".
            "📄 <b>Shablon</b> tanlang — tayyor matn yuklanadi va uni tahrirlashingiz mumkin.\n".
            'Yoki muammoni o\'z so\'zingiz bilan yozing:',
            ['inline_keyboard' => $rows]
        );
    }

    private function onTemplateSelected(object $bot, object $session, string $chatId, string $key): void
    {
        $data = $this->sessionData($session);
        $teamName = (string) ($data['ticket_team_name'] ?? '');
        $prefill = null;

        if ($key !== 'skip') {
            $template = DB::table('ticket_templates')
                ->where('id', (int) $key)
                ->where('is_active', true)
                ->first(['id', 'name', 'content']);

            if (! $template) {
                $this->api->sendMessage($chatId, '⚠️ Shablon topilmadi. Muammoni o\'zingiz yozing:');
            } else {
                // Shablondagi "Xodim F.I.Sh:" kabi bo'sh qatorlar xodim
                // ma'lumoti bilan to'ldiriladi — veb oynasidagi bilan bir xil.
                $prefill = RequesterPrefill::apply(
                    (string) $template->content,
                    $session->user_id === null ? null : User::find($session->user_id),
                );
                $data['ticket_template_name'] = (string) $template->name;
            }
        }

        $this->setState($session, self::STATE_AWAIT_TICKET_TEXT, $data);
        $this->promptTicketText($chatId, $teamName, $prefill);
    }

    private function promptTicketText(string $chatId, string $teamName, ?string $prefill): void
    {
        // Shablon ALOHIDA xabarda va <pre> blokda yuboriladi. Sababi: shablonlar
        // ko'p qatorli forma ("- Qurilma nomi:", "- Muammo:" ...) va Telegram
        // <pre> blokka nusxalash tugmasi qo'yadi. Ilgari shablon guruh nomi va
        // ko'rsatmalar bilan bitta uzun xabar ichida edi — faqat shablonni
        // ajratib nusxalash noqulay edi.
        if ($prefill !== null && $prefill !== '') {
            $this->api->sendMessage($chatId,
                ($teamName !== '' ? '👥 Guruh: <b>'.htmlspecialchars($teamName)."</b>\n" : '').
                "📄 <b>Shablon</b> — nusxalab, kerakli joyini to'ldiring:"
            );
            $this->api->sendMessage($chatId, '<pre>'.htmlspecialchars($prefill).'</pre>');
        } elseif ($teamName !== '') {
            $this->api->sendMessage($chatId, '👥 Guruh: <b>'.htmlspecialchars($teamName).'</b>');
        }

        $this->api->sendMessage($chatId,
            "📝 Endi <b>muammoingizni yozing</b>.\n\n".
            "Masalan: <i>\"Kompyuterim yoqilmayapti, quvvat tugmasi ishlamayapti\"</i>\n\n".
            "🖼 Rasm, 🎤 ovozli xabar yoki 📎 fayl ham yuborishingiz mumkin — zayavkaga biriktiriladi.\n\n".
            '❌ Bekor qilish uchun /cancel yozing.',
            ['remove_keyboard' => true]
        );
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
        // Muhimlik so'ralmaydi — u SLA qoidasidan olinadi (saytdagi bilan bir xil).
        //
        // Matndan keyin darrov tasdiqlashga o'tilmaydi: avval rasm, keyin ovoz
        // ketma-ket so'raladi. Ilgari ular umuman so'ralmasdi — foydalanuvchi
        // o'zi yuborishi kerakligini bilsagina qo'shilardi.
        $this->askTicketPhoto($bot, $session, $chatId, $data);
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
        // Saytda fayl/ovoz zayavka yuborilgunga qadar istalgan paytda qo'shiladi —
        // botda ham tavsifdan tasdiqlashgacha bo'lgan barcha bosqichlarda qabul qilamiz.
        $ticketStates = [
            self::STATE_AWAIT_TICKET_TEMPLATE,
            self::STATE_AWAIT_TICKET_TEXT,
            self::STATE_AWAIT_TICKET_PHOTO,
            self::STATE_AWAIT_TICKET_AUDIO,
            self::STATE_AWAIT_TICKET_CONFIRM,
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

        // Ketma-ket so'rash bosqichlarida kutilgan tur kelsa — keyingi qadamga
        // o'tamiz. Boshqa tur kelsa (masalan rasm so'ralganda fayl) u saqlanadi,
        // lekin qadam almashmaydi: foydalanuvchi baribir "O'tkazib yuborish"
        // tugmasi bilan davom eta oladi.
        if ($state === self::STATE_AWAIT_TICKET_PHOTO && $media['type'] === 'photo') {
            $this->api->sendMessage($chatId, $label." qo'shildi (jami: ".$count.').');
            $this->askTicketAudio($bot, $session, $chatId, $data);

            return;
        }

        if ($state === self::STATE_AWAIT_TICKET_AUDIO && in_array($media['type'], ['voice', 'audio'], true)) {
            $this->api->sendMessage($chatId, $label." qo'shildi (jami: ".$count.').');
            $this->setState($session, self::STATE_AWAIT_TICKET_CONFIRM, $data);
            $this->showConfirm($bot, $session, $chatId, $data);

            return;
        }

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
                    Log::warning('Telegram fayl yolini bermadi', ['file_id' => $item['file_id'] ?? null]);

                    continue;
                }

                $content = $this->api->downloadFile((string) $filePath);

                if ($content === null || $content === '') {
                    // Ilgari bu yerda jimgina continue bo'lardi va biriktirma
                    // hech qanday iz qoldirmay yo'qolardi.
                    Log::warning('Telegram fayli bosh keldi, biriktirma qoshilmadi', [
                        'file_path' => $filePath,
                        'ticket_id' => $ticketId,
                    ]);

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

    /**
     * Rasm so'raydi (ixtiyoriy).
     *
     * Ketma-ketlik: matn -> RASM -> ovoz -> tasdiqlash. Har qadam o'tkazib
     * yuborilishi mumkin, chunki zayavkalarning ko'pida na rasm, na ovoz
     * bo'ladi — ularni majburiy qilish foydalanuvchini to'sib qo'yardi.
     */
    private function askTicketPhoto(object $bot, object $session, string $chatId, array $data): void
    {
        $this->setState($session, self::STATE_AWAIT_TICKET_PHOTO, $data);
        $this->api->sendMessage($chatId,
            '🖼 <b>Rasm (skrinshot) yuboring.</b>

'.
            "Rasm bo'lmasa — <b>O'tkazib yuborish</b> tugmasini bosing.",
            ['inline_keyboard' => [[
                ['text' => "⏭ O'tkazib yuborish", 'callback_data' => 'ticket:skip_photo'],
            ]]]
        );
    }

    /** Ovozli xabar so'raydi (ixtiyoriy). */
    private function askTicketAudio(object $bot, object $session, string $chatId, array $data): void
    {
        $this->setState($session, self::STATE_AWAIT_TICKET_AUDIO, $data);
        $this->api->sendMessage($chatId,
            '🎤 <b>Ovozli xabar yuboring.</b>

'.
            "Ovoz bo'lmasa — <b>O'tkazib yuborish</b> tugmasini bosing.",
            ['inline_keyboard' => [[
                ['text' => "⏭ O'tkazib yuborish", 'callback_data' => 'ticket:skip_audio'],
            ]]]
        );
    }

    private function showConfirm(object $bot, object $session, string $chatId, array $data): void
    {
        $text = Str::limit((string) ($data['ticket_text'] ?? ''), self::TICKET_TEXT_MAX);
        $teamName = (string) ($data['ticket_team_name'] ?? '');
        $templateName = (string) ($data['ticket_template_name'] ?? '');
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
            '👥 <b>Guruh:</b> '.htmlspecialchars($teamName ?: '-')."\n".
            ($templateName !== '' ? '📄 <b>Shablon:</b> '.htmlspecialchars($templateName)."\n" : '').
            "📝 <b>Tavsif:</b>\n".htmlspecialchars($text).$mediaText."\n\n".
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
        $teamId = (int) ($data['ticket_team_id'] ?? 0);
        $teamName = (string) ($data['ticket_team_name'] ?? '');
        $media = $data['media'] ?? [];

        try {
            Auth::loginUsingId($user->id);
            // Saytdagi CreateTaskModal ayni shu maydonlarni yuboradi:
            // todo + priority + teamId + category (guruh nomi). Qolgan maydonlar
            // (departament, telefon, F.I.Sh.) controller ichida AD/employee dan olinadi.
            $baseRequest = Request::create('/api/v1/tickets', 'POST', array_filter([
                'todo' => $todo,
                'teamId' => $teamId ?: null,
                'category' => $teamName ?: null,
            ], fn ($v) => $v !== null));

            // `Accept: application/json` — validatsiya yiqilsa FormRequest
            // redirect qilishga urinmasin (bu yerda redirector yo'q), balki
            // ValidationException tashlasin. Uni quyidagi catch ushlaydi.
            $baseRequest->headers->set('Accept', 'application/json');

            // `store()` oddiy `Request` emas, `StoreTicketRequest` talab qiladi
            // va ichida `validated()` chaqiradi.
            //
            // Ilgari bu yerga oddiy `Request` uzatilardi: 2026-09-10 da
            // controller FormRequest'ga o'tkazilgan (bb410869), bot chaqiruvi
            // esa yangilanmagan — natijada botdan zayavka yaratishga urinilganda
            // TypeError chiqib, foydalanuvchi "zayavka yaratishda xatolik"
            // xabarini olardi.
            //
            // So'rov marshrut orqali kelmagani uchun Laravel FormRequest'ni o'zi
            // validatsiya qilmaydi — `validateResolved()` qo'lda chaqiriladi,
            // aks holda `validated()` bo'sh massiv qaytarardi va zayavka
            // matnsiz yaratilardi.
            $request = StoreTicketRequest::createFrom($baseRequest);
            $request->setContainer(app());
            $request->validateResolved();

            $response = app(TicketController::class)->store($request);
            $json = $response->getData(true);
            $ticket = $json['data'] ?? $json;

            // MUHIM: avval HTTP statusini tekshiramiz.
            //
            // Rad etish javoblari ham 'ticket_no' qaytarishi mumkin — masalan
            // "eski zayavkangizni baholang" qoidasi BLOKLOVCHI zayavka raqamini
            // yuboradi. Statusni tekshirmasdan turib uni o'qiganimizda bot
            // "zayavka yaratildi" deb yolg'on xabar berardi, aslida hech narsa
            // yaratilmagan bo'lardi.
            $created = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

            $ticketId = $created ? ($ticket['id'] ?? null) : null;
            $ticketNo = $created ? ($ticket['ticketNumber'] ?? $ticket['ticket_no'] ?? null) : null;

            if ($ticketNo) {
                if ($ticketId) {
                    // Zayavka controller orqali yaratilgani uchun metadata.device
                    // ichida web brauzeri yozilib qolgan bo'ladi (bot HTTP so'rovni
                    // o'zi yasaydi). Uni Telegram kanaliga to'g'rilaymiz.
                    $storedMeta = json_decode(
                        (string) DB::table('tickets')->where('id', (int) $ticketId)->value('metadata'),
                        true
                    );
                    $storedMeta = is_array($storedMeta) ? $storedMeta : [];
                    $storedMeta['device'] = DeviceInfo::telegram();

                    DB::table('tickets')->where('id', (int) $ticketId)->update([
                        'source_id' => 2,
                        'telegram_chat_id' => (string) $chatId,
                        'metadata' => json_encode($storedMeta),
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
                    '📝 <b>Tavsif:</b> '.htmlspecialchars(Str::limit($todo, self::TICKET_TEXT_MAX))."\n".
                    '👥 <b>Guruh:</b> '.htmlspecialchars($teamName ?: '-').$mediaHint."\n".
                    "📌 <b>Holat:</b> 🟦 Yangi\n\n".
                    'Zayavkangiz IT xodimlariga yuborildi. Holatini sayt yoki shu bot orqali kuzatishingiz mumkin.'
                );
            } else {
                $message = $json['message'] ?? 'Zayavka yaratishda xatolik yuz berdi.';

                // Saytdagi bilan bir xil qoida: baholanmagan yakunlangan zayavka bo'lsa,
                // yangisini yaratib bo'lmaydi. Botda darrov baholash tugmasini beramiz.
                $blockingId = null;
                if (! empty($json['unrated_blocking']) && ! empty($json['ticket_no'])) {
                    $blockingId = DB::table('tickets')
                        ->whereNull('deleted_at')
                        ->where('ticket_no', (string) $json['ticket_no'])
                        ->value('id');
                }

                $this->api->sendMessage($chatId, '⚠️ '.htmlspecialchars((string) $message),
                    $blockingId ? [
                        'inline_keyboard' => [
                            [['text' => '⭐ Baholash', 'callback_data' => 'ticket:rate:'.$blockingId]],
                            [['text' => '👁 Zayavkani ochish', 'callback_data' => 'ticket:open:'.$blockingId]],
                        ],
                    ] : null
                );
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

    /** Bir chatdagi tugma bosishlari orasidagi eng kam oraliq. */
    private const CALLBACK_LOCK_SECONDS = 3;

    private function handleCallback(object $bot, object $session, string $chatId, array $callback): void
    {
        $callbackId = $callback['id'] ?? '';
        $data = $callback['data'] ?? '';

        // Tugma "aylanishi" darhol to'xtaydi — javob har doim yuboriladi.
        $this->api->answerCallbackQuery($callbackId);

        // Ketma-ket bosilgan tugmalar: faqat BIRINCHISI ishlanadi.
        //
        // Ilgari 10-20 marta bosilganda har bosish uchun to'liq ishlov ketardi:
        // bot o'nlab xabar yuborib Telegram chegarasiga (429) urilar, sessiya
        // holati bir vaqtda bir necha marta o'zgarib chalkashardi. Qulf
        // atomik — navbat ishchilari parallel bo'lsa ham bittasi o'tadi.
        $lock = Cache::lock('telegram:callback:'.$chatId, self::CALLBACK_LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->processCallback($bot, $session, $chatId, $callbackId, $data);
        } finally {
            $lock->release();
        }
    }

    private function processCallback(object $bot, object $session, string $chatId, string $callbackId, string $data): void
    {
        if ($data === 'cancel') {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId, 'Zayavka bekor qilindi. Bosh menyu:');

            return;
        }

        $this->dispatchMenuAction($bot, $session, $chatId, $data, $callbackId);

        if ($session->user_id === null) {
            // menu:new_ticket o'zi login so'rovini yuborgan bo'lsa, takrorlamaymiz
            if (! in_array($session->state, [self::STATE_AWAIT_CONTACT, self::STATE_AWAIT_USERNAME, self::STATE_AWAIT_SMS_CODE], true)) {
                $this->sendLoginPrompt($bot, $session, $chatId);
            }

            return;
        }

        if (str_starts_with($data, 'ticket:open:')) {
            $this->showTicketDetail($bot, $session, $chatId, (int) substr($data, 12));

            return;
        }

        if ($data === 'ticket:skip_photo') {
            $this->askTicketAudio($bot, $session, $chatId, $this->sessionData($session));

            return;
        }

        if ($data === 'ticket:skip_audio') {
            $sessionData = $this->sessionData($session);
            $this->setState($session, self::STATE_AWAIT_TICKET_CONFIRM, $sessionData);
            $this->showConfirm($bot, $session, $chatId, $sessionData);

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

        if (str_starts_with($data, 'ticket:rate:')) {
            $this->showRatingButtons($bot, $session, $chatId, (int) substr($data, 12));

            return;
        }

        if (str_starts_with($data, 'ticket:return:')) {
            $this->promptReturnReason($bot, $session, $chatId, (int) substr($data, 14));

            return;
        }

        if (preg_match('/^rate:(\d+):([1-5])$/', $data, $m)) {
            $this->saveRating($bot, $session, $chatId, (int) $m[1], (int) $m[2]);

            return;
        }

        if (str_starts_with($data, 'rate:skip:')) {
            $this->finishRating($bot, $session, $chatId, (int) substr($data, 10));

            return;
        }

        if (str_starts_with($data, 'team:page:')) {
            $this->showTeamButtons($bot, $session, $chatId, (int) substr($data, 10));

            return;
        }

        if (str_starts_with($data, 'team:')) {
            $this->onTeamSelected($bot, $session, $chatId, (int) substr($data, 5));

            return;
        }

        if (str_starts_with($data, 'tmpl:')) {
            $this->onTemplateSelected($bot, $session, $chatId, substr($data, 5));

            return;
        }

        if ($data === 'confirm') {
            $sessionData = $this->sessionData($session);
            if (empty($sessionData['ticket_text']) || empty($sessionData['ticket_team_id'])) {
                $this->api->sendMessage($chatId, "⚠️ Zayavka ma'lumotlari to'liq emas. Boshidan boshlaymiz:");
                $this->startTicketFlow($bot, $session, $chatId, []);

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
            // Kirmagan bo'lsa — login so'raymiz va shundan keyin zayavka oqimiga o'tamiz.
            // Kirgan bo'lsa qayta login so'ralmaydi: saytda ham bir marta kiriladi.
            if ($session->user_id === null) {
                $data = $this->sessionData($session);
                $data['pending_action'] = 'new_ticket';
                $this->setState($session, $session->state, $data);
                $this->sendLoginPrompt($bot, $session, $chatId);

                return;
            }

            // Tugma yashirilgan bo'lsa ham chaqiruv kelishi mumkin: eski
            // klaviatura Telegramda saqlanib qoladi. Shuning uchun huquq shu
            // yerda ham tekshiriladi.
            $actor = $this->user($session);

            if ($actor && $this->isStaff($actor)) {
                $this->api->sendMessage($chatId,
                    '⚠️ Support xodimlari bu bot orqali zayavka yarata olmaydi.

'.
                    "Zayavkalarni qabul qilish uchun «📥 Ochiq zayavkalar» bo'limidan foydalaning."
                );
                $this->sendMenu($bot, $session, $chatId);

                return;
            }

            $this->startTicketFlow($bot, $session, $chatId, [
                'username' => $actor?->username,
            ]);

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
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $tickets = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->whereNull('tickets.deleted_at')
            ->where('tickets.requester_user_id', $user->id)
            ->select(
                'tickets.id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.status_id',
                'tickets.client_rating',
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
            $statusEmoji = TicketStatusEmoji::for($ticket->status_id, $ticket->client_rating ?? null);
            $lines[] = $statusEmoji.' <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
                '   '.htmlspecialchars(Str::limit((string) $ticket->subject, 80))."\n".
                '   🗓 '.Carbon::parse($ticket->created_at)->timezone(self::DISPLAY_TIMEZONE)->format('d.m.Y H:i').' — '.htmlspecialchars((string) $ticket->status_name);
        }

        $keyboard = [];
        foreach ($tickets as $ticket) {
            $keyboard[] = [['text' => '🔎 '.$ticket->ticket_no.' — Batafsil', 'callback_data' => 'ticket:open:'.$ticket->id]];
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
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $tickets = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)
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
            $statusEmoji = TicketStatusEmoji::for($ticket->status_id, $ticket->client_rating ?? null);
            $lines[] = $statusEmoji.' <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
                '   '.htmlspecialchars(Str::limit((string) $ticket->subject, 80))."\n".
                '   🙋 '.htmlspecialchars((string) ($ticket->requester_username ?: '-')).' — 🗓 '.Carbon::parse($ticket->created_at)->timezone(self::DISPLAY_TIMEZONE)->format('d.m.Y H:i').' — '.htmlspecialchars((string) $ticket->status_name);
        }

        $keyboard = [];
        foreach ($tickets as $ticket) {
            $keyboard[] = [['text' => '🔎 '.$ticket->ticket_no.' — Batafsil', 'callback_data' => 'ticket:open:'.$ticket->id]];
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
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $tickets = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)
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
            $statusEmoji = TicketStatusEmoji::for($ticket->status_id, $ticket->client_rating ?? null);
            $lines[] = $statusEmoji.' <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
                '   '.htmlspecialchars(Str::limit((string) $ticket->subject, 80))."\n".
                '   🙋 '.htmlspecialchars((string) ($ticket->requester_username ?: '-')).' — '.htmlspecialchars((string) $ticket->status_name);
        }

        $keyboard = [];
        foreach ($tickets as $ticket) {
            $keyboard[] = [['text' => '🔎 '.$ticket->ticket_no.' — Batafsil', 'callback_data' => 'ticket:open:'.$ticket->id]];
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
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $stats = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)->whereNull('deleted_at')
            ->selectRaw('SUM(CASE WHEN status_id IN (1,2,3) THEN 1 ELSE 0 END) as open')
            ->selectRaw('SUM(CASE WHEN status_id IN (4,5,6) THEN 1 ELSE 0 END) as in_progress')
            ->selectRaw('SUM(CASE WHEN status_id IN (7,8) THEN 1 ELSE 0 END) as done')
            ->selectRaw('SUM(CASE WHEN status_id = 9 THEN 1 ELSE 0 END) as rejected')
            ->selectRaw('COUNT(*) as total')
            ->first();

        $myTasks = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)->whereNull('deleted_at')
            ->where('assigned_user_id', $user->id)
            ->whereIn('status_id', [1, 2, 3, 4, 5, 6])
            ->count();

        $myCompleted = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)->whereNull('deleted_at')
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

    /** Egasiz zayavkani navbatdan o'ziga olish. */
    private function canTake(User $user): bool
    {
        return $user->canTakeTickets();
    }

    /** Sherigining ishini o'ziga olish — dispetcherlik huquqi. */
    private function canAssign(User $user): bool
    {
        // Saytdagi bilan bir xil mezon: biriktirish `tickets.assign` huquqiga
        // bog'langan, navbatni ko'rish huquqi (`tickets.view`) uni ochmaydi.
        return $user->canAssignTickets();
    }

    private function canTransition(User $user): bool
    {
        return $user->canTransitionTickets();
    }

    /**
     * @return array{kind: string, os: string|null, browser: string|null, label: string}
     */
    private function deviceOf(object $ticket): array
    {
        $meta = json_decode((string) ($ticket->metadata ?? ''), true);

        return DeviceInfo::normalize(is_array($meta) ? ($meta['device'] ?? null) : null);
    }

    private function deviceIcon(object $ticket): string
    {
        return match ($this->deviceOf($ticket)['kind']) {
            DeviceInfo::KIND_DESKTOP => '💻',
            DeviceInfo::KIND_MOBILE => '📱',
            DeviceInfo::KIND_TABLET => '📟',
            DeviceInfo::KIND_TELEGRAM => '✈️',
            default => '❔',
        };
    }

    private function deviceLabel(object $ticket): string
    {
        return $this->deviceOf($ticket)['label'];
    }

    private function fetchTicket(int $ticketId, User $user, bool $forWork = false): ?object
    {
        return \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user, ! $forWork)
            ->leftJoin('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->leftJoin('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->leftJoin('users as req_user', 'tickets.requester_user_id', '=', 'req_user.id')
            ->leftJoin('users as asg_user', 'tickets.assigned_user_id', '=', 'asg_user.id')
            ->whereNull('tickets.deleted_at')
            ->where('tickets.id', $ticketId)
            ->select(
                'tickets.id', 'tickets.organization_id',
                'tickets.ticket_no',
                'tickets.subject',
                'tickets.bxm_code', 'tickets.local_code', 'tickets.region_id', 'tickets.support_scope', 'tickets.assigned_team_id',
                'tickets.status_id',
                'tickets.assigned_user_id',
                'tickets.requester_user_id',
                'tickets.client_rating',
                'tickets.created_at',
                'tickets.metadata',
                'tickets.solution_comment',
                'tickets.rejection_reason',
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
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user);

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

        $statusEmoji = TicketStatusEmoji::for($ticket->status_id, $ticket->client_rating ?? null);
        $priorityMap = ['Kritik' => '🔴', 'Yuqori' => '🟠', "O'rta" => '🟡', 'Past' => '🟢'];
        $priority = (string) $ticket->priority_name;
        $priorityEmoji = $priorityMap[$priority] ?? '';
        $ratingText = ! empty($ticket->client_rating)
            ? "\n⭐ Baho: <b>".(int) $ticket->client_rating.'/5</b> '.str_repeat('⭐', (int) $ticket->client_rating)
            : '';

        // Saytdagidek: yechim va rad etish sababi zayavka kartochkasida
        // ko'rinib turadi.
        $solutionText = ! empty($ticket->solution_comment)
            ? "\n\n💡 <b>Yechim:</b> ".htmlspecialchars(Str::limit((string) $ticket->solution_comment, 400))
            : '';
        $rejectionText = ! empty($ticket->rejection_reason)
            ? "\n\n📌 <b>Rad etish sababi:</b> ".htmlspecialchars(Str::limit((string) $ticket->rejection_reason, 400))
            : '';

        $text =
            '🎫 <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
            '📝 '.htmlspecialchars(Str::limit((string) $ticket->subject, self::TICKET_TEXT_MAX))."\n\n".
            '📊 Holat: '.$statusEmoji.' '.htmlspecialchars((string) $ticket->status_name)."\n".
            '⚡ Muhimlik: '.$priorityEmoji.' '.htmlspecialchars($priority ?: '-')."\n".
            '🙋 So\'rovchi: <b>'.htmlspecialchars((string) ($ticket->requester_username ?: '-'))."</b>\n".
            '👤 Ijrochi: '.htmlspecialchars((string) ($ticket->assignee_username ?: '-'))."\n".
            '🗓 Yaratilgan: '.Carbon::parse($ticket->created_at)->timezone(self::DISPLAY_TIMEZONE)->format('d.m.Y H:i')."\n".
            $this->deviceIcon($ticket).' Qurilma: '.htmlspecialchars($this->deviceLabel($ticket)).$ratingText.
            $solutionText.$rejectionText;

        $keyboard = $this->ticketActionButtons($ticket, $user);
        $keyboard[] = [['text' => '🔁 Yangilash', 'callback_data' => 'ticket:open:'.$ticket->id]];
        $keyboard[] = [
            ['text' => '🆕 Yangi zayavka', 'callback_data' => 'menu:new_ticket'],
            ['text' => '🏠 Bosh menyu', 'callback_data' => 'menu:home'],
        ];

        $this->api->sendMessage($chatId, $text, [
            'inline_keyboard' => $keyboard,
        ]);

        $this->sendTicketAttachments($chatId, (int) $ticket->id);
    }

    /** Bir zayavka uchun botga yuboriladigan maksimal fayl soni. */
    private const MAX_ATTACHMENTS_SENT = 10;

    /**
     * Zayavka biriktirmalarini chatga yuboradi.
     *
     * Bot orqali qo'shilgan fayllar uchun telegram_file_id saqlangan bo'ladi —
     * o'shani qayta yuborish yetarli (Telegram fayl allaqachon o'zida, trafik
     * ketmaydi). Saytdan yuklangan fayllarda file_id bo'lmaydi, ular diskdan
     * o'qib yuboriladi.
     */
    private function sendTicketAttachments(string $chatId, int $ticketId): void
    {
        $attachments = DB::table('attachments')
            ->where('attachable_type', Ticket::class)
            ->where('attachable_id', $ticketId)
            ->orderBy('id')
            ->limit(self::MAX_ATTACHMENTS_SENT + 1)
            ->get();

        if ($attachments->isEmpty()) {
            return;
        }

        $extra = max(0, $attachments->count() - self::MAX_ATTACHMENTS_SENT);
        foreach ($attachments->take(self::MAX_ATTACHMENTS_SENT) as $att) {
            try {
                $this->sendOneAttachment($chatId, $att);
            } catch (\Throwable $e) {
                Log::warning('Biriktirmani botga yuborib bo\'lmadi', [
                    'attachment_id' => $att->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($extra > 0) {
            $this->api->sendMessage($chatId, "📎 Yana {$extra} ta fayl bor — to'liq ro'yxatni saytda ko'ring.");
        }
    }

    private function sendOneAttachment(string $chatId, object $att): void
    {
        $mime = strtolower((string) ($att->mime_type ?? ''));
        $name = (string) ($att->original_name ?? 'fayl');

        [$method, $field] = match (true) {
            str_contains($mime, 'image') => ['sendPhoto', 'photo'],
            str_contains($mime, 'audio') => ['sendVoice', 'voice'],
            str_contains($mime, 'video') => ['sendVideo', 'video'],
            default => ['sendDocument', 'document'],
        };

        $caption = '📎 '.htmlspecialchars($name);

        // 1) Tez yo'l — Telegram'dagi mavjud fayl
        if (! empty($att->telegram_file_id)) {
            $this->api->sendMedia($chatId, $method, $field, (string) $att->telegram_file_id, $caption);

            return;
        }

        // 2) Saytdan yuklangan fayl — diskdan o'qiymiz
        $path = (string) ($att->storage_path ?? '');
        $disk = (string) ($att->storage_disk ?: 'public');

        foreach (array_unique([$disk, 'public', 'local']) as $candidate) {
            if ($path !== '' && Storage::disk($candidate)->exists($path)) {
                $this->api->sendMedia($chatId, $method, $field, [
                    'contents' => Storage::disk($candidate)->get($path),
                    'filename' => $name,
                ], $caption);

                return;
            }
        }

        Log::warning('Biriktirma fayli diskda topilmadi', ['attachment_id' => $att->id, 'path' => $path]);
    }

    private function ticketActionButtons(object $ticket, User $user): array
    {
        $rows = [];
        $active = in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true);
        $isAssignee = (int) $ticket->assigned_user_id === $user->id;
        $isRequester = (int) $ticket->requester_user_id === $user->id;
        $isResolved = in_array((int) $ticket->status_id, [7, 8], true);
        // Boshqa xodimda turgan zayavkani yopib bo'lmaydi — saytdagi qoida bilan
        // bir xil: avval "O'zimga olish", so'ng yakunlash.
        $canWork = \App\Support\RegionalRouting::canWork($user, $ticket);
        $actor = $canWork && ($isAssignee || ($ticket->assigned_user_id === null && $this->canTransition($user)));

        if ($isRequester && $isResolved && empty($ticket->client_rating)) {
            $rows[] = [
                ['text' => '⭐ Baholash', 'callback_data' => 'ticket:rate:'.$ticket->id],
            ];
        }

        if ($isRequester && $isResolved && empty($ticket->client_rating)) {
            $rows[] = [
                ['text' => '↩️ Qaytarish', 'callback_data' => 'ticket:return:'.$ticket->id],
            ];
        }

        $canPickUp = $canWork && ($ticket->assigned_user_id === null ? $this->canTake($user) : $this->canAssign($user));
        if ($canPickUp && ! $isAssignee && $active) {
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
        $openRejected = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)->whereNull('deleted_at')
            ->where('assigned_user_id', $user->id)
            ->where('status_id', 9)
            ->where('id', '!=', $ticketId)
            ->exists();

        if ($openRejected) {
            return "Sizda yopilmagan qaytarilgan (reject) zayavka bor. Avval uni yakunlang, so'ng yangi zayavka qabul qilishingiz mumkin!";
        }

        $activeCount = \App\Support\RegionalRouting::constrain(DB::table('tickets'), $user)->whereNull('deleted_at')
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
        if (! $user || ! $this->canTake($user)) {
            $this->api->sendMessage($chatId, "❌ Sizda zayavkani o'ziga olish huquqi yo'q.");

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user, true);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        // Sherigining ishini tortib olish — alohida (dispetcherlik) huquqi.
        if ($ticket->assigned_user_id !== null && ! $this->canAssign($user)) {
            $this->api->sendMessage($chatId, "❌ Zayavka boshqa xodimga biriktirilgan. Uni o'ziga olish uchun biriktirish huquqi kerak.");

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
            $this->sendLoginPrompt($bot, $session, $chatId);

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
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user, true);
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
                // `started_at` faqat bo'sh bo'lsa yoziladi (saytdagi qoida bilan
                // bir xil): aks holda saytda qabul qilingan zayavka botdan
                // jarayonga o'tkazilganda SLA taymeri nolga qaytardi.
                DB::table('tickets')->where('id', $ticketId)->whereNull('started_at')->update(['started_at' => now()]);
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
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user, true);
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

        // Saytdagidek: yechim matnisiz zayavka yopilmaydi. Matn `tickets`
        // ustuniga ham, yozishmaga ham yoziladi (qarang: insertThreadEntry).
        $this->setState($session, self::STATE_AWAIT_SOLUTION_COMMENT, ['resolve_ticket_id' => $ticketId]);
        $this->api->sendMessage($chatId,
            '✅ <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b> zayavkasini yakunlash uchun <b>yechim</b> matnini yozing.\n\n".
            "Masalan: <i>\"Printer drayveri qayta o'rnatildi va sinovdan o'tkazildi\"</i>",
            ['remove_keyboard' => true]
        );
    }

    /**
     * Yechim matni kelgach zayavkani 'Hal qilindi' holatiga o'tkazadi.
     *
     * Sayt (TicketController::update) bilan bir xil ish bajariladi:
     * solution_comment ustuni yoziladi, sarflangan vaqt hisoblanadi va yechim
     * yozishmaga `kind=solution` belgisi bilan qo'shiladi.
     */
    private function onSolutionComment(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $ticketId = (int) ($data['resolve_ticket_id'] ?? 0);

        if (! $ticketId) {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $solution = trim($text);
        if (mb_strlen($solution) < 3) {
            $this->api->sendMessage($chatId, '⚠️ Yechim matni juda qisqa (kamida 3 ta belgi). Qaytadan yozing:');

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user, true);
        if (! $ticket) {
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        $isAssignee = (int) $ticket->assigned_user_id === $user->id;
        if ((! $isAssignee && ! $this->canTransition($user)) || ! in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true)) {
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Zayavkani hozirgi holatida yakunlab bo'lmaydi.");

            return;
        }

        try {
            DB::transaction(function () use ($ticketId, $user, $bot, $solution) {
                // Ustunlar holatdan OLDIN yoziladi: holat o'zgarishi hodisasi
                // (TicketStatusChanged) bildirishnomani navbatga qo'yadi va u
                // yechim matnini bazadan qayta o'qiydi.
                $row = DB::table('tickets')->where('id', $ticketId)->first(['started_at']);
                $update = [
                    'resolved_at' => now(),
                    'solution_comment' => $solution,
                    'updated_at' => now(),
                ];

                // rejection_reason ATAYLAB tozalanmaydi — saytdagidek, rad etish
                // sababi yozishma tarixining bir qismi bo'lib qoladi.
                if (! empty($row?->started_at)) {
                    // Carbon 3 da diffInMinutes ISHORALI qiymat qaytaradi
                    // (o'tmish uchun manfiy) — abs() bo'lmasa vaqt doim 1 daqiqa
                    // bo'lib qolardi.
                    $update['spent_minutes'] = max(1, (int) abs(now()->diffInMinutes(Carbon::parse($row->started_at))));
                }

                DB::table('tickets')->where('id', $ticketId)->update($update);

                $this->insertThreadEntry((int) $bot->organization_id, $ticketId, $user->id, 'solution', $solution);

                $this->transitionStatus($ticketId, 7, $user, 'Telegram bot orqali hal qilindi');
            });
        } catch (\Throwable $e) {
            Log::error('Bot zayavka holatini o\'zgartirish xatosi', ['error' => $e->getMessage()]);
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Holatni o'zgartirishda xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");

            return;
        }

        $this->resetSession($session);
        $this->api->sendMessage($chatId,
            "✅ Zayavka 'Hal qilindi' holatiga o'tkazildi.\n\n".
            '💡 Yechim: '.htmlspecialchars(Str::limit($solution, 200))
        );
        $this->showTicketDetail($bot, $session, $chatId, $ticketId);
    }

    private function rejectTicket(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user || ! $this->canTransition($user)) {
            $this->api->sendMessage($chatId, "❌ Bu amal uchun huquqingiz yo'q.");

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user, true);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        if (! in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true)) {
            $this->api->sendMessage($chatId, "⚠️ Zayavkani hozirgi holatidan 'Rad etildi' holatiga o'tkazib bo'lmaydi.");

            return;
        }

        // Saytdagidek: sababsiz rad etilmaydi. Sabab `tickets.rejection_reason`
        // ustuniga ham, yozishmaga ham yoziladi (qarang: insertThreadEntry).
        $this->setState($session, self::STATE_AWAIT_REJECT_REASON, ['reject_ticket_id' => $ticketId]);
        $this->api->sendMessage($chatId,
            '❌ <b>'.htmlspecialchars((string) $ticket->ticket_no).'</b> zayavkasini rad etish uchun <b>sababini</b> yozing.'."\n\n".
            "Masalan: <i>\"Zayavka noto'g'ri bo'limga tushgan\"</i>",
            ['remove_keyboard' => true]
        );
    }

    /**
     * Rad etish sababi kelgach zayavkani 'Rad etildi' holatiga o'tkazadi.
     *
     * Sayt (TicketController::update) bilan bir xil: rejection_reason ustuni
     * yoziladi va sabab yozishmaga `kind=rejection` belgisi bilan qo'shiladi.
     */
    private function onRejectReason(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $ticketId = (int) ($data['reject_ticket_id'] ?? 0);

        if (! $ticketId) {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $reason = trim($text);
        if (mb_strlen($reason) < 3) {
            $this->api->sendMessage($chatId, '⚠️ Sabab juda qisqa (kamida 3 ta belgi). Qaytadan yozing:');

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user, true);
        if (! $ticket) {
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        if (! $this->canTransition($user) || ! in_array((int) $ticket->status_id, [1, 2, 3, 4, 5, 6], true)) {
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Zayavkani hozirgi holatida rad etib bo'lmaydi.");

            return;
        }

        try {
            DB::transaction(function () use ($ticketId, $user, $bot, $reason) {
                // Ustun holatdan OLDIN yoziladi — bildirishnoma sababni
                // bazadan o'qiydi (qarang: onSolutionComment).
                DB::table('tickets')->where('id', $ticketId)->update([
                    'rejection_reason' => $reason,
                    'updated_at' => now(),
                ]);

                $this->insertThreadEntry((int) $bot->organization_id, $ticketId, $user->id, 'rejection', $reason);

                $this->transitionStatus($ticketId, 9, $user, 'Telegram bot orqali rad etildi: '.$reason);
            });
        } catch (\Throwable $e) {
            Log::error('Bot zayavka holatini o\'zgartirish xatosi', ['error' => $e->getMessage()]);
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Holatni o'zgartirishda xatolik yuz berdi. Keyinroq qayta urinib ko'ring.");

            return;
        }

        $this->resetSession($session);
        $this->api->sendMessage($chatId,
            "✅ Zayavka 'Rad etildi' holatiga o'tkazildi.\n\n".
            '📌 Sabab: '.htmlspecialchars(Str::limit($reason, 200))
        );
        $this->showTicketDetail($bot, $session, $chatId, $ticketId);
    }

    private function showRatingButtons(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        if ((int) $ticket->requester_user_id !== $user->id) {
            $this->api->sendMessage($chatId, '❌ Faqat zayavka muallifi baholay oladi.');

            return;
        }

        if (! in_array((int) $ticket->status_id, [7, 8], true)) {
            $this->api->sendMessage($chatId, "⚠️ Faqat 'Hal qilindi' holatidagi zayavkalarni baholash mumkin.");

            return;
        }

        if (! empty($ticket->client_rating)) {
            $this->api->sendMessage($chatId, 'ℹ️ Siz bu zayavkani allaqachon baholagansiz ('.(int) $ticket->client_rating.'/5).');

            return;
        }

        $rows = [];
        foreach ([1, 2, 3, 4, 5] as $star) {
            $rows[] = [['text' => str_repeat('⭐', $star), 'callback_data' => 'rate:'.$ticketId.':'.$star]];
        }

        $this->api->sendMessage($chatId,
            '⭐ <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b> zayavkasi uchun xizmat sifatini baholang:\n\n".
            "1 — juda yomon, 5 — a'lo",
            ['inline_keyboard' => $rows]
        );
    }

    private function saveRating(object $bot, object $session, string $chatId, int $ticketId, int $rating): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user);
        if (! $ticket || (int) $ticket->requester_user_id !== $user->id || ! empty($ticket->client_rating)) {
            $this->api->sendMessage($chatId, '⚠️ Baholash amalga oshirilmadi. Zayavka holatini tekshiring.');

            return;
        }

        // metadata'ni PHP tomonda yangilaymiz.
        //
        // Ilgari bu yerda jsonb_set() ishlatilardi — bu PostgreSQL funksiyasi,
        // loyiha esa MySQL/MariaDB da ishlaydi. Natijada har bir baholash
        // "FUNCTION jsonb_set does not exist" xatosi bilan uzilardi.
        // PHP tomonda yig'ish DB'ga bog'liq bo'lmaydi va qiymat ham bog'lanadi.
        $meta = json_decode((string) DB::table('tickets')->where('id', $ticketId)->value('metadata'), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['rating'] = $rating;

        DB::table('tickets')->where('id', $ticketId)->update([
            'client_rating' => $rating,
            'metadata' => json_encode($meta),
            'updated_at' => now(),
        ]);

        $this->setState($session, self::STATE_AWAIT_RATING_FEEDBACK, [
            'rating_ticket_id' => $ticketId,
            'rating' => $rating,
        ]);

        $this->api->sendMessage($chatId,
            "⭐ Rahmat! Bahoyingiz (<b>{$rating}/5</b>) qabul qilindi.\n\n".
            'Xohlasangiz qisqa izoh yozing yoki «Izohsiz yakunlash» tugmasini bosing:',
            [
                'inline_keyboard' => [
                    [['text' => '⏭ Izohsiz yakunlash', 'callback_data' => 'rate:skip:'.$ticketId]],
                ],
            ]
        );
    }

    private function onRatingFeedback(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $ticketId = (int) ($data['rating_ticket_id'] ?? 0);
        $rating = (int) ($data['rating'] ?? 0);

        if (! $ticketId || $rating < 1) {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        $data['rating_feedback'] = mb_substr(trim($text), 0, 500);
        $this->setState($session, self::STATE_IDLE, []);
        $this->finishRating($bot, $session, $chatId, $ticketId, $rating, $data['rating_feedback'] ?? null);
    }

    private function finishRating(object $bot, object $session, string $chatId, int $ticketId, ?int $rating = null, ?string $feedback = null): void
    {
        $user = $this->user($session);
        $data = $this->sessionData($session);

        if ($rating === null) {
            $rating = (int) ($data['rating'] ?? 0);
        }
        if ($feedback === null) {
            $feedback = $data['rating_feedback'] ?? null;
        }

        if (! $user || $rating < 1) {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        // Matn va belgi saytdagi baholash bilan AYNI: yozishmada Telegram'dan
        // kelgan baho ham veb'dan kelgani kabi ko'k "Baho" yozuvi bo'lib
        // ko'rinadi. Ilgari bu yerda `Rating: 5/5` deb yozilar va `kind`
        // qo'yilmagani uchun oddiy kulrang izoh bo'lib qolardi.
        $body = 'Zayavka baholandi: '.$rating.'/5 '.str_repeat('⭐', $rating);
        if ($feedback !== null && trim($feedback) !== '') {
            $body .= "\n\n".trim($feedback);
        }

        $this->insertComment((int) $bot->organization_id, $ticketId, $user->id, $body, 'rating');

        // Ijrochiga baho haqida xabar
        $assigneeId = DB::table('tickets')->where('id', $ticketId)->value('assigned_user_id');
        if ($assigneeId) {
            app(TelegramNotifierService::class)->sendToUser((int) $bot->organization_id, (int) $assigneeId,
                '⭐ <b>Zayavkangizga baho berildi</b>'."\n\n".
                '🎫 <b>'.htmlspecialchars((string) ($this->fetchTicket($ticketId, $user)?->ticket_no ?? '#'.$ticketId))."</b>\n".
                '⭐ Baho: '.$rating.'/5 '.str_repeat('⭐', $rating).($feedback ? "\n💬 Izoh: ".htmlspecialchars($feedback) : '')
            );
        }

        $this->resetSession($session);
        $this->api->sendMessage($chatId, '✅ Baholangiz saqlandi. Fikringiz uchun rahmat!');
        $this->sendMenu($bot, $session, $chatId);
    }

    private function promptReturnReason(object $bot, object $session, string $chatId, int $ticketId): void
    {
        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user);
        if (! $ticket) {
            $this->api->sendMessage($chatId, "⚠️ Zayavka topilmadi yoki o'chirilgan.");

            return;
        }

        if ((int) $ticket->requester_user_id !== $user->id) {
            $this->api->sendMessage($chatId, '❌ Faqat zayavka muallifi qaytarishi mumkin.');

            return;
        }

        if (! in_array((int) $ticket->status_id, [7, 8], true)) {
            $this->api->sendMessage($chatId, "⚠️ Faqat 'Hal qilindi' holatidagi zayavkalarni qaytarish mumkin.");

            return;
        }

        if (! empty($ticket->client_rating)) {
            $this->api->sendMessage($chatId, "⚠️ Bu zayavka allaqachon baholangan (".(int) $ticket->client_rating."/5). Baholangan zayavkani qaytarib bo'lmaydi.");

            return;
        }

        $this->setState($session, self::STATE_AWAIT_TICKET_RETURN_REASON, ['return_ticket_id' => $ticketId]);
        $this->api->sendMessage($chatId,
            '↩️ <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b> zayavkasini qaytarish uchun <b>sabab</b> yozing.\n\n".
            "Masalan: <i>\"Muammo hal bo'lmadi, kompyuter hali ham ishlamayapti\"</i>\n\n".
            "❌ Bekor qilish uchun /cancel yozing yoki pastdagi tugmani bosing:",
            [
                'inline_keyboard' => [
                    [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel']],
                ],
            ]
        );
    }

    private function onTicketReturnReason(object $bot, object $session, string $chatId, string $text): void
    {
        $data = $this->sessionData($session);
        $ticketId = (int) ($data['return_ticket_id'] ?? 0);

        if (! $ticketId) {
            $this->resetSession($session);
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        $user = $this->user($session);
        if (! $user) {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        if (mb_strlen(trim($text)) < 3) {
            $this->api->sendMessage($chatId, '⚠️ Sabab juda qisqa (kamida 3 ta belgi). Qaytadan yozing:');

            return;
        }

        $ticket = $this->fetchTicket($ticketId, $user);
        if (! $ticket || (int) $ticket->requester_user_id !== $user->id) {
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Zayavkani qaytarish imkoni yo'q.");
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        if (! empty($ticket->client_rating)) {
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Bu zayavka allaqachon baholangan (".(int) $ticket->client_rating."/5). Baholangan zayavkani qaytarib bo'lmaydi.");
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        if (! in_array((int) $ticket->status_id, [7, 8], true)) {
            $this->resetSession($session);
            $this->api->sendMessage($chatId, "⚠️ Faqat 'Hal qilindi' holatidagi zayavkani qaytarish mumkin.");
            $this->sendMenu($bot, $session, $chatId);

            return;
        }

        // `kind = rejection` — saytdagi TicketController::appendThreadEntry bilan
        // bir xil. Ilgari bu oddiy izoh bo'lib, ustiga inglizcha "Solution
        // rejected:" prefiksi qo'shilardi. Natijada zayavka kartochkasida sabab
        // IKKI marta chiqardi: bir marta oddiy izoh sifatida, yana bir marta
        // `rejection_reason` ustunidan chiziladigan "Rad etish sababi" bloki
        // ko'rinishida — TaskDetailPage `hasThreadEntry('rejection')` ni topa
        // olmagani uchun zaxira blokni ham chizib yuborardi.
        $this->insertComment((int) $bot->organization_id, $ticketId, $user->id, trim($text), 'rejection');

        // Holat 9 = "Radd etildi", 2 (Ochiq) emas.
        //
        // Ilgari bu yerda `status_id = 2` yozilardi: murojaatchi yechimni rad
        // etsa, zayavka rad etilgan emas, yana OCHIQ bo'lib ko'rinardi. Sayt
        // esa ayni amalda 9 ni qo'yadi (TicketController::update,
        // status = 'rejected' -> [9]) — ya'ni bot bilan sayt zid edi.
        //
        // Bu faqat ko'rinish masalasi emas: botning o'z qoidasi
        // (`assignmentBlockReason`) "yopilmagan qaytarilgan zayavka" ni aynan
        // `status_id = 9` bo'yicha qidiradi, shuning uchun botdan qaytarilgan
        // zayavkalarda u hech qachon ishlamasdi.
        //
        // `rejection_reason` ham yoziladi: sayt kartochkasi va holat o'zgarishi
        // bildirishnomasi sababni shu ustundan o'qiydi.
        DB::table('tickets')->where('id', $ticketId)->update([
            'status_id' => 9,
            'rejection_reason' => trim($text),
            'updated_at' => now(),
        ]);

        DB::table('ticket_status_history')->insert([
            'ticket_id' => $ticketId,
            'from_status_id' => (int) $ticket->status_id,
            'to_status_id' => 9,
            'changed_by' => $user->id,
            'source_id' => 2,
            'action' => 'RETURNED_BY_REQUESTER',
            'reason' => mb_substr(trim($text), 0, 2000),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);

        // Status o'zgargani haqida ijrochiga bildirishnoma yuborish uchun event
        event(new TicketStatusChanged(Ticket::query()->find($ticketId), (int) $ticket->status_id, 9, $user->id));

        $this->resetSession($session);
        $this->api->sendMessage($chatId,
            '↩️ Zayavka <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b> qaytarildi va <b>rad etildi</b> holatiga o'tkazildi.\n\n".
            'Sabab: '.htmlspecialchars(Str::limit(trim($text), 200))
        );
        $this->sendMenu($bot, $session, $chatId);
    }

    /**
     * Yechim yoki rad etish matnini zayavka yozishmasiga qo'shadi.
     *
     * Saytdagi TicketController::appendThreadEntry bilan bir xil ish:
     * `solution_comment` va `rejection_reason` bitta ustun bo'lgani uchun
     * zayavka ikkinchi marta yopilganda eskisi ustiga yozilib yo'qolardi.
     * Yozishma esa qo'shiluvchi jadval — butun tarix saqlanib qoladi.
     * `metadata.kind` orqali frontend uni yashil (yechim) yoki qizil
     * (rad etish) ramkada ko'rsatadi.
     *
     * CommentAdded hodisasi ataylab otilmaydi: holat o'zgargani haqida
     * bildirishnoma allaqachon ketadi, aks holda bitta amal uchun ikki marta
     * xabar yuborilardi.
     */
    private function insertThreadEntry(int $organizationId, int $ticketId, int $authorUserId, string $kind, string $body): void
    {
        $text = trim($body);
        if ($text === '') {
            return;
        }

        // Ayni matn ketma-ket ikki marta yozilmasin.
        $lastBody = Comment::where('commentable_type', Ticket::class)
            ->where('commentable_id', $ticketId)
            ->orderByDesc('id')
            ->value('body');

        if ($lastBody === $text) {
            return;
        }

        $comment = new Comment;
        $comment->organization_id = $organizationId;
        $comment->commentable_type = Ticket::class;
        $comment->commentable_id = $ticketId;
        $comment->author_user_id = $authorUserId;
        $comment->type_id = 1;   // PUBLIC
        $comment->source_id = 2; // TELEGRAM
        $comment->body = $text;
        $comment->metadata = json_encode(['kind' => $kind], JSON_UNESCAPED_UNICODE);
        $comment->save();
    }

    private function insertComment(int $organizationId, int $ticketId, int $authorUserId, string $body, ?string $kind = null): void
    {
        // Ilgari bu yerda to'g'ridan-to'g'ri DB::table('comments')->insert()
        // ishlatilardi. Natijada CommentAdded hodisasi otilmasdi va botdan
        // yozilgan izoh ikkinchi tomonga Telegram orqali yetib bormasdi.
        // Endi sayt bilan bir xil domen servisidan o'tadi.
        app(AddCommentService::class)->execute([
            'organization_id' => $organizationId,
            'commentable_type' => Ticket::class,
            'commentable_id' => $ticketId,
            'author_user_id' => $authorUserId,
            'type_id' => 1,      // PUBLIC
            'source_id' => 2,    // TELEGRAM
            'body' => $body,
            // `kind` berilsa yozishmada ajratib ko'rsatiladi (TicketResource).
            'metadata' => $kind ? json_encode(['kind' => $kind], JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    private function sendHelp(object $bot, object $session, string $chatId): void
    {
        $user = $this->user($session);
        $staff = $user && $this->isStaff($user);

        $text =
            "ℹ️ <b>Yordam</b>\n\n".
            '🆕 <b>Yangi zayavka</b> — saytdagi forma bilan bir xil: guruh, shablon, tavsif, muhimlik
'.
            "📄 <b>Shablon</b> — guruhga mos tayyor matn; tanlab, kerakli joyini to'ldirasiz
".
            '🖼 <b>Fayl biriktirish</b> — tavsif yozayotganda rasm, ovozli xabar, video yoki hujjat yuborasiz
'.
            "📋 <b>Mening zayavkalarim</b> — o'z zayavkalaringiz holatini ko'rasiz\n".
            "👁 <b>Zayavkani ochish</b> — ro'yxatdagi zayavka ustiga bosib, tafsilotini ko'rasiz\n".
            "⭐ <b>Baholash</b> — hal qilingan zayavkani 1-5 gacha baholaysiz\n".
            "↩️ <b>Qaytarish</b> — hal qilingan zayavka muammosi hal bo'lmasa, sabab bilan qaytarasiz";

        if ($staff) {
            $text .= "\n".
                "📥 <b>Ochiq zayavkalar</b> — barcha ochiq zayavkalar ro'yxati\n".
                "🛠 <b>Mening vazifalarim</b> — sizga biriktirilgan zayavkalar\n".
                "📊 <b>Statistika</b> — zayavkalar bo'yicha umumiy ko'rsatkichlar\n".
                "📥 <b>O'zimga olish</b> — zayavkani qabul qilish (huquq bo'yicha)\n".
                "▶️ <b>Jarayonga o'tkazish</b> — zayavkani ishga olish\n".
                '✅ <b>Hal qilindi</b> / ❌ <b>Rad etish</b> — zayavkani yakunlash';
        }

        $text .= "\n\n🔐 <b>Kirish 2 bosqichda</b>:\n".
            "1️⃣ Pochtangizni yozasiz\n".
            "2️⃣ Raqamingizga kelgan bir martalik kodni kiritasiz\n";

        $text .= "\nKomandalar:\n".
            "/start — asosiy menyu\n".
            "/cancel — amalni bekor qilish\n".
            "/logout — tizimdan chiqish (qayta kirishda ikkala bosqich qaytadan so'raladi)\n".
            '/help — yordam';

        $this->api->sendMessage($chatId, $text, [
            'inline_keyboard' => $this->menuRows($user),
        ]);
    }

    private function sendMenu(object $bot, object $session, string $chatId, ?string $header = null): void
    {
        $user = $this->user($session);
        // Standart sarlavha neytral. Ilgari bu yerda "Xush kelibsiz, X!" turardi
        // va sendMenu() deyarli har amaldan keyin chaqirilgani uchun salomlashish
        // takror-takror chiqaverardi. Salomlashish endi faqat /start da.
        $header = $header ?? 'Bosh menyu:';

        $this->api->sendMessage($chatId, $header, [
            'keyboard' => $this->menuRows($user),
            'resize_keyboard' => true,
        ]);
    }

    private function menuRows(?User $user): array
    {
        // "Yangi zayavka" faqat oddiy foydalanuvchida.
        //
        // Support/admin/superadmin bu bot orqali zayavka YARATMAYDI — ular
        // zayavkani qabul qiladi. Ilgari tugma hammaga ko'rinardi va xodimlar
        // o'z nomidan murojaat ochib yuborardi.
        $rows = [];

        if (! $user || ! $this->isStaff($user)) {
            $rows[] = [['text' => '🆕 Yangi zayavka']];
        }

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
        // Sessiya to'liq tozalanadi — telefon raqam ham. Chiqqandan keyin kirish
        // yana 1-qadamdan (kontakt) boshlanadi.
        DB::table('telegram_chat_sessions')->where('id', $session->id)->update([
            'user_id' => null,
            'state' => self::STATE_AWAIT_CONTACT,
            'data' => json_encode([]),
            'last_activity_at' => now(),
            'updated_at' => now(),
        ]);
        $session->user_id = null;
        $session->state = self::STATE_AWAIT_CONTACT;
        $session->data = json_encode([]);

        // verified_at tozalanmasa, keyingi xabarda session() hisobni avtomatik tiklab yuboradi.
        DB::table('telegram_accounts')
            ->where('organization_id', $bot->organization_id)
            ->where('telegram_user_id', (string) $session->telegram_user_id)
            ->update([
                'verified_at' => null,
                'updated_at' => now(),
            ]);

        $this->api->sendMessage($chatId,
            "👋 Tizimdan chiqdingiz.\n\n".
            "🔐 Qayta kirish uchun:\n".
            "1️⃣ Pochtangizni yozing\n".
            '2️⃣ Raqamingizga kelgan kodni kiriting',
            ['remove_keyboard' => true]
        );
    }

    private function isStaff(User $user): bool
    {
        return $user->isSupportStaff();
    }

    /**
     * Kirish so'rovi. Qaysi qadam ko'rsatilishi sessiyadagi telefon raqamga
     * bog'liq: raqam hali yuborilmagan bo'lsa — 1-qadam, yuborilgan bo'lsa —
     * 2-qadam (pochta).
     */
    private function sendLoginPrompt(object $bot, object $session, string $chatId): void
    {
        $data = $this->sessionData($session);

        if (empty($data['phone'])) {
            $this->setState($session, self::STATE_AWAIT_CONTACT, $data);
            $this->api->sendMessage($chatId,
                "🔐 Avval tizimga kirishingiz kerak.\n\n".
                "1️⃣ Pastdagi tugma orqali <b>telefon raqamingizni</b> yuboring\n".
                "2️⃣ So'ng <b>pochtangizni</b> yozasiz\n".
                '3️⃣ Raqamingizga kelgan kodni kiritasiz',
                $this->contactKeyboard()
            );

            return;
        }

        $this->setState($session, self::STATE_AWAIT_USERNAME, $data);
        $this->api->sendMessage($chatId,
            "2️⃣ Davom etamiz — <b>pochtangizni</b> yozing.\n".
            "<i>Masalan: ism.familiya@xb.uz</i>\n\n".
            'Raqamingizga bir martalik kod yuboriladi.',
            ['remove_keyboard' => true]
        );
    }

    private function contactKeyboard(): array
    {
        $rows = [
            [['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]],
        ];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }

    private function handleContact(object $bot, object $session, string $chatId, array $message): void
    {
        // Allaqachon kirgan bo'lsa, kontakt yuborish hech narsani o'zgartirmaydi —
        // aks holda foydalanuvchi kirish oqimiga qaytib qolardi.
        if ($session->user_id !== null) {
            $this->sendMenu($bot, $session, $chatId, 'Siz allaqachon tizimdasiz. Chiqish uchun /logout ni bosing.');

            return;
        }

        $contact = $message['contact'] ?? [];

        // XAVFSIZLIK: foydalanuvchi boshqa odamning kontaktini ham yubora oladi.
        // Telegram o'z raqami uchun contact.user_id ni to'ldiradi va u yuboruvchi
        // id'si bilan mos keladi — mos kelmasa, bu birovning raqami.
        $contactUserId = (string) ($contact['user_id'] ?? '');
        $senderId = (string) ($message['from']['id'] ?? '');

        if ($contactUserId === '' || $contactUserId !== $senderId) {
            $this->api->sendMessage($chatId,
                "❌ Iltimos, faqat <b>o'zingizning</b> raqamingizni pastdagi tugma orqali yuboring.",
                $this->contactKeyboard()
            );

            return;
        }

        $phone = (string) ($contact['phone_number'] ?? '');
        if ($phone === '') {
            $this->sendLoginPrompt($bot, $session, $chatId);

            return;
        }

        // Raqam AD dagi (employees.phone AD telephoneNumber dan to'ladi) raqam bilan
        // mos kelishi SHART — aks holda istalgan raqam bilan 2-qadamga o'tib bo'lardi.
        $employee = $this->findEmployeeByPhone($phone);

        if (! $employee) {
            Log::info('Bot kirish: kontakt raqami AD da topilmadi', [
                'chat_id' => $chatId,
                'phone_tail' => $this->phoneTail($phone),
            ]);

            // Bot orqali pochta ochish olib tashlangan — foydalanuvchi IT ga
            // murojaat qiladi. Kirish baribir pochta orqali boshlanadi.
            $this->setState($session, self::STATE_AWAIT_USERNAME, $this->sessionData($session));

            $this->api->sendMessage($chatId,
                "❌ Bu telefon raqam bo'yicha hisob topilmadi.\n\n".
                "Hisobingiz bo'lishi kerak bo'lsa — tizimdagi telefon raqamingiz noto'g'ri ".
                "bo'lishi mumkin, IT bo'limiga murojaat qiling.\n\n".
                '📧 Pochtangizni bilsangiz — shu yerga yozing.',
                ['remove_keyboard' => true]
            );

            return;
        }

        // Kontakt o'zi tizimga kiritmaydi — haqiqiy autentifikatsiya pochtaga
        // yuborilgan bir martalik SMS kod orqali bo'ladi (onSmsCode).
        $data = $this->sessionData($session);
        $data['phone'] = $phone;
        $data['employee_id'] = (int) $employee->id;
        unset($data['username']);
        $this->setState($session, self::STATE_AWAIT_USERNAME, $data);

        $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));

        $intro = $name !== ''
            ? '✅ Raqam tasdiqlandi: <b>'.htmlspecialchars($name)."</b>\n\n"
            : "✅ Raqamingiz tasdiqlandi.\n\n";

        $this->api->sendMessage($chatId,
            $intro.
            "2️⃣ Endi <b>pochtangizni</b> yozing.\n".
            '<i>Masalan: ism.familiya@xb.uz</i>',
            ['remove_keyboard' => true]
        );
    }

    /**
     * 1-qadamda yuborilgan telefon raqam AD orqali kirgan hisobga tegishlimi?
     *
     * AD login paytida AdUserProvisionService employees yozuvini AD dagi
     * telephoneNumber bilan yangilaydi, shuning uchun tekshiruv login'dan KEYIN
     * qilinadi — bazadagi raqam shu paytda AD dagi eng oxirgi qiymat bo'ladi.
     *
     * Ikki tekshiruv: xodim yozuvi bir xilmi va raqamning o'zi mos keladimi.
     * Ikkinchisi kerak, chunki AD da raqam login paytida o'zgargan bo'lishi mumkin.
     *
     * @param  array<string, mixed>  $data  sessiya ma'lumotlari (phone, employee_id)
     */
    private function phoneBelongsToUser(array $data, User $user): bool
    {
        $sessionPhone = $this->phoneTail($data['phone'] ?? null);

        // Kontakt qadami o'tkazib yuborilgan bo'lsa — bu holatga tushmasligi kerak.
        if ($sessionPhone === '') {
            return false;
        }

        if (empty($user->employee_id)) {
            return false;
        }

        // Xodim yozuvi 1-qadamdagi bilan bir xil bo'lishi kerak.
        if (! empty($data['employee_id']) && (int) $data['employee_id'] !== (int) $user->employee_id) {
            return false;
        }

        // Va AD dan yangilangan raqamning o'zi ham mos kelishi kerak.
        $adPhone = DB::table('employees')->where('id', $user->employee_id)->value('phone');

        return $this->phoneTail((string) $adPhone) === $sessionPhone;
    }

    /**
     * Telefon raqamning solishtirish uchun normallashtirilgan ko'rinishi:
     * faqat raqamlar, oxirgi 9 ta belgi (operator kodi + raqam).
     *
     * '+998 90 123 45 67', '998901234567' va '901234567' bir xil natija beradi.
     * 9 ta raqamdan qisqa bo'lsa (masalan ichki '10777') — o'zi qaytariladi va
     * to'liq mobil raqam bilan hech qachon mos kelmaydi.
     */
    private function phoneTail(?string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * Telefon bo'yicha xodimni topadi.
     *
     * Telegram raqamni '+' siz yuborishi mumkin, bazada esa ikkala ko'rinish ham
     * uchraydi — shuning uchun faqat raqamlar bo'yicha, oxirgi 9 ta belgi
     * (operator kodi + raqam) bilan solishtiramiz. Bu '+998 90 123 45 67',
     * '998901234567' va '901234567' variantlarini bir xil topadi.
     */
    // ── Ro'yxatdan o'tish (AD pochta ochish) ──────────────────────────────────
    //
    // Saytdagi /ad-account oqimining aynan o'zi, faqat bot ichida:
    //   PINFL → BXM kodi → telefon (SMS) → kod → pochta yaratish/parol tiklash.
    //
    // Barcha tekshiruvlar AdAccountController da qoladi — bu yerda mantiq
    // takrorlanmaydi, shunchaki so'rov yasab, javob foydalanuvchiga uzatiladi.

    private function escText(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    }

    private function digitsOnly(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    /**
     * AdAccountController metodini chaqiradi va javobni bir xil ko'rinishda qaytaradi.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    private function adAccount(string $action, array $payload): array
    {
        $controller = app(AdAccountController::class);
        $request = Request::create('/api/v1/ad-account/'.$action, 'POST', $payload);

        try {
            $response = match ($action) {
                'check-employee' => $controller->checkEmployee($request),
                'check-bxm' => $controller->checkBxm($request),
                'send-code' => $controller->sendCode($request),
                'verify-code' => $controller->verifyCode($request),
                'exchange' => $controller->createExchange($request),
                'reset-password' => $controller->resetPassword($request),
                'link-bxm' => $controller->linkBxm($request),
            };
        } catch (ValidationException $e) {
            return [
                'ok' => false,
                'message' => (string) (collect($e->errors())->flatten()->first() ?? "Ma'lumot noto'g'ri kiritildi."),
                'data' => [],
            ];
        } catch (\Throwable $e) {
            Log::error("Bot ro'yxatdan o'tish xatosi", ['action' => $action, 'error' => $e->getMessage()]);

            return [
                'ok' => false,
                'message' => "Xizmatda vaqtincha nosozlik. Birozdan so'ng qayta urinib ko'ring.",
                'data' => [],
            ];
        }

        $json = $response->getData(true);
        $status = $response->getStatusCode();

        return [
            'ok' => $status >= 200 && $status < 300,
            'message' => (string) ($json['message'] ?? ''),
            'data' => is_array($json) ? $json : [],
        ];
    }

    private function findEmployeeByPhone(string $phone): ?object
    {
        $tail = $this->phoneTail($phone);

        // To'liq mobil raqam bo'lishi shart. Qisqa (ichki) raqamlar bilan
        // solishtirilsa, bazadagi ichki raqamlarga tasodifan mos tushib qolardi.
        if (strlen($tail) < 9) {
            return null;
        }

        return DB::table('employees')
            ->leftJoin('users', 'users.employee_id', '=', 'employees.id')
            ->whereNull('employees.deleted_at')
            ->whereNotNull('employees.phone')
            ->whereRaw("RIGHT(REGEXP_REPLACE(employees.phone, '[^0-9]', ''), 9) = ?", [$tail])
            ->select('employees.id', 'employees.first_name', 'employees.last_name', 'employees.phone', 'users.id as user_id')
            ->first();
    }

    /**
     * @param  string  $source  qanday tasdiqlandi: LOGIN (login/parol) yoki CONTACT (telefon)
     */
    private function linkAccount(object $bot, object $session, User $user, string $chatId, string $source = 'LOGIN'): void
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
                'verification_source' => $source,
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
            'verification_source' => $source,
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

        // Bitta update ichida bir necha marta o'qilishi mumkin — xotiradagi nusxani
        // ham yangilaymiz, aks holda keyingi qadam eski holatni ko'radi.
        $session->state = $state;
        $session->data = json_encode($data);
    }

    private function resetSession(object $session): void
    {
        $this->setState($session, self::STATE_IDLE, []);
    }

    private function user(object $session): ?User
    {
        if (empty($session->user_id)) {
            return null;
        }

        return User::query()->find($session->user_id);
    }
}
