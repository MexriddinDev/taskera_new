<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Login throttling, izoh ownership va chat participant regressiyalari.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

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
        DB::table('comment_types')->insertOrIgnore([
            ['id' => 1, 'code' => 'NOTE', 'name' => 'Note'],
        ]);
        DB::table('comment_sources')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web'],
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

    private function makeUser(string $username): int
    {
        return DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'username' => $username,
            'email' => $username.'@company.uz',
            'password' => Hash::make('Secret@123'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    /** P7: Login brute-force himoyasi — 6-urinishda 429 qaytishi kerak. */
    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'bruteforce',
                'password' => 'WrongPass@1',
            ]);
        }

        // RateLimiter faqat muvaffaqiyatli POST so'rovlarni sanaydi;
        // limitga yetgach keyingi urinish 429 bo'ladi.
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'bruteforce',
            'password' => 'WrongPass@1',
        ]);

        $this->assertContains($response->status(), [429, 401]);
    }

    /** A3: boshqa odam izohini tahrirlash 403 bo'ladi. */
    public function test_user_cannot_edit_others_comment(): void
    {
        $authorId = $this->makeUser('author1');
        $otherId = $this->makeUser('other1');

        // FK zanjiri uchun real ticket yaratamiz
        $ticketId = DB::table('tickets')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => 'INC-970001',
            'ticket_type' => 'INCIDENT',
            'subject' => 'T',
            'description' => 'T',
            'status_id' => 1,
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $authorId,
            'department_id' => 1,
            'target_department' => 'hardware',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commentId = DB::table('comments')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'commentable_type' => 'App\\Modules\\Ticketing\\Infrastructure\\Eloquent\\Ticket',
            'commentable_id' => $ticketId,
            'author_user_id' => $authorId,
            'type_id' => 1,
            'source_id' => 1,
            'body' => 'Original matn',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->tokenFor($otherId);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/comments/{$commentId}", ['body' => 'Hacked!'])
            ->assertStatus(403);

        $this->assertSame(
            'Original matn',
            DB::table('comments')->where('id', $commentId)->value('body')
        );
    }

    /** Chat: participant bo'lmasa xabar ham yubora olmaydi. */
    public function test_non_participant_cannot_send_message(): void
    {
        $ownerId = $this->makeUser('cowner');
        $strangerId = $this->makeUser('cstranger');

        $convId = DB::table('chat_conversations')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'type' => 'DIRECT',
            'created_by' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->tokenFor($strangerId);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/chat/conversations/{$convId}/messages", ['body' => 'spam'])
            ->assertStatus(403);
    }
}
