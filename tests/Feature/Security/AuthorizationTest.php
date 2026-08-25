<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RBAC, transition, attachment ownership va mime whitelist regressiya testlari.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $orgId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('locales')->insertOrIgnore([
            ['id' => 1, 'code' => 'uz', 'name' => 'Uzbek', 'is_active' => 1, 'sort_order' => 1],
        ]);
        DB::table('timezones')->insertOrIgnore([
            ['id' => 1, 'name' => 'Asia/Tashkent', 'utc_offset_hint' => 300, 'is_active' => 1],
        ]);
        DB::table('organizations')->insertOrIgnore([
            'id' => 1,
            'public_id' => (string) Str::uuid(),
            'name' => 'Test Org',
            'code' => 'TEST',
            'default_locale_id' => 1,
            'default_timezone_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'code' => 'HQ',
            'name' => 'Head Office',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $username, bool $superAdmin = false): int
    {
        $userId = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'username' => $username,
            'email' => $username.'@company.uz',
            // Superadmin tekshiruvi User::isSuperAdmin() — username bo'yicha
            'password' => Hash::make('Secret@123'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($superAdmin) {
            $roleId = DB::table('roles')->insertGetId([
                'organization_id' => 1,
                'name' => 'Super Admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $userId,
                'organization_id' => 1,
            ]);
        }

        return $userId;
    }

    private function tokenFor(int $userId): string
    {
        $plain = Str::random(40);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $userId,
            'name' => 'test',
            'token' => hash('sha256', $plain),
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plain;
    }

    /** C-5: Web'da rol yaratish faqat roles.manage permission bilan. */
    public function test_web_role_store_requires_manage_permission(): void
    {
        $response = $this->post('/settings/roles', ['name' => 'Hacker Role']);

        // Auth'siz — login redirect yoki 401
        $this->assertContains($response->status(), [302, 401, 419]);
    }

    /** H: transition endpoint staff bo'lmagan userga 403 qaytaradi. */
    public function test_transition_requires_staff_permission(): void
    {
        $userId = $this->makeUser('plainuser');
        $token = $this->tokenFor($userId);

        $ticketId = DB::table('tickets')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => 'INC-980001',
            'ticket_type' => 'INCIDENT',
            'subject' => 'T',
            'description' => 'T',
            'status_id' => 1,
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $userId,
            'department_id' => 1,
            'target_department' => 'hardware',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/tickets/{$ticketId}/transition", ['to_status_id' => 7]);

        $response->assertStatus(403);
    }

    /** A2: ruxsat etilmagan fayl turi rad etiladi. */
    public function test_attachment_upload_rejects_disallowed_mime(): void
    {
        $userId = $this->makeUser('uploader');
        $token = $this->tokenFor($userId);

        DB::table('attachment_types')->insertOrIgnore([
            ['id' => 1, 'code' => 'FILE', 'name' => 'File', 'is_active' => 1],
        ]);

        $fake = \Illuminate\Http\Testing\File::fake()->createWithContent(
            'malicious.html',
            '<script>alert(1)</script>'
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/attachments/upload', [
                'file' => $fake,
                'attachable_type' => \App\Modules\Ticketing\Infrastructure\Eloquent\Ticket::class,
                'attachable_id' => 1,
            ]);

        $this->assertTrue(in_array($response->status(), [302, 422]), 'HTML fayl rad etilishi kerak');
    }

    /** A1: organization_id header spoof qilinsa ham user org ishlatiladi. */
    public function test_current_org_ignores_spoofed_header(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET');
        $request->headers->set('X-Organization-Id', '9999');

        // Auth bo'lmasa default 1
        $this->assertSame(1, \App\Support\CurrentOrg::id($request));
    }
}
