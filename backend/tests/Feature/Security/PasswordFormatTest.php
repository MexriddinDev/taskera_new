<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Profildagi parol almashtirish shakli.
 *
 * Talab qilingan namuna: `AAzz+123` — 2 ta katta harf, 2 ta kichik harf,
 * `+` belgisi va 3 ta raqam. Harf va raqamlar ixtiyoriy, lekin TARTIB qat'iy:
 * o'rinlarni almashtirib yozib bo'lmaydi va `+` o'z joyida qoladi.
 */
final class PasswordFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Parol test', 'code' => 'PWD',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // LOCAL foydalanuvchi: AD (Exchange) yo'li ishga tushmaydi.
        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => 'xodim',
            'email' => 'xodim@example.com', 'password' => bcrypt('EskiParol1'),
            'auth_source' => 'LOCAL', 'status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::findOrFail($id);
    }

    /** Namunaga mos parol qabul qilinadi va haqiqatan saqlanadi. */
    public function test_a_password_in_the_required_shape_is_accepted(): void
    {
        Sanctum::actingAs($this->user);

        foreach (['AAzz+123', 'QWer+456', 'ZZaa+000'] as $password) {
            $this->postJson('/api/v1/auth/change-password', [
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertOk();
        }

        $this->assertTrue(
            Hash::check('ZZaa+000', (string) $this->user->fresh()->password),
            'Oxirgi parol saqlanishi kerak.'
        );
    }

    /**
     * Tartib qat'iy: `+` o'z joyida qoladi, harf/raqam bloklari almashmaydi.
     */
    public function test_the_order_cannot_be_swapped(): void
    {
        Sanctum::actingAs($this->user);

        $swapped = [
            'zzAA+123',   // kichik va katta harflar almashgan
            'AAzz123+',   // `+` oxirida
            '123+AAzz',   // raqamlar boshida
            'AA+zz123',   // `+` o'rtada emas, uchinchi o'rinda
            'zAzA+123',   // harflar aralashgan
        ];

        foreach ($swapped as $password) {
            $this->postJson('/api/v1/auth/change-password', [
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertStatus(422, "«{$password}» rad etilishi kerak: tartib buzilgan.");
        }
    }

    /** Har blokdagi belgilar soni ham qat'iy. */
    public function test_the_length_of_each_block_is_fixed(): void
    {
        Sanctum::actingAs($this->user);

        $bad = [
            'Aazz+123',    // 1 ta katta harf
            'AAZz+123',    // 3 ta katta, 1 ta kichik
            'AAAzz+123',   // 3 ta katta harf
            'AAzzz+123',   // 3 ta kichik harf
            'AAzz+12',     // 2 ta raqam
            'AAzz+1234',   // 4 ta raqam
            'AAzz123',     // `+` yo'q
            'AAzz-123',    // boshqa belgi
            'AAzz+12a',    // raqam o'rnida harf
            'Secret123!',  // eski uslubdagi parol
            '',            // bo'sh
        ];

        foreach ($bad as $password) {
            $this->postJson('/api/v1/auth/change-password', [
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertStatus(422, "«{$password}» rad etilishi kerak.");
        }

        // Rad etilganlardan keyin eski parol o'zgarmagan bo'lishi kerak.
        $this->assertTrue(Hash::check('EskiParol1', (string) $this->user->fresh()->password));
    }

    /** Tasdiq maydoni mos kelmasa ham o'tmaydi. */
    public function test_the_confirmation_must_match(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/auth/change-password', [
            'password' => 'AAzz+123',
            'password_confirmation' => 'AAzz+124',
        ])->assertStatus(422);
    }
}
