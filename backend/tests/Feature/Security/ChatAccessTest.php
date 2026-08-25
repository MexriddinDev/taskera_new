<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chat IDOR himoyasi: foydalanuvchi faqat o'zi participant bo'lgan
 * suhbatlarga kirishi mumkin.
 */
class ChatAccessTest extends TestCase
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
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Org',
            'code' => 'TEST',
            'default_locale_id' => 1,
            'default_timezone_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $username): int
    {
        return DB::table('users')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'username' => $username,
            'email' => $username.'@company.uz',
            'password' => bcrypt('Secret@123'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tokenFor(int $userId): string
    {
        $plain = \Illuminate\Support\Str::random(40);
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

    public function test_non_participant_cannot_read_conversation(): void
    {
        $ownerId = $this->makeUser('owner1');
        $strangerId = $this->makeUser('stranger1');

        $convId = DB::table('chat_conversations')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'type' => 'DIRECT',
            'created_by' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('chat_participants')->insert([
            'conversation_id' => $convId,
            'user_id' => $ownerId,
            'role' => 'OWNER',
            'joined_at' => now(),
        ]);

        $token = $this->tokenFor($strangerId);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/chat/conversations/{$convId}")
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/chat/conversations/{$convId}/messages")
            ->assertStatus(403);
    }
}
