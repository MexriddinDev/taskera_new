<?php

declare(strict_types=1);

namespace Tests\Feature\ITSM;

use App\Models\User;
use App\Modules\Ticketing\Domain\Services\TicketSlaService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SlaRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regular;
    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        DB::table('organizations')->insert([
            'id' => 1,
            'public_id' => (string) Str::uuid(),
            'name' => 'SLA Test',
            'code' => 'SLA-TEST',
            'default_locale_id' => 1,
            'default_timezone_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->admin = $this->user('superadmin');
        $this->regular = $this->user('sla_viewer');
        $this->teamId = DB::table('teams')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'code' => 'PRINTER',
            'name' => 'Printer guruhi',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_create_edit_toggle_and_delete_team_sla(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/sla-rules', [
            'team_id' => $this->teamId,
            'name' => 'Printer ishlamayapti',
            'description' => 'Printer muammolari uchun qoida',
            'accept_minutes' => 15,
            'work_minutes' => 30,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.team.name', 'Printer guruhi')
            ->json('data.id');

        $this->getJson("/api/v1/sla-rules?team_id={$this->teamId}&is_active=1&per_page=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Printer muammolari uchun qoida');

        $this->postJson('/api/v1/sla-rules', [
            'team_id' => $this->teamId,
            'name' => 'Takroriy',
            'accept_minutes' => 10,
            'work_minutes' => 20,
        ])->assertUnprocessable();

        $this->putJson("/api/v1/sla-rules/{$id}", [
            'name' => 'Printer SLA',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Printer SLA')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/sla-rules/{$id}")->assertOk();
        $this->assertSoftDeleted('sla_rules', ['id' => $id]);
    }

    public function test_non_manager_cannot_write_sla_rules(): void
    {
        Sanctum::actingAs($this->regular);

        $this->getJson('/api/v1/sla-rules')->assertOk();
        $this->postJson('/api/v1/sla-rules', [
            'team_id' => $this->teamId,
            'name' => 'Ruxsatsiz SLA',
            'accept_minutes' => 15,
            'work_minutes' => 30,
        ])->assertForbidden();
    }

    public function test_ticket_sla_uses_active_team_rule_and_calculates_overdue_minutes(): void
    {
        $this->travelTo('2026-09-08 10:00:00');
        DB::table('sla_rules')->insert([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'team_id' => $this->teamId,
            'name' => 'Printer ishlamayapti',
            'accept_minutes' => 15,
            'work_minutes' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TicketSlaService::forgetRules();

        $ticket = Ticket::create([
            'organization_id' => 1,
            'ticket_no' => 'T-001',
            'ticket_type' => 'INCIDENT',
            'subject' => 'Printer ishlamayapti',
            'description' => 'Printer qog‘oz olmayapti',
            'status_id' => 4,
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $this->regular->id,
            'assigned_team_id' => $this->teamId,
            'started_at' => now()->subMinutes(42),
            'created_at' => now()->subMinutes(50),
            'updated_at' => now(),
        ]);

        $sla = app(TicketSlaService::class)->forTicket($ticket);
        $this->assertCount(2, $sla);
        $this->assertSame('MET', $sla[0]['status']);
        $this->assertSame('BREACHED', $sla[1]['status']);
        $this->assertSame(12, $sla[1]['overdueMinutes']);
        $this->assertSame('Printer ishlamayapti', $sla[1]['slaName']);

        DB::table('sla_rules')->where('team_id', $this->teamId)->update(['is_active' => false]);
        TicketSlaService::forgetRules();
        $this->assertSame([], app(TicketSlaService::class)->forTicket($ticket));
    }

    private function user(string $username): User
    {
        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => bcrypt('Secret123!'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }
}
