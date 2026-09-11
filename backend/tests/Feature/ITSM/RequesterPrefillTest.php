<?php

declare(strict_types=1);

namespace Tests\Feature\ITSM;

use App\Models\User;
use App\Support\RequesterPrefill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Shablon matnidagi bo'sh qatorlarni so'rovchi ma'lumoti bilan to'ldirish.
 *
 * Eng muhim shart: BAZADAGI matn o'zgarmaydi. Shuning uchun sozlamalar
 * bo'limi (parametrsiz so'rov) xom matnni oladi.
 */
final class RequesterPrefillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        DB::table('organizations')->insert([
            'id' => 1,
            'public_id' => (string) Str::uuid(),
            'name' => 'Prefill Test',
            'code' => 'PRE-TEST',
            'default_locale_id' => 1,
            'default_timezone_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'username' => 'aamanov',
            'email' => 'aamanov@example.com',
            'password' => bcrypt('Secret123!'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user = User::findOrFail($id);
    }

    public function test_known_labels_are_filled_and_the_rest_is_untouched(): void
    {
        $text = "Antivirus o‘rnatish so‘rovi.\n\n"
            ."Xodim F.I.Sh:\n"
            ."Tarkibiy bo‘linma / Departament:\n"
            ."Telefon raqami:\n"
            ."Muammoning qisqacha tavsifi:";

        $filled = RequesterPrefill::apply($text, $this->user);

        // Xodim kartochkasi yo'q — ism sifatida username ishlatiladi.
        $this->assertStringContainsString('Xodim F.I.Sh: aamanov', $filled);
        // Qiymati yo'q maydonlar bo'sh qoladi: "—" yozib qo'yilmaydi.
        $this->assertStringContainsString("Telefon raqami:\n", $filled);
        // Tanish bo'lmagan qator tegilmaydi.
        $this->assertStringContainsString("Muammoning qisqacha tavsifi:", $filled);
        $this->assertStringStartsWith('Antivirus o‘rnatish so‘rovi.', $filled);
    }

    public function test_already_filled_line_is_not_overwritten(): void
    {
        $filled = RequesterPrefill::apply("Xodim F.I.Sh: Boshqa odam", $this->user);

        $this->assertSame('Xodim F.I.Sh: Boshqa odam', $filled);
    }

    public function test_endpoint_returns_raw_text_without_the_prefill_flag(): void
    {
        Sanctum::actingAs($this->user);

        DB::table('ticket_templates')->insert([
            'team_id' => null,
            'name' => 'Umumiy shablon',
            'content' => "Xodim F.I.Sh:\nMuammo:",
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sozlamalar bo'limi — xom matn (aks holda admin tahrirlaganda
        // o'z ismini shablon ichiga saqlab qo'yardi).
        $this->getJson('/api/v1/ticket-templates')
            ->assertOk()
            ->assertJsonPath('data.0.content', "Xodim F.I.Sh:\nMuammo:");

        // Zayavka oynasi — to'ldirilgan matn.
        $this->getJson('/api/v1/ticket-templates?prefill=1')
            ->assertOk()
            ->assertJsonPath('data.0.content', "Xodim F.I.Sh: aamanov\nMuammo:");
    }
}
