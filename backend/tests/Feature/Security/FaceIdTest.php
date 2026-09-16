<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ichki xavfsizlik → FaceID: yozuv yaratish, ro'yxat va huquq tekshiruvi.
 */
final class FaceIdTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'FaceID test', 'code' => 'FID',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->officer = $this->user('officer', ['security.manage']);
        $this->outsider = $this->user('outsider', ['tickets.view']);

        Storage::fake('public');
    }

    public function test_it_stores_a_record_with_photo_and_pdf(): void
    {
        Sanctum::actingAs($this->officer);

        $this->postJson('/api/v1/face-id', [
            'pinfl' => '32503890123456',
            'last_name' => 'Nuriddinov',
            'first_name' => 'Mexriddin',
            'middle_name' => 'Muxiddinovich',
            'photo' => UploadedFile::fake()->image('face.jpg'),
            'document' => UploadedFile::fake()->create('hujjat.pdf', 120, 'application/pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Nuriddinov Mexriddin Muxiddinovich')
            // Sana yuborilmadi — PINFL raqamining o'zidan hisoblanishi kerak.
            ->assertJsonPath('data.birth_date', '1989-03-25')
            ->assertJsonPath('data.has_photo', true)
            ->assertJsonPath('data.has_document', true);

        $this->assertDatabaseHas('face_id_records', [
            'pinfl' => '32503890123456',
            'last_name' => 'Nuriddinov',
        ]);
    }

    public function test_pinfl_and_names_are_validated(): void
    {
        Sanctum::actingAs($this->officer);

        // PINFL 14 xonali bo'lishi shart.
        $this->postJson('/api/v1/face-id', [
            'pinfl' => '123',
            'last_name' => 'Nuriddinov',
            'first_name' => 'Mexriddin',
        ])->assertStatus(422);

        // Ism/familiya majburiy.
        $this->postJson('/api/v1/face-id', ['pinfl' => '32503890123456'])
            ->assertStatus(422);
    }

    /**
     * Rasm faqat JPG yoki PNG.
     *
     * Formada "Faqat JPG yoki PNG" deb yozilgan, lekin tekshiruv `webp` ni ham
     * o'tkazib yuborardi — yozuv bilan xatti-harakat zid edi.
     */
    public function test_only_jpg_and_png_are_accepted_as_the_photo(): void
    {
        Sanctum::actingAs($this->officer);

        foreach (['jpg', 'png'] as $extension) {
            $this->postJson('/api/v1/face-id', [
                'pinfl' => '32503890123456', 'last_name' => 'Nuriddinov', 'first_name' => 'Mexriddin',
                'photo' => UploadedFile::fake()->image("rasm.{$extension}", 40, 40),
            ])->assertCreated();
        }

        foreach (['webp', 'gif', 'bmp'] as $extension) {
            $this->postJson('/api/v1/face-id', [
                'pinfl' => '32503890123456', 'last_name' => 'Nuriddinov', 'first_name' => 'Mexriddin',
                'photo' => UploadedFile::fake()->image("rasm.{$extension}", 40, 40),
            ])->assertStatus(422);
        }
    }

    /**
     * Diskdagi kengaytma fayl mazmunidan olinadi.
     *
     * PNG ni `rasm.svg` deb yuborish mumkin edi: tekshiruv (mazmuni PNG)
     * o'tkazib yuborardi, fayl esa diskda `.svg` bo'lib qolardi.
     */
    public function test_the_stored_photo_keeps_its_real_extension(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->officer);

        $image = UploadedFile::fake()->image('haqiqiy.png', 40, 40);
        $renamed = new UploadedFile($image->getPathname(), 'haqiqiy.svg', 'image/png', null, true);

        $this->postJson('/api/v1/face-id', [
            'pinfl' => '32503890123456', 'last_name' => 'Nuriddinov', 'first_name' => 'Mexriddin',
            'photo' => $renamed,
        ])->assertCreated();

        $path = (string) DB::table('face_id_records')->orderByDesc('id')->value('photo_path');
        $this->assertStringEndsWith('.png', $path, "Fayl nomidagi kengaytma emas, mazmuni hal qilishi kerak.");
    }

    public function test_only_pdf_is_accepted_as_the_document(): void
    {
        Sanctum::actingAs($this->officer);

        $this->postJson('/api/v1/face-id', [
            'pinfl' => '32503890123456',
            'last_name' => 'Nuriddinov',
            'first_name' => 'Mexriddin',
            'document' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertStatus(422);
    }

    public function test_the_list_can_be_searched(): void
    {
        Sanctum::actingAs($this->officer);

        $this->postJson('/api/v1/face-id', [
            'pinfl' => '32503890123456', 'last_name' => 'Nuriddinov', 'first_name' => 'Mexriddin',
        ])->assertCreated();
        $this->postJson('/api/v1/face-id', [
            'pinfl' => '40511920123456', 'last_name' => 'Karimov', 'first_name' => 'Alisher',
        ])->assertCreated();

        $this->getJson('/api/v1/face-id')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/face-id?search=Karimov')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Karimov');

        // PINFL bo'yicha ham topilsin.
        $this->getJson('/api/v1/face-id?search=325038')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Nuriddinov');
    }

    /** Ro'yxat yozuv kiritilgan vaqt bo'yicha filtrlanadi. */
    public function test_the_list_can_be_filtered_by_date(): void
    {
        Sanctum::actingAs($this->officer);

        $this->postJson('/api/v1/face-id', [
            'pinfl' => '32503890123456', 'last_name' => 'Nuriddinov', 'first_name' => 'Mexriddin',
        ])->assertCreated();

        // Eski yozuv: kiritilgan vaqti orqaga suriladi.
        $oldId = $this->postJson('/api/v1/face-id', [
            'pinfl' => '40511920123456', 'last_name' => 'Karimov', 'first_name' => 'Alisher',
        ])->assertCreated()->json('data.id');
        DB::table('face_id_records')->where('id', $oldId)->update(['created_at' => now()->subDays(10)]);

        $this->getJson('/api/v1/face-id?from='.now()->subDay()->format('Y-m-d\TH:i'))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Nuriddinov');

        $this->getJson('/api/v1/face-id?to='.now()->subDay()->format('Y-m-d\TH:i'))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Karimov');

        // Bo'sh chegara cheklamaydi.
        $this->getJson('/api/v1/face-id?from=&to=')->assertOk()->assertJsonCount(2, 'data');

        // Noto'g'ri sana jimgina e'tiborsiz qolmaydi.
        $this->getJson('/api/v1/face-id?from=kecha')->assertStatus(422);
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->getJson('/api/v1/face-id')->assertForbidden();
        $this->postJson('/api/v1/face-id', [
            'pinfl' => '32503890123456', 'last_name' => 'X', 'first_name' => 'Y',
        ])->assertForbidden();
        $this->postJson('/api/v1/face-id/lookup', ['pinfl' => '32503890123456'])->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function user(string $name, array $permissions): User
    {
        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => $name,
            'email' => $name.'@example.com', 'password' => bcrypt('Secret123!'), 'auth_source' => 'LOCAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('model_has_permissions')->insert([
                'permission_id' => $permissionId, 'model_type' => User::class, 'model_id' => $id,
            ]);
        }

        return User::findOrFail($id);
    }
}
