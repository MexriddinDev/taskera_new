<?php

declare(strict_types=1);

namespace Tests\Feature\ITSM;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Elektron ruxsatnoma bo'limi — qayd yuritish va kirish huquqi. */
final class PermitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Permit test', 'code' => 'PRM',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->admin = $this->user('permit-admin', ['permits.manage']);
        $this->staff = $this->user('support-agent', ['tickets.view']);
    }

    public function test_admin_can_create_search_and_delete_a_permit(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/permits', [
            'last_name' => 'Aliyev',
            'first_name' => 'Vali',
            'middle_name' => 'Salimovich',
            'document_type' => 'PASSPORT',
            'document_number' => 'AA1234567',
            'visit_purpose' => 'Server xonasiga texnik xizmat',
            'visit_at' => '2026-09-15 09:30:00',
            'host_department' => 'IT departament',
        ])->assertCreated()
            ->assertJsonPath('data.full_name', 'Aliyev Vali Salimovich')
            ->json('data.id');

        $this->getJson('/api/v1/permits?search=Vali')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_type', 'PASSPORT');

        $this->putJson("/api/v1/permits/{$id}", ['visit_purpose' => 'Kabel tortish'])
            ->assertOk()
            ->assertJsonPath('data.visit_purpose', 'Kabel tortish');

        $this->deleteJson("/api/v1/permits/{$id}")->assertOk();
        $this->assertSoftDeleted('permits', ['id' => $id]);
    }

    public function test_required_fields_and_document_type_are_validated(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/permits', ['document_type' => 'ID_CARD'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name', 'first_name', 'document_type', 'visit_purpose']);
    }

    public function test_staff_without_permission_cannot_reach_the_section(): void
    {
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/permits')->assertForbidden();
        $this->postJson('/api/v1/permits', [
            'last_name' => 'Test',
            'first_name' => 'Sinov',
            'document_type' => 'PASSPORT',
            'visit_purpose' => 'Tashrif',
        ])->assertForbidden();
    }

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
