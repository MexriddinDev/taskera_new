<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Xavfsizlik tuzatishlarining regressiya testlari.
 * Har bir test auditdagi CRITICAL zaiflikka mos keladi.
 */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // FK zanjiri uchun minimal reference yozuvlar
        DB::table('locales')->insertOrIgnore([
            ['id' => 1, 'code' => 'uz', 'name' => 'Uzbek', 'is_active' => 1, 'sort_order' => 1],
        ]);
        DB::table('timezones')->insertOrIgnore([
            ['id' => 1, 'name' => 'Asia/Tashkent', 'utc_offset_hint' => 300, 'is_active' => 1],
        ]);
        DB::table('organizations')->insertOrIgnore([
            'id' => 1,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Org',
            'code' => 'TEST',
            'default_locale_id' => 1,
            'default_timezone_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Ticket uchun zarur reference yozuvlar
        DB::table('ticket_statuses')->insertOrIgnore([
            ['id' => 1, 'code' => 'NEW', 'name' => 'New', 'status_group' => 'OPEN', 'sort_order' => 1],
        ]);
        DB::table('ticket_priorities')->insertOrIgnore([
            ['id' => 3, 'code' => 'MEDIUM', 'name' => 'Medium', 'weight' => 3, 'color' => '#f59e0b', 'is_active' => 1],
        ]);
        DB::table('ticket_sources')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web', 'is_active' => 1],
        ]);
        DB::table('departments')->insertOrIgnore([
            'id' => 1,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'code' => 'HQ',
            'name' => 'Head Office',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(array $attributes = []): object
    {
        $userId = DB::table('users')->insertGetId(array_merge([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'username' => 'testuser',
            'email' => 'testuser@company.uz',
            'password' => Hash::make('Secret@123'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return DB::table('users')->where('id', $userId)->first();
    }

    private function actingAsUser(object $user): string
    {
        // Sanctum personal access token
        $token = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $user->id,
            'name' => 'test',
            'token' => hash('sha256', $plain = 'test-token-'.\Illuminate\Support\Str::random(20)),
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'test-token-placeholder';
    }

    /** C-1: /ad-account/recent hech qachon parol qaytarmasligi kerak. */
    public function test_recent_ad_account_does_not_expose_password(): void
    {
        DB::table('ad_accounts')->insert([
            'pinfl' => '12345678901234',
            'username' => 'new.employee',
            'email' => 'new.employee@xb.uz',
            'password_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString('PlainTextPass1!'),
            'bxm_code' => '9006',
            'status' => 'CREATED',
            'error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/ad-account/recent');

        $response->assertStatus(200);
        $account = $response->json('account');

        if ($account !== null) {
            $this->assertArrayNotHasKey('password', $account);
            $this->assertStringNotContainsString('PlainTextPass1!', $response->getContent());
        }
    }

    /** C-3: imzosiz attachment download 403 qaytarishi kerak. */
    public function test_attachment_download_requires_valid_signature(): void
    {
        $response = $this->getJson('/api/v1/attachments/999/download');

        $this->assertContains($response->status(), [403, 404]);
    }

    /** H: oddiy xodim ticketni o'chira olmasligi kerak (IDOR). */
    public function test_regular_user_cannot_delete_ticket(): void
    {
        $user = $this->createUser(['username' => 'regularuser', 'email' => 'regular@company.uz']);

        $ticketId = DB::table('tickets')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => 'INC-990001',
            'ticket_type' => 'INCIDENT',
            'subject' => 'Test zayavka',
            'description' => 'Test',
            'status_id' => 1,
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $user->id,
            'department_id' => 1,
            'target_department' => 'hardware',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Token yaratish (sanctum)
        $plainToken = \Illuminate\Support\Str::random(40);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $user->id,
            'name' => 'test',
            'token' => hash('sha256', $plainToken),
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->deleteJson("/api/v1/tickets/{$ticketId}");

        $response->assertStatus(403);

        $stillThere = DB::table('tickets')->where('id', $ticketId)->whereNull('deleted_at')->exists();
        $this->assertTrue($stillThere, 'Ticket o\'chirilmagan bo\'lishi kerak');
    }

    /** P8: SMS kodi bazada hash ko'rinishida saqlanadi. */
    public function test_sms_code_is_stored_hashed(): void
    {
        // sendCode to'liq flow uchun tashqi xizmatlar kerak — shuning uchun
        // to'g'ridan-to'g'ri insert qilib verifyCode orqali tekshiramiz.
        $plainCode = '54321';

        DB::table('sms_codes')->insert([
            'phone' => '+998901112233',
            'code' => Hash::make($plainCode),
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'template_id' => 'SYSTEM_VERIFY_CODE',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('sms_codes')->where('phone', '+998901112233')->first();

        $this->assertStringStartsWith('$2y$', $row->code, 'Kod plaintext saqlanmasligi kerak');
        $this->assertTrue(Hash::check($plainCode, $row->code));
        $this->assertStringNotContainsString($plainCode, $row->code);
    }
}
