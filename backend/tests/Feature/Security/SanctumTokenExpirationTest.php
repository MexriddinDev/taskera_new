<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * API tokenining amal qilish muddati [H-10].
 *
 * `sanctum.expiration = null` bo'lganda o'g'irlangan token qo'lda bekor
 * qilinmaguncha abadiy ishlaydi. Frontend tokenni `localStorage` da saqlagani
 * uchun XSS ta'siri ham shu muddat bilan cheklanadi.
 */
final class SanctumTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiration_window_is_configured(): void
    {
        $expiration = config('sanctum.expiration');

        $this->assertIsInt($expiration, 'sanctum.expiration belgilanmagan — token muddatsiz.');
        $this->assertGreaterThan(0, $expiration);
    }

    public function test_a_fresh_token_is_accepted(): void
    {
        $token = $this->user()->createToken('test');

        $this->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_token_older_than_the_window_is_rejected(): void
    {
        $token = $this->user()->createToken('test');

        // Guard `created_at` ni oynaga taqqoslaydi (Sanctum Guard.php:128),
        // shuning uchun tokenni oynadan bir daqiqa oshirib "qaritamiz".
        DB::table('personal_access_tokens')
            ->where('id', $token->accessToken->getKey())
            ->update(['created_at' => now()->subMinutes((int) config('sanctum.expiration') + 1)]);

        $this->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    private function user(): User
    {
        DB::table('organizations')->insertOrIgnore([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Token test', 'code' => 'TKN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => 'token-user',
            'email' => 'token-user@example.com', 'password' => bcrypt('Secret123!'), 'auth_source' => 'LOCAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }
}
