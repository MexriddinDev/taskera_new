<?php

declare(strict_types=1);

namespace Tests\Feature\ITSM;

use App\Models\User;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Zayavkani biriktirish: huquqlar, guruhning saqlanishi va taymer.
 */
final class TicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $assigner;

    private User $viewer;

    private User $target;

    private User $worker;

    private User $requester;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Assign Test', 'code' => 'ASSIGN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->teamId = DB::table('teams')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'code' => 'IT', 'name' => 'IT guruhi',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assigner = $this->user('assigner', ['tickets.view', 'tickets.assign', 'tickets.transition']);
        // Faqat KO'RISH huquqi — amallar unga ochiq bo'lmasligi kerak.
        $this->viewer = $this->user('viewer', ['tickets.view']);
        $this->target = $this->user('target', ['tickets.view', 'tickets.assign']);
        // "support" roli: ishlaydi, lekin dispetcherlik huquqi yo'q.
        $this->worker = $this->user('worker', ['tickets.view', 'tickets.transition']);
        $this->requester = $this->user('requester');
    }

    public function test_assigning_to_another_user_keeps_team_and_restarts_the_timer(): void
    {
        $this->travelTo('2026-09-11 10:00:00');
        $ticket = $this->ticket(['assigned_user_id' => $this->assigner->id, 'started_at' => now()->subMinutes(25)]);

        Sanctum::actingAs($this->assigner);
        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'assignee_user_id' => $this->target->id,
            'reason' => 'Xodim ta\'tilda',
        ])->assertOk();

        $ticket->refresh();

        // Guruh saqlanadi — aks holda zayavka SLA qoidasidan uzilib qolardi.
        $this->assertSame($this->teamId, (int) $ticket->assigned_team_id);
        $this->assertSame($this->target->id, (int) $ticket->assigned_user_id);
        // Taymer noldan: yangi ijrochi uchun `started_at` = hozir.
        $this->assertSame(now()->toDateTimeString(), $ticket->started_at->toDateTimeString());

        // Oldingi ijrochining vaqti tarixda qoladi.
        $this->assertSame(25, (int) DB::table('ticket_assignment_history')
            ->where('ticket_id', $ticket->id)->orderByDesc('id')->value('spent_minutes'));
    }

    public function test_first_assignment_starts_the_timer(): void
    {
        $this->travelTo('2026-09-11 10:00:00');
        $ticket = $this->ticket();

        Sanctum::actingAs($this->assigner);
        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['assignee_user_id' => $this->assigner->id])->assertOk();

        $ticket->refresh();
        $this->assertNotNull($ticket->started_at);
        $this->assertSame($this->teamId, (int) $ticket->assigned_team_id);
    }

    public function test_view_only_user_cannot_assign_or_change_status(): void
    {
        $ticket = $this->ticket();

        Sanctum::actingAs($this->viewer);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['assignee_user_id' => $this->viewer->id])
            ->assertForbidden();

        $this->putJson("/api/v1/tickets/{$ticket->id}", ['assignToMe' => true])
            ->assertForbidden();

        $this->putJson("/api/v1/tickets/{$ticket->id}", ['status' => 'in_progress'])
            ->assertForbidden();

        $this->postJson("/api/v1/tickets/{$ticket->id}/transition", ['to_status_id' => 4])
            ->assertForbidden();
    }

    public function test_requester_can_still_return_own_ticket_without_transition_permission(): void
    {
        $ticket = $this->ticket(['status_id' => 7, 'assigned_user_id' => $this->assigner->id]);

        Sanctum::actingAs($this->requester);
        $this->putJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'rejected',
            'rejectionReason' => 'Muammo hal bo\'lmadi',
        ])->assertOk();
    }

    public function test_worker_without_assign_permission_takes_free_ticket_but_cannot_route_work(): void
    {
        Sanctum::actingAs($this->worker);

        // Egasiz zayavkani o'ziga olish — ishlash huquqi yetarli.
        $free = $this->ticket();
        $this->putJson("/api/v1/tickets/{$free->id}", ['assignToMe' => true])->assertOk();
        $this->assertSame($this->worker->id, (int) $free->refresh()->assigned_user_id);

        $free2 = $this->ticket();
        $this->postJson("/api/v1/tickets/{$free2->id}/assign", ['assignee_user_id' => $this->worker->id])->assertOk();

        // Boshqa xodimga biriktirish — mumkin emas.
        $free3 = $this->ticket();
        $this->postJson("/api/v1/tickets/{$free3->id}/assign", ['assignee_user_id' => $this->target->id])
            ->assertForbidden();

        // Sherigining ishini tortib olish ham mumkin emas.
        $taken = $this->ticket(['assigned_user_id' => $this->target->id]);
        $this->putJson("/api/v1/tickets/{$taken->id}", ['assignToMe' => true, 'reason' => 'Xodim ta\'tilda'])
            ->assertForbidden();
    }

    private function ticket(array $values = []): Ticket
    {
        return Ticket::create(array_merge([
            'organization_id' => 1,
            'ticket_no' => 'INC-'.Str::random(6),
            'ticket_type' => 'INCIDENT',
            'subject' => 'Printer ishlamayapti',
            'description' => 'Printer ishlamayapti',
            'status_id' => 1,
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $this->requester->id,
            'assigned_team_id' => $this->teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $values));
    }

    private function user(string $name, array $permissions = []): User
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
