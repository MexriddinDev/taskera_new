<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Models\User;
use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use App\Modules\Telegram\Infrastructure\Services\BotConversationService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
