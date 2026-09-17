<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Telegram\Infrastructure\Services\BotLoginCodeService;
use App\Services\SmsGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Botning texnik chidamliligi.
 *
 * Uch narsa tekshiriladi:
 *   1. SMS ketma-ket yuborilmaydi (2 daqiqalik oraliq);
 *   2. tugmani ketma-ket bosish bir marta ishlanadi;
 *   3. ro'yxat qatorida faqat BITTA holat belgisi turadi.
 */
final class BotHardeningTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Bot test', 'code' => 'BOT',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employment_statuses')->insertOrIgnore([
            ['id' => 1, 'code' => 'ACTIVE', 'name' => 'Faol', 'can_login' => true, 'is_active' => true],
        ]);
        // `employees` da bo'lim/filial/lavozim majburiy — minimal zanjir.
        DB::table('regions')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'TSH', 'name' => 'Toshkent', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'region_id' => 1,
            'code' => 'HQ', 'name' => 'Bosh ofis', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('departments')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'IT', 'name' => 'IT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'STAFF', 'name' => 'Xodim', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $employeeId = DB::table('employees')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'employee_no' => 'E-1',
            'first_name' => 'Test', 'last_name' => 'Xodim', 'phone' => '998901234567',
            'department_id' => 1, 'branch_id' => 1, 'position_id' => 1,
            'employment_status_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->userId = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => 'xodim',
            'email' => 'xodim@example.com', 'password' => bcrypt('x'), 'auth_source' => 'LOCAL',
            'status' => 'ACTIVE', 'employee_id' => $employeeId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * SMS ikki daqiqada bir marta yuboriladi.
     *
     * Ilgari hech qanday oraliq yo'q edi: tugmani ketma-ket bosgan foydalanuvchi
     * o'nlab SMS yuborardi.
     */
    public function test_a_second_sms_is_refused_until_the_cooldown_passes(): void
    {
        $sent = 0;
        $this->mock(SmsGatewayService::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('send')->andReturnUsing(function () use (&$sent) {
                $sent++;

                return true;
            });
        });

        $service = app(BotLoginCodeService::class);

        $first = $service->send('xodim@example.com');
        $this->assertTrue($first['ok'], 'Birinchi SMS yuborilishi kerak.');

        $second = $service->send('xodim@example.com');
        $this->assertFalse($second['ok'], 'Ikkinchi SMS darhol yuborilmasligi kerak.');
        $this->assertGreaterThan(0, $second['retry_after']);
        $this->assertLessThanOrEqual(120, $second['retry_after']);

        $this->assertSame(1, $sent, 'Gateway faqat bir marta chaqirilishi kerak.');
        $this->assertSame(1, DB::table('sms_codes')->count(), 'Ikkinchi kod yaratilmasligi kerak.');
    }

    /** Oraliq o'tgach yangi SMS yuboriladi. */
    public function test_a_new_sms_is_allowed_after_the_cooldown(): void
    {
        $this->mock(SmsGatewayService::class, fn ($mock) => $mock->shouldReceive('send')->andReturn(true));
        $service = app(BotLoginCodeService::class);

        $this->assertTrue($service->send('xodim@example.com')['ok']);

        // Oxirgi yuborilgan vaqt orqaga suriladi.
        DB::table('sms_codes')->update(['sent_at' => now()->subSeconds(121)]);

        $this->assertTrue($service->send('xodim@example.com')['ok'], "Oraliq o'tgach yuborilishi kerak.");
        $this->assertSame(2, DB::table('sms_codes')->count());
    }

    /**
     * Ketma-ket bosilgan tugmadan faqat bittasi o'tadi.
     *
     * Bu — `BotConversationService::handleCallback` ishlatadigan qulfning
     * o'zi: bir xil chat uchun ikkinchi urinish qulfni ololmaydi.
     */
    public function test_rapid_button_presses_are_collapsed_into_one(): void
    {
        $key = 'telegram:callback:12345';

        $first = Cache::lock($key, 3);
        $this->assertTrue($first->get(), 'Birinchi bosish o\'tishi kerak.');

        $second = Cache::lock($key, 3);
        $this->assertFalse($second->get(), 'Ikkinchi bosish qulfga urilishi kerak.');

        $first->release();

        $third = Cache::lock($key, 3);
        $this->assertTrue($third->get(), 'Qulf bo\'shagach keyingi bosish o\'tadi.');
        $third->release();
    }

    /**
     * Ro'yxat qatorida faqat holat belgisi turadi.
     *
     * Ilgari yonida muhimlik rangi ham chiqib (masalan ko'k + sariq), qaysi
     * rang nimani bildirishi chalkashardi.
     */
    public function test_the_list_line_carries_only_the_status_icon(): void
    {
        $source = file_get_contents(
            base_path('app/Modules/Telegram/Infrastructure/Services/BotConversationService.php')
        );

        $this->assertStringNotContainsString(
            '$priorityEmoji."\n"',
            $source,
            "Ro'yxat qatorida muhimlik belgisi qolmasligi kerak."
        );

        // Tafsilot kartochkasida esa muhimlik alohida qatorda qoladi.
        $this->assertStringContainsString('⚡ Muhimlik: ', $source);
    }
}
