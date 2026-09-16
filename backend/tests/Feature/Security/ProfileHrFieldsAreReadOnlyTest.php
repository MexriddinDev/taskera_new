<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * HR manbasidan keladigan maydonlar profilda tahrirlanmaydi.
 *
 * `AdUserProvisionService::findOrProvision` har AD login'da ism, familiya,
 * otasining ismi, pochta va telefonni AD'dan olib ustidan qayta yozadi.
 * Ya'ni bu maydonlarni profilda tahrirlash allaqachon befoyda edi:
 * foydalanuvchi kiritgan qiymat keyingi kirishda jimgina yo'qolardi.
 * Forma endi haqiqatni ko'rsatadi — maydonlar faqat o'qish uchun.
 */
final class ProfileHrFieldsAreReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Profil test', 'code' => 'PRF',
            'default_locale_id' => 1, 'default_timezone_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $regionId = DB::table('regions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'TSH', 'name' => 'Toshkent', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'region_id' => $regionId, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'HQ', 'name' => 'Bosh ofis', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('departments')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'branch_id' => $branchId,
            'code' => 'IT', 'name' => 'IT', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $positionId = DB::table('positions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'SUP', 'name' => 'Support xodimi', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $employeeId = DB::table('employees')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'employee_no' => 'EMP-1',
            'first_name' => 'Yusuf', 'last_name' => 'Rahimboyev', 'middle_name' => 'Baxtiyorovich',
            'email' => 'yusuf@example.com', 'phone' => '998932129905',
            'department_id' => 1, 'branch_id' => $branchId, 'position_id' => $positionId, 'employment_status_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'employee_id' => $employeeId,
            'username' => 'yusuf', 'email' => 'yusuf@example.com', 'password' => bcrypt('Secret123!'),
            'auth_source' => 'AD', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::findOrFail($id);
    }

    public function test_hr_owned_fields_are_ignored_on_profile_update(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/v1/profile', [
            'first_name' => 'Boshqa',
            'last_name' => 'Familiya',
            'middle_name' => 'Otasining',
        ])->assertOk();

        $employee = DB::table('employees')->where('id', $this->user->employee_id)->first();

        $this->assertSame('Yusuf', $employee->first_name);
        $this->assertSame('Rahimboyev', $employee->last_name);
        $this->assertSame('Baxtiyorovich', $employee->middle_name);
    }

    /**
     * Telefon va pochta ham HR maydonlari.
     *
     * Telefon profildan yozilardi, keyingi AD login'da esa
     * `AdUserProvisionService` uni AD dagi raqam bilan qayta yozib yuborardi:
     * xodim o'zgartirgan deb o'ylab yurardi, aslida qiymat yo'qolardi.
     * Pochta esa formada tahrirlanardi, lekin backend uni umuman o'qimasdi.
     */
    public function test_the_phone_and_the_email_cannot_be_edited(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/v1/profile', [
            'phone' => '998900000000',
            'email' => 'boshqa@example.com',
        ])->assertOk();

        $employee = DB::table('employees')->where('id', $this->user->employee_id)->first();
        $user = DB::table('users')->where('id', $this->user->id)->first();

        $this->assertSame('998932129905', $employee->phone, 'Telefon HR manbasida qolishi kerak.');
        $this->assertSame('yusuf@example.com', $employee->email, 'Pochta HR manbasida qolishi kerak.');
        $this->assertSame('yusuf@example.com', $user->email, 'Kirish pochtasi ham o‘zgarmasligi kerak.');
    }

    /**
     * Telefon va pochta yuborilishi qolgan maydonlarni saqlashga xalaqit
     * bermaydi: eski forma ularni baribir yuborishi mumkin.
     */
    public function test_sending_the_phone_does_not_block_the_rest_of_the_form(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/v1/profile', [
            'phone' => '998900000000',
            'address' => 'Toshkent, Yunusobod',
        ])->assertOk();

        $employee = DB::table('employees')->where('id', $this->user->employee_id)->first();
        $attrs = json_decode((string) $employee->attributes, true);

        $this->assertSame('998932129905', $employee->phone);
        $this->assertSame('Toshkent, Yunusobod', $attrs['address']);
    }

    public function test_self_owned_fields_are_still_editable(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/v1/profile', [
            'address' => 'Toshkent, Chilonzor',
            'birth_date' => '1994-09-06',
            'bio' => 'Support xodimi',
        ])->assertOk();

        $employee = DB::table('employees')->where('id', $this->user->employee_id)->first();
        $attrs = json_decode((string) $employee->attributes, true);

        $this->assertSame('Toshkent, Chilonzor', $attrs['address']);
        $this->assertSame('1994-09-06', $attrs['birth_date']);
        $this->assertSame('Support xodimi', $attrs['bio']);
    }
}
