<?php

namespace Tests\Feature\ITSM;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $support;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(12, 0));
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Support', 'code' => 'SUP',
            'created_at' => now(), 'updated_at' => now()]);

        $this->support = $this->user('support-agent', ['tickets.view', 'tickets.assign']);
        $this->requester = $this->user('plain-requester');
        Sanctum::actingAs($this->support);
    }

    private function user(string $name, array $permissions = []): User
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => $name,
            'email' => $name.'@example.com', 'password' => bcrypt('test-password'), 'auth_source' => 'LOCAL',
            'created_at' => now(), 'updated_at' => now()]);

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('model_has_permissions')->insert(['permission_id' => $permissionId, 'model_type' => User::class, 'model_id' => $id]);
        }

        return User::findOrFail($id);
    }

    private function ticket(array $values = []): int
    {
        return DB::table('tickets')->insertGetId(array_merge([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'ticket_no' => 'INC-'.Str::random(6),
            'ticket_type' => 'INCIDENT', 'subject' => 'Printer ishlamayapti', 'description' => 'Printer ishlamayapti',
            'status_id' => 1, 'priority_id' => 3, 'source_id' => 1, 'requester_user_id' => $this->requester->id,
            'assigned_user_id' => $this->support->id, 'created_at' => now(), 'updated_at' => now(),
        ], $values));
    }

    public function test_staff_row_counts_tickets_by_status_and_sla(): void
    {
        $this->ticket(); // ochiq
        $this->ticket(['status_id' => 4]); // jarayonda
        $this->ticket(['status_id' => 7, 'resolved_at' => now(), 'due_at' => now()->addHour()]); // muddat ichida
        $this->ticket(['status_id' => 7, 'resolved_at' => now(), 'due_at' => now()->subHour()]); // muddat buzilgan

        $row = collect($this->getJson('/api/v1/support-panel/staff')->assertOk()->json('data'))
            ->firstWhere('user_id', $this->support->id);

        $this->assertSame(4, $row['total_tickets']);
        $this->assertSame(1, $row['open_tickets']);
        $this->assertSame(1, $row['in_progress_tickets']);
        $this->assertSame(2, $row['resolved_tickets']);
        $this->assertSame(2, $row['sla_tracked']);
        $this->assertSame(1, $row['sla_breached']);
        // JSON butun songa aylantiradi (50.0 -> 50), shuning uchun qat'iy tur tekshirilmaydi.
        $this->assertEquals(50, $row['sla_compliance']);
        $this->assertSame('Printer ishlamayapti', $row['last_ticket']['subject']);
    }

    public function test_staff_rows_respect_the_date_range(): void
    {
        $this->ticket(['created_at' => now()->subDays(20)]);
        $this->ticket();

        $row = collect($this->getJson('/api/v1/support-panel/staff?start_date='.now()->subDay()->toDateString())->assertOk()->json('data'))
            ->firstWhere('user_id', $this->support->id);

        $this->assertSame(1, $row['total_tickets']);
    }

    public function test_agent_tickets_can_be_filtered_by_status_and_search(): void
    {
        $this->ticket(['subject' => 'Ochiq ish']);
        $this->ticket(['status_id' => 7, 'subject' => 'Yopilgan ish', 'resolved_at' => now(), 'due_at' => now()->subHour()]);

        $all = $this->getJson("/api/v1/support-panel/staff/{$this->support->id}/tickets")->assertOk();
        $this->assertSame(2, $all->json('meta.total'));

        $done = $this->getJson("/api/v1/support-panel/staff/{$this->support->id}/tickets?status=done")->assertOk();
        $this->assertSame(1, $done->json('meta.total'));
        $this->assertSame('Yopilgan ish', $done->json('data.0.subject'));
        $this->assertTrue($done->json('data.0.sla_breached'));

        $search = $this->getJson("/api/v1/support-panel/staff/{$this->support->id}/tickets?search=Ochiq")->assertOk();
        $this->assertSame(1, $search->json('meta.total'));
    }

    public function test_the_panel_requires_the_ticket_view_permission(): void
    {
        Sanctum::actingAs($this->requester);
        $this->getJson('/api/v1/support-panel/staff')->assertForbidden();
    }
}
