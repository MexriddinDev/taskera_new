<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Models\User;
use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use App\Modules\Telegram\Infrastructure\Services\BotConversationService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Telegram botidan zayavka yaratish.
 *
 * Bu test regressiya qo'riqchisi sifatida yozilgan: 2026-09-10 da
 * `TicketController::store()` oddiy `Request` o'rniga `StoreTicketRequest`
 * qabul qiladigan qilindi (bb410869), lekin `BotConversationService` dagi
 * chaqiruv yangilanmadi. Natijada botdan zayavka yaratmoqchi bo'lgan har bir
 * foydalanuvchi TypeError sababli "zayavka yaratishda xatolik" xabarini olardi
 * va bu to'rt kun davomida sezilmadi — bot yo'lini qamraydigan test yo'q edi.
 */
final class BotCreateTicketTest extends TestCase
{
    use RefreshDatabase;

    private object $bot;

    private User $user;

    private int $teamId;

    /** Botdan Telegramga yuborilgan xabarlar — tarmoqqa chiqilmaydi. */
    private FakeTelegramApiClient $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Bot Test', 'code' => 'BOT',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // `TicketController::store()` da xodim topilmasa `department_id` uchun
        // qattiq kodlangan zaxira qiymat ishlatiladi: `$employee?->department_id ?? 1`.
        // Haqiqiy bazada 1-departament bor, testda esa uni o'zimiz yaratamiz —
        // aks holda FOREIGN KEY cheklovi buziladi.
        DB::table('departments')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'IT', 'name' => 'IT departamenti', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->teamId = DB::table('teams')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'code' => 'IT', 'name' => 'IT guruhi',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => 'bot.user',
            'email' => 'bot.user@example.com', 'password' => bcrypt('Secret123!'), 'auth_source' => 'LOCAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::findOrFail($userId);

        $botId = DB::table('telegram_bots')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'name' => 'Test bot', 'username' => 'testbot', 'token_secret_ref' => 'test-token',
            'webhook_secret_hash' => hash('sha256', 'test-secret'),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->bot = DB::table('telegram_bots')->where('id', $botId)->first();

        // Tarmoqqa chiqmaydigan soxta mijoz.
        $this->api = new FakeTelegramApiClient;
        $this->app->instance(TelegramApiClient::class, $this->api);
    }

    public function test_bot_creates_a_ticket_when_the_user_confirms(): void
    {
        $this->seedConfirmableSession();

        $this->handleConfirm();

        $ticket = Ticket::query()->where('requester_user_id', $this->user->id)->first();

        $this->assertNotNull($ticket, 'Botdan tasdiqlangandan keyin zayavka yaratilishi kerak edi.');
        $this->assertSame('Printer ishlamayapti', $ticket->subject);
        $this->assertSame($this->teamId, (int) $ticket->assigned_team_id);

        // Foydalanuvchi xato emas, zayavka raqamini ko'rishi kerak.
        $this->assertStringNotContainsStringIgnoringCase('xatolik', $this->api->allText());
        $this->assertStringContainsString((string) $ticket->ticket_no, $this->api->allText());
    }

    /**
     * Validatsiya ishlayotganini alohida tasdiqlaydi.
     *
     * `store()` ichida `$request->validated()` chaqiriladi. Agar FormRequest
     * qo'lda validatsiyadan o'tkazilmasa, `validated()` bo'sh massiv qaytaradi
     * va zayavka mavzusiz yaratilib qolardi — TypeError yo'qolgani bilan xato
     * jimroq shaklda saqlanib qolardi.
     */
    public function test_ticket_body_survives_validation(): void
    {
        $this->seedConfirmableSession('Monitor yonmayapti, tekshirib bering');

        $this->handleConfirm();

        $ticket = Ticket::query()->where('requester_user_id', $this->user->id)->firstOrFail();

        $this->assertSame('Monitor yonmayapti, tekshirib bering', $ticket->subject);
        $this->assertNotEmpty($ticket->description);
    }

    /**
     * Murojaatchi yechimni rad etsa — zayavka "Radd etildi" (9) bo'ladi.
     *
     * Ilgari bot bu yerda 2 (Ochiq) yozardi: foydalanuvchi rad etgan zayavka
     * ro'yxatda yana ochiq bo'lib turardi. Sayt esa ayni amalda 9 ni qo'yadi
     * (`TicketController::update`, status = 'rejected'), ya'ni ikki kanal
     * bir-biriga zid edi.
     */
    public function test_requester_rejection_marks_the_ticket_as_rejected(): void
    {
        $this->seedLoggedInSession();
        $ticket = $this->resolvedTicket();

        $this->startReturnFlow((int) $ticket->id);
        $this->sendText('Muammo hal bolmadi, printer hamon ishlamayapti');

        $ticket->refresh();

        $this->assertSame(9, (int) $ticket->status_id, 'Rad etilgan zayavka 9-holatda bolishi kerak.');
        $this->assertSame('Muammo hal bolmadi, printer hamon ishlamayapti', $ticket->rejection_reason);

        // Tarixda ham 9 turishi kerak — hisobotlar shu jadvalni o'qiydi.
        $this->assertDatabaseHas('ticket_status_history', [
            'ticket_id' => $ticket->id,
            'to_status_id' => 9,
            'action' => 'RETURNED_BY_REQUESTER',
        ]);
    }

    /**
     * Baholangan zayavkani qaytarib yoki rad etib bo'lmaydi.
     */
    public function test_rated_ticket_cannot_be_returned_or_rejected(): void
    {
        $this->seedLoggedInSession();
        $ticket = $this->resolvedTicket();
        $ticket->update(['client_rating' => 4]);

        // 1. Qaytarish oqimini boshlashga urinish
        $this->startReturnFlow((int) $ticket->id);

        $this->assertStringContainsString('allaqachon baholangan', $this->api->allText());
        $this->assertNotSame('AWAIT_TICKET_RETURN_REASON', $this->currentState());

        // 2. Agar sessiya majburiy AWAIT_TICKET_RETURN_REASON da turgan bo'lsa ham:
        DB::table('telegram_chat_sessions')->where('chat_id', '555')->update([
            'state' => 'AWAIT_TICKET_RETURN_REASON',
            'data' => json_encode(['return_ticket_id' => $ticket->id]),
        ]);

        $this->sendText('Rad etish sababi');

        $ticket->refresh();
        $this->assertSame(7, (int) $ticket->status_id, "Baholangan zayavka holati o'zgarmasligi kerak.");
        $this->assertNull($ticket->rejection_reason);
    }

    /**
     * Support xodimi bot orqali zayavka YARATA olmaydi.
     *
     * Ilgari "Yangi zayavka" tugmasi hammaga ko'rinardi va support/admin/
     * superadmin o'z nomidan murojaat ochib yuborardi.
     */
    public function test_support_staff_cannot_create_a_ticket_from_the_bot(): void
    {
        $this->grantPermission($this->user, 'tickets.view');
        $this->seedSession('IDLE', []);

        $this->pressButton('menu:new_ticket');

        $this->assertSame(0, Ticket::query()->count(), 'Support uchun zayavka yaratilmasligi kerak.');
        $this->assertStringContainsString('zayavka yarata olmaydi', $this->api->allText());
    }

    /** Oddiy foydalanuvchida oqim odatdagidek boshlanadi. */
    public function test_a_regular_user_can_still_start_the_ticket_flow(): void
    {
        $this->seedSession('IDLE', []);

        $this->pressButton('menu:new_ticket');

        $this->assertStringNotContainsString('zayavka yarata olmaydi', $this->api->allText());
    }

    /**
     * Uzun matn support ko'radigan kartochkada TO'LIQ chiqadi.
     *
     * Ilgari `Str::limit(subject, 200)` turardi: shablon bilan yuborilgan uzun
     * murojaat support tomonda qirqilib, uzuk-uzuk bo'lib ko'rinardi.
     */
    public function test_a_long_ticket_text_is_shown_in_full_to_support(): void
    {
        $long = str_repeat('Printer ishlamayapti va qogoz tiqilib qoldi. ', 20);
        $this->assertGreaterThan(400, mb_strlen($long));

        $ticket = Ticket::create([
            'organization_id' => 1,
            'ticket_no' => 'INC-'.Str::random(6),
            'ticket_type' => 'INCIDENT',
            'subject' => $long,
            'description' => $long,
            'status_id' => 4,
            'priority_id' => 3,
            'source_id' => 2,
            'requester_user_id' => $this->user->id,
            'assigned_user_id' => $this->user->id,
            'assigned_team_id' => $this->teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedSession('IDLE', []);
        $this->pressButton('ticket:open:'.$ticket->id);

        $sent = $this->api->allText();
        // Oxirgi bo'lak ham yetib kelishi kerak.
        $this->assertStringContainsString(rtrim($long), $sent);
    }

    /** Foydalanuvchiga huquq beradi. */
    private function grantPermission(User $user, string $permission): void
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => $permissionId, 'model_type' => User::class, 'model_id' => $user->id,
        ]);
        Cache::forget('user_permissions_'.$user->id);
    }

    /** Tasdiqlashga tayyor sessiya yozadi. */
    private function seedConfirmableSession(string $text = 'Printer ishlamayapti'): void
    {
        DB::table('telegram_chat_sessions')->insert([
            'organization_id' => 1,
            'bot_id' => $this->bot->id,
            'chat_id' => '555',
            'telegram_user_id' => '777',
            'user_id' => $this->user->id,
            'state' => 'TICKET_CONFIRM',
            'data' => json_encode([
                'ticket_text' => $text,
                'ticket_team_id' => $this->teamId,
                'ticket_team_name' => 'IT guruhi',
            ]),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Matndan keyin rasm, keyin ovoz ketma-ket so'raladi.
     *
     * Ilgari matn kiritilishi bilan darrov tasdiqlashga o'tilardi va rasm/ovoz
     * umuman so'ralmasdi — foydalanuvchi o'zi yuborishni bilsagina qo'shilardi.
     */
    public function test_bot_asks_for_photo_then_audio_after_the_text(): void
    {
        $this->seedSession('AWAIT_TICKET_TEXT', [
            'ticket_team_id' => $this->teamId,
            'ticket_team_name' => 'IT guruhi',
        ]);

        $this->sendText('Printer ishlamayapti');
        $this->assertSame('AWAIT_TICKET_PHOTO', $this->currentState(), 'Matndan keyin rasm soralishi kerak.');
        $this->assertStringContainsString('Rasm', $this->api->allText());

        $this->pressButton('ticket:skip_photo');
        $this->assertSame('AWAIT_TICKET_AUDIO', $this->currentState(), 'Rasmdan keyin ovoz soralishi kerak.');
        $this->assertStringContainsString('Ovozli xabar', $this->api->allText());

        $this->pressButton('ticket:skip_audio');
        $this->assertSame('AWAIT_TICKET_CONFIRM', $this->currentState(), 'Ovozdan keyin tasdiqlash bosqichi.');
    }

    /** Sessiyaning hozirgi holati. */
    private function currentState(): string
    {
        return (string) DB::table('telegram_chat_sessions')->where('chat_id', '555')->value('state');
    }

    /** Inline tugma bosilishi. */
    private function pressButton(string $callbackData): void
    {
        app(BotConversationService::class)->handle(
            $this->bot, '555', '777', 'Test', null,
            ['id' => 'cb-x', 'data' => $callbackData],
            null,
        );
    }

    /** Ixtiyoriy holat va ma'lumot bilan sessiya. */
    private function seedSession(string $state, array $data): void
    {
        DB::table('telegram_chat_sessions')->insert([
            'organization_id' => 1,
            'bot_id' => $this->bot->id,
            'chat_id' => '555',
            'telegram_user_id' => '777',
            'user_id' => $this->user->id,
            'state' => $state,
            'data' => json_encode($data),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Kirgan, lekin hech qanday oqimda turmagan sessiya. */
    private function seedLoggedInSession(): void
    {
        DB::table('telegram_chat_sessions')->insert([
            'organization_id' => 1,
            'bot_id' => $this->bot->id,
            'chat_id' => '555',
            'telegram_user_id' => '777',
            'user_id' => $this->user->id,
            'state' => 'IDLE',
            'data' => json_encode([]),
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Hal qilingan (7) zayavka — murojaatchi uni qaytara oladi. */
    private function resolvedTicket(): Ticket
    {
        return Ticket::create([
            'organization_id' => 1,
            'ticket_no' => 'INC-'.Str::random(6),
            'ticket_type' => 'INCIDENT',
            'subject' => 'Printer ishlamayapti',
            'description' => 'Printer ishlamayapti',
            'status_id' => 7,
            'priority_id' => 3,
            'source_id' => 2,
            'requester_user_id' => $this->user->id,
            'assigned_team_id' => $this->teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** "Qaytarish" tugmasi bosilishi — bot sabab so'raydigan holatga o'tadi. */
    private function startReturnFlow(int $ticketId): void
    {
        app(BotConversationService::class)->handle(
            $this->bot, '555', '777', 'Test', null,
            ['id' => 'cb-1', 'data' => 'ticket:return:'.$ticketId],
            null,
        );
    }

    /** Sabab matnini yuborish. */
    private function sendText(string $text): void
    {
        app(BotConversationService::class)->handle(
            $this->bot, '555', '777', 'Test', $text, null, null,
        );
    }

    /** "Tasdiqlash" tugmasi bosilishini taqlid qiladi. */
    private function handleConfirm(): void
    {
        app(BotConversationService::class)->handle(
            $this->bot,
            '555',
            '777',
            'Test',
            null,
            ['id' => 'cb-1', 'data' => 'confirm'],
            null,
        );
    }
}

/** Tarmoqqa chiqmaydigan TelegramApiClient o'rnini bosuvchi. */
final class FakeTelegramApiClient extends TelegramApiClient
{
    /** @var list<string> */
    public array $sent = [];

    public function __construct()
    {
        parent::__construct('fake-token');
    }

    public function sendMessage(
        string $chatId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): array {
        $this->sent[] = $text;

        return ['ok' => true, 'result' => ['message_id' => count($this->sent)]];
    }

    public function editMessageText(
        string $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): array {
        $this->sent[] = $text;

        return ['ok' => true, 'result' => ['message_id' => $messageId]];
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        return ['ok' => true];
    }

    public function allText(): string
    {
        return implode("\n", $this->sent);
    }
}
