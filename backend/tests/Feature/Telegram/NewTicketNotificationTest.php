<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Telegram\Infrastructure\Listeners\SyncTelegramThreadListener;
use App\Modules\Telegram\Infrastructure\Services\TelegramNotifierService;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Yangi zayavka haqida support xodimlariga boradigan xabar shabloni.
 */
final class NewTicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    private RecordingNotifier $notifier;

    private int $teamId;

    private int $requesterId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Tpl Test', 'code' => 'TPL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->teamId = DB::table('teams')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'code' => 'IT', 'name' => 'IT guruhi',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->requesterId = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => 'tpl.user',
            'email' => 'tpl.user@example.com', 'password' => bcrypt('Secret123!'), 'auth_source' => 'LOCAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->notifier = new RecordingNotifier;
    }

    public function test_notification_uses_the_requested_template(): void
    {
        config(['app.frontend_url' => 'https://taskera.xb.uz']);

        $ticket = $this->ticket(['initiator_phone' => '915191700']);

        (new SyncTelegramThreadListener($this->notifier))->handle(new TicketCreated($ticket));

        $text = $this->notifier->staffText;

        $this->assertNotNull($text, 'Support xodimlariga xabar yuborilishi kerak edi.');
        $this->assertStringContainsString('ID: <b>'.$ticket->ticket_no.'</b>', $text);
        $this->assertStringContainsString('Guruh: IT guruhi', $text);
        // Havola aynan shu zayavkaga ishora qilsin, umumiy ro'yxatga emas.
        $this->assertStringContainsString('URL: https://taskera.xb.uz/task/'.$ticket->id, $text);
        $this->assertStringContainsString('Vaqt: '.$ticket->created_at->copy()->timezone('Asia/Tashkent')->format('Y.m.d H:i:s'), $text);
        $this->assertStringContainsString('Xodim telefon raqami: 915191700', $text);
        $this->assertStringContainsString('Holat: ', $text);
        $this->assertStringContainsString('Muammo: Printer ishlamayapti', $text);
    }

    /**
     * Ma'lumoti yo'q qatorlar umuman tushmasligi kerak.
     *
     * BXM kodi `ad_accounts` da saqlanadi va ko'p zayavkada bo'lmaydi — bo'sh
     * "BXM ID: -" qatori xabarni uzaytirib, o'qishni qiyinlashtirardi.
     */
    public function test_missing_phone_and_bxm_rows_are_omitted(): void
    {
        $ticket = $this->ticket();

        (new SyncTelegramThreadListener($this->notifier))->handle(new TicketCreated($ticket));

        $text = (string) $this->notifier->staffText;

        $this->assertStringNotContainsString('BXM ID', $text);
        $this->assertStringNotContainsString('Xodim telefon raqami', $text);
        // Qolgan majburiy qatorlar esa joyida.
        $this->assertStringContainsString('Guruh: IT guruhi', $text);
        $this->assertStringContainsString('Muammo: ', $text);
    }

    private function ticket(array $values = []): Ticket
    {
        return Ticket::create(array_merge([
            'organization_id' => 1,
            'ticket_no' => 'INC-'.Str::random(6),
            'ticket_type' => 'INCIDENT',
            'subject' => 'Printer ishlamayapti',
            'description' => 'Printer ishlamayapti',
            'status_id' => 1,
            'priority_id' => 3,
            'source_id' => 2,
            'assigned_team_id' => $this->teamId,
            'requester_user_id' => $this->requesterId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $values));
    }
}

/** Yuborilgan xabarni yozib oluvchi — tarmoqqa chiqmaydi. */
final class RecordingNotifier extends TelegramNotifierService
{
    public ?string $staffText = null;

    public function __construct() {}

    public function sendToStaff(int $organizationId, string $text, ?int $excludeUserId = null, ?object $ticket = null): void
    {
        $this->staffText = $text;
    }
}
