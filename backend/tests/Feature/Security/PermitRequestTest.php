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
 * Elektron ruxsatnoma: xodim so'rov yuboradi, Ichki xavfsizlik qaror qabul qiladi.
 */
final class PermitRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Permit test', 'code' => 'PRQ',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // So'rov yuborgan xodimning kartochkasi qorovul postida ko'rinishi
        // kerak, shuning uchun testda ham to'liq zanjir yaratiladi.
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
        DB::table('departments')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'IT', 'name' => 'IT Departament', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'code' => 'LEAD', 'name' => 'Yetakchi mutaxassis', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employment_statuses')->insert([
            'id' => 1, 'code' => 'ACTIVE', 'name' => 'Faol', 'can_login' => true, 'is_active' => true,
        ]);
        $employeeId = DB::table('employees')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'employee_no' => 'E-001', 'department_id' => 1, 'branch_id' => 1,
            'position_id' => 1, 'employment_status_id' => 1,
            'first_name' => 'Yusuf', 'last_name' => 'Rahimboyev',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->employee = $this->user('xodim', [], $employeeId);
        $this->officer = $this->user('xavfsizlik', ['security.manage']);
    }

    public function test_an_employee_submits_a_request_and_sees_only_their_own(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov',
            'first_name' => 'Alisher',
            'document_type' => 'PASSPORT',
            'visitor_organization' => 'Uzinfocom',
            'visit_purpose' => 'Server xonasiga texnik xizmat',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.requester', 'xodim');

        // Boshqa xodimning so'rovi ro'yxatga tushmasligi kerak.
        $other = $this->user('boshqa', []);
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Begona', 'first_name' => 'Mehmon',
            'document_type' => 'PASSPORT', 'visit_purpose' => 'Uchrashuv',
        ])->assertCreated();

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/v1/permit-requests/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Karimov Alisher');
    }

    public function test_required_fields_are_validated(): void
    {
        Sanctum::actingAs($this->employee);

        // Familiya/ism/guvohnoma turi va maqsad — majburiy.
        $this->postJson('/api/v1/permit-requests', ['last_name' => 'Karimov'])->assertStatus(422);
        $this->postJson('/api/v1/permit-requests', ['visit_purpose' => 'Uchrashuv'])->assertStatus(422);
        // Guvohnoma turi ro'yxatdan tashqari bo'lsa ham rad etiladi.
        $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov', 'first_name' => 'Alisher',
            'document_type' => 'MILITARY_ID', 'visit_purpose' => 'Uchrashuv',
        ])->assertStatus(422);
    }

    public function test_security_sees_the_queue_with_pending_first_and_can_approve(): void
    {
        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov', 'first_name' => 'Alisher',
            'document_type' => 'PASSPORT', 'visit_purpose' => 'Texnik xizmat',
        ])->assertCreated();

        Sanctum::actingAs($this->officer);

        $id = $this->getJson('/api/v1/permit-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0.id');

        $this->postJson("/api/v1/permit-requests/{$id}/decide", ['status' => 'APPROVED'])
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.decided_by', 'xavfsizlik');
    }

    /** Rad etishda sabab majburiy — qaror izohsiz qolmasin. */
    public function test_rejecting_requires_a_reason(): void
    {
        Sanctum::actingAs($this->employee);
        $id = $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov', 'first_name' => 'Alisher',
            'document_type' => 'PASSPORT', 'visit_purpose' => 'Texnik xizmat',
        ])->json('data.id');

        Sanctum::actingAs($this->officer);

        $this->postJson("/api/v1/permit-requests/{$id}/decide", ['status' => 'REJECTED'])
            ->assertStatus(422);

        $this->postJson("/api/v1/permit-requests/{$id}/decide", [
            'status' => 'REJECTED',
            'decision_reason' => 'Tashrif sanasi ko\'rsatilmagan',
        ])->assertOk()->assertJsonPath('data.status', 'REJECTED');
    }

    /** Qaror bir marta qabul qilinadi. */
    public function test_a_decision_cannot_be_changed(): void
    {
        Sanctum::actingAs($this->employee);
        $id = $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov', 'first_name' => 'Alisher',
            'document_type' => 'PASSPORT', 'visit_purpose' => 'Texnik xizmat',
        ])->json('data.id');

        Sanctum::actingAs($this->officer);
        $this->postJson("/api/v1/permit-requests/{$id}/decide", ['status' => 'APPROVED'])->assertOk();
        $this->postJson("/api/v1/permit-requests/{$id}/decide", ['status' => 'REJECTED', 'decision_reason' => 'Fikr o\'zgardi'])
            ->assertStatus(422);
    }

    public function test_an_employee_cannot_see_the_queue_or_decide(): void
    {
        Sanctum::actingAs($this->employee);
        $id = $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov', 'first_name' => 'Alisher',
            'document_type' => 'PASSPORT', 'visit_purpose' => 'Texnik xizmat',
        ])->json('data.id');

        $this->getJson('/api/v1/permit-requests')->assertForbidden();
        $this->postJson("/api/v1/permit-requests/{$id}/decide", ['status' => 'APPROVED'])->assertForbidden();
    }

    /**
     * Kirish faqat RUXSAT BERILGAN so'rov bo'yicha qayd etiladi.
     *
     * Tasdiqlanmagan mehmonni ichkariga kiritib bo'lmaydi — qorovul posti
     * uchun asosiy qoida.
     */
    public function test_entry_requires_an_approved_request(): void
    {
        $id = $this->submit();

        Sanctum::actingAs($this->officer);
        $this->postJson("/api/v1/permit-requests/{$id}/enter")->assertStatus(422);

        $this->postJson("/api/v1/permit-requests/{$id}/decide", ['status' => 'APPROVED'])->assertOk();
        $this->postJson("/api/v1/permit-requests/{$id}/enter")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertNotNull(
            $this->getJson('/api/v1/permit-requests')->json('data.0.entered_at'),
            'Kirish vaqti yozilishi kerak.'
        );
    }

    /** Chiqish kirishdan oldin qayd etilmaydi, ikkalasi ham bir martalik. */
    public function test_exit_follows_entry_and_each_is_recorded_once(): void
    {
        $id = $this->submit();

        Sanctum::actingAs($this->officer);
        $this->postJson("/api/v1/permit-requests/{$id}/decide", ['status' => 'APPROVED'])->assertOk();

        // Kirmasdan chiqib bo'lmaydi.
        $this->postJson("/api/v1/permit-requests/{$id}/exit")->assertStatus(422);

        $this->postJson("/api/v1/permit-requests/{$id}/enter")->assertOk();
        // Ikkinchi marta kirish qayd etilmaydi.
        $this->postJson("/api/v1/permit-requests/{$id}/enter")->assertStatus(422);

        $this->postJson("/api/v1/permit-requests/{$id}/exit")->assertOk();
        $this->postJson("/api/v1/permit-requests/{$id}/exit")->assertStatus(422);
    }

    /** Qorovul postida "kim chaqirgan" ko'rinib turishi kerak. */
    public function test_the_queue_shows_the_requesting_employee_card(): void
    {
        $this->submit();

        Sanctum::actingAs($this->officer);
        $row = $this->getJson('/api/v1/permit-requests')->assertOk()->json('data.0');

        $this->assertSame('Rahimboyev Yusuf', $row['requester_card']['name']);
        $this->assertSame('IT Departament', $row['requester_card']['department']);
        $this->assertSame('Yetakchi mutaxassis', $row['requester_card']['position']);
    }

    /** Ro'yxat F.I.Sh va guvohnoma raqami bo'yicha qidiriladi. */
    public function test_the_queue_can_be_searched(): void
    {
        $this->submit();

        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Toshmatov', 'first_name' => 'Sardor',
            'document_type' => 'PASSPORT', 'document_number' => 'AD4232369',
            'visit_purpose' => 'Uchrashuv',
        ])->assertCreated();

        Sanctum::actingAs($this->officer);

        $this->getJson('/api/v1/permit-requests?search=Toshmatov')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Toshmatov');

        $this->getJson('/api/v1/permit-requests?search=AD4232369')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Toshmatov');
    }

    /**
     * Guvohnoma raqami shakli: 2 ta katta harf va 7 ta raqam.
     *
     * Uchala hujjat turi uchun ham bir xil (`PermitRequest::DOCUMENT_PATTERNS`).
     */
    public function test_the_document_number_format_is_enforced(): void
    {
        Sanctum::actingAs($this->employee);

        foreach (['ID_CARD', 'PASSPORT', 'DRIVER_LICENSE'] as $type) {
            // To'g'ri shakl.
            $this->postJson('/api/v1/permit-requests', [
                'last_name' => 'Karimov', 'first_name' => 'Alisher',
                'document_type' => $type, 'document_number' => 'AA1234567',
                'visit_purpose' => 'Texnik xizmat',
            ])->assertCreated();

            // Kichik harf, harf soni, raqam soni — hammasi rad etiladi.
            foreach (['aa1234567', 'A1234567', 'AAA1234567', 'AA123456', 'AA12345678', 'AA123456X'] as $bad) {
                $this->postJson('/api/v1/permit-requests', [
                    'last_name' => 'Karimov', 'first_name' => 'Alisher',
                    'document_type' => $type, 'document_number' => $bad,
                    'visit_purpose' => 'Texnik xizmat',
                ])->assertStatus(422);
            }
        }
    }

    /** Raqam ixtiyoriy — berilmasa shakl tekshirilmaydi. */
    public function test_the_document_number_stays_optional(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov', 'first_name' => 'Alisher',
            'document_type' => 'ID_CARD', 'visit_purpose' => 'Texnik xizmat',
        ])->assertCreated();
    }

    /** Navbat holat bo'yicha filtrlanadi. */
    public function test_the_queue_can_be_filtered_by_status(): void
    {
        $approvedId = $this->submit();
        $pendingId = $this->submit();

        Sanctum::actingAs($this->officer);
        $this->postJson("/api/v1/permit-requests/{$approvedId}/decide", ['status' => 'APPROVED'])->assertOk();

        $this->getJson('/api/v1/permit-requests?status=PENDING')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendingId);

        $this->getJson('/api/v1/permit-requests?status=APPROVED')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approvedId);

        // Filtrsiz — ikkalasi ham.
        $this->getJson('/api/v1/permit-requests')->assertOk()->assertJsonCount(2, 'data');
    }

    /** Kelib tushgan vaqt javobda bo'lishi kerak — tafsilotda ko'rsatiladi. */
    public function test_the_response_carries_the_created_time(): void
    {
        $this->submit();

        Sanctum::actingAs($this->officer);
        $this->assertNotNull($this->getJson('/api/v1/permit-requests')->json('data.0.created_at'));
    }

    /** Sinov so'rovi yuboradi va id sini qaytaradi. */
    private function submit(): int
    {
        Sanctum::actingAs($this->employee);

        return (int) $this->postJson('/api/v1/permit-requests', [
            'last_name' => 'Karimov', 'first_name' => 'Alisher',
            'document_type' => 'PASSPORT', 'visit_purpose' => 'Texnik xizmat',
        ])->assertCreated()->json('data.id');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function user(string $name, array $permissions, ?int $employeeId = null): User
    {
        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => $name,
            'email' => $name.'@example.com', 'password' => bcrypt('Secret123!'), 'auth_source' => 'LOCAL',
            'employee_id' => $employeeId,
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
