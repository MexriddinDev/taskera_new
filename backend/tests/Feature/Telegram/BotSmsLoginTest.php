<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Models\User;
use App\Modules\Telegram\Infrastructure\Services\BotLoginCodeService;
use App\Services\SmsGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Botga pochta + bir martalik SMS kod bilan kirish.
 *
 * Asosiy xavfsizlik shartI: kod FAQAT tizimda o'sha pochtaga biriktirilgan
 * xodim raqamiga ketadi. Aks holda begona pochtani yozib, kodni o'z telefoniga
 * olib, boshqa odam nomidan kirish mumkin bo'lardi.
 */
final class BotSmsLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $phone = '998915092777';

    private FakeSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'SMS test', 'code' => 'SMS',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('departments')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'IT', 'name' => 'IT', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // `employees` bir nechta majburiy FK ga bog'langan — minimal zanjir.
        DB::table('regions')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'TSH', 'name' => 'Toshkent', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'region_id' => 1,
            'code' => 'HQ', 'name' => 'Bosh ofis', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'ENG', 'name' => 'Muhandis', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employment_statuses')->insert([
            'id' => 1, 'code' => 'ACTIVE', 'name' => 'Faol', 'can_login' => true, 'is_active' => true,
        ]);

        $employeeId = DB::table('employees')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'department_id' => 1,
            'employee_no' => 'E-001', 'branch_id' => 1, 'position_id' => 1, 'employment_status_id' => 1,
            'first_name' => 'Xusniddin', 'last_name' => 'Amanov', 'phone' => $this->phone,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => 'xusniddin.amanov',
            'email' => 'xusniddin.amanov@xb.uz', 'password' => bcrypt('Secret123!'), 'auth_source' => 'LOCAL',
            'employee_id' => $employeeId, 'status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::findOrFail($id);

        $this->sms = new FakeSmsGateway;
        $this->app->instance(SmsGatewayService::class, $this->sms);
    }

    public function test_the_code_goes_to_the_phone_registered_for_that_email(): void
    {
        $result = app(BotLoginCodeService::class)->send('xusniddin.amanov@xb.uz');

        $this->assertTrue($result['ok']);
        $this->assertSame($this->phone, $this->sms->phone, 'SMS xodim kartochkasidagi raqamga ketishi kerak.');
        // Xabarda to'liq raqam ko'rsatilmaydi.
        $this->assertSame('****2777', $result['phone']);

        // Kod bazada ochiq saqlanmaydi.
        $stored = DB::table('sms_codes')->latest('id')->first();
        $this->assertNotSame($this->sms->code, $stored->code);
        $this->assertTrue(Hash::check($this->sms->code, $stored->code));
    }

    public function test_a_correct_code_logs_the_user_in(): void
    {
        app(BotLoginCodeService::class)->send('xusniddin.amanov@xb.uz');

        $result = app(BotLoginCodeService::class)->verify($this->user->id, $this->sms->code);

        $this->assertTrue($result['ok']);
        $this->assertSame($this->user->id, $result['user']->id);
    }

    public function test_a_code_can_be_used_only_once(): void
    {
        app(BotLoginCodeService::class)->send('xusniddin.amanov@xb.uz');
        $code = $this->sms->code;

        $this->assertTrue(app(BotLoginCodeService::class)->verify($this->user->id, $code)['ok']);
        // Ikkinchi marta ishlamasligi kerak.
        $this->assertFalse(app(BotLoginCodeService::class)->verify($this->user->id, $code)['ok']);
    }

    public function test_a_wrong_code_is_rejected_and_counted(): void
    {
        app(BotLoginCodeService::class)->send('xusniddin.amanov@xb.uz');

        $this->assertFalse(app(BotLoginCodeService::class)->verify($this->user->id, '00000')['ok']);

        $this->assertSame(1, (int) DB::table('sms_codes')->latest('id')->value('attempts'));
    }

    public function test_an_unknown_email_never_sends_an_sms(): void
    {
        $result = app(BotLoginCodeService::class)->send('boshqa.odam@xb.uz');

        $this->assertFalse($result['ok']);
        $this->assertNull($this->sms->phone, 'Noma\'lum pochta uchun SMS umuman ketmasligi kerak.');
        $this->assertSame(0, DB::table('sms_codes')->count());
    }

    public function test_a_user_without_a_phone_gets_no_code(): void
    {
        DB::table('users')->where('id', $this->user->id)->update(['employee_id' => null]);

        $result = app(BotLoginCodeService::class)->send('xusniddin.amanov@xb.uz');

        $this->assertFalse($result['ok']);
        $this->assertNull($this->sms->phone);
    }
}

/** Tarmoqqa chiqmaydigan SMS gateway — yuborilgan kodni saqlaydi. */
final class FakeSmsGateway extends SmsGatewayService
{
    public ?string $phone = null;

    public ?string $code = null;

    public function __construct() {}

    public function send(string $phone, string $code, string $requestId): bool
    {
        $this->phone = $phone;
        $this->code = $code;

        return true;
    }
}
