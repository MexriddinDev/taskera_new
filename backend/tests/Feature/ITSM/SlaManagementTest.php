<?php

namespace Tests\Feature\ITSM;

use App\Models\User;
use App\Modules\SLA\Domain\Services\{SlaEngine, SlaNotificationService};
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Carbon\CarbonImmutable as Time;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SlaManagementTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $agent;
    private int $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Time::parse('2026-09-08 04:00:00', 'UTC'));
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'SLA Test', 'code' => 'SLA-TEST', 'created_at' => now(), 'updated_at' => now()]);
        $this->admin = $this->user('sla-admin', true);
        $this->agent = $this->user('sla-agent');
        Sanctum::actingAs($this->admin);
        $this->calendar = DB::table('business_calendars')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'name' => '24x7', 'timezone_id' => 1, 'is_24x7' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(string $name, bool $manage = false): User
    {
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => $name,
            'email' => $name.'@example.com', 'password' => bcrypt('test-password'), 'auth_source' => 'LOCAL', 'created_at' => now(), 'updated_at' => now()]);
        if ($manage) {
            $permission = DB::table('permissions')->insertGetId(['name' => 'sla.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('model_has_permissions')->insert(['permission_id' => $permission, 'model_type' => User::class, 'model_id' => $id]);
        }
        return User::findOrFail($id);
    }

    private function config(): array
    {
        return ['code' => 'DEFAULT-SLA', 'name' => 'Default SLA', 'description' => 'Test', 'calendar_id' => $this->calendar,
            'effective_from' => '2026-01-01', 'effective_to' => null, 'policy_priority' => 0, 'scope' => [],
            'targets' => [
                ['metric' => 'ASSIGNMENT', 'minutes' => 15, 'start' => 'CREATED', 'calendar_mode' => 'BUSINESS'],
                ['metric' => 'ACCEPTANCE', 'minutes' => 30, 'start' => 'ASSIGNED', 'calendar_mode' => 'BUSINESS'],
                ['metric' => 'FIRST_RESPONSE', 'minutes' => 60, 'start' => 'CREATED', 'calendar_mode' => 'BUSINESS'],
                ['metric' => 'WORK_START', 'minutes' => 60, 'start' => 'ACCEPTED', 'calendar_mode' => 'BUSINESS'],
                ['metric' => 'RESOLUTION', 'minutes' => 100, 'start' => 'CREATED', 'calendar_mode' => 'BUSINESS'],
                ['metric' => 'CLOSURE', 'minutes' => 30, 'start' => 'RESOLVED', 'calendar_mode' => 'BUSINESS'],
            ], 'pause_statuses' => ['WAITING_USER'],
            'extension' => ['enabled' => true, 'max_minutes' => 120, 'approver_ids' => [$this->admin->id]],
            'escalations' => array_map(fn ($p) => ['threshold' => $p, 'assignee' => true, 'user_ids' => [], 'channels' => ['IN_APP', 'EMAIL']], [75, 90, 100, 120])];
    }

    private function publish(array $changes = []): int
    {
        return $this->postJson('/api/v1/sla-management/policies', array_replace($this->config(), $changes) + ['publish' => true])->assertCreated()->json('data.id');
    }

    private function ticket(): Ticket
    {
        return Ticket::create(['organization_id' => 1, 'ticket_no' => 'SLA-'.Str::random(8), 'subject' => 'Test issue', 'description' => 'Test issue',
            'status_id' => 1, 'priority_id' => 3, 'source_id' => 1, 'requester_user_id' => $this->admin->id]);
    }

    public function test_publish_snapshots_calendar_and_draft_does_not_change_existing_ticket(): void
    {
        $id = $this->publish();
        $ticket = $this->ticket();
        $this->assertDatabaseCount('ticket_sla_instances', 6);
        $due = DB::table('ticket_sla_instances')->where('ticket_id', $ticket->id)->where('metric', 'RESOLUTION')->value('due_at');
        $config = $this->config(); $config['targets'][4]['minutes'] = 200;
        $this->putJson('/api/v1/sla-management/policies/'.$id, $config + ['publish' => false])->assertOk();
        $second = $this->ticket();
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $second->id, 'metric' => 'RESOLUTION', 'target_minutes' => 100]);
        $this->putJson('/api/v1/sla-management/policies/'.$id, $config + ['publish' => true])->assertOk();
        $third = $this->ticket();
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $third->id, 'metric' => 'RESOLUTION', 'target_minutes' => 200]);
        $this->assertSame($due, DB::table('ticket_sla_instances')->where('ticket_id', $ticket->id)->where('metric', 'RESOLUTION')->value('due_at'));
        $this->assertDatabaseCount('sla_policy_versions', 2);
    }

    public function test_lifecycle_pause_extension_and_completion(): void
    {
        $this->publish(); $ticket = $this->ticket();
        $ticket->assigned_user_id = $this->agent->id; $ticket->save();
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $ticket->id, 'metric' => 'ASSIGNMENT', 'status' => 'COMPLETED']);
        $ticket->started_at = now(); $ticket->status_id = 4; $ticket->save();
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $ticket->id, 'metric' => 'WORK_START', 'status' => 'COMPLETED']);
        $this->travel(10)->minutes();
        Sanctum::actingAs($this->agent);
        $this->postJson("/api/v1/sla-management/tickets/{$ticket->id}/pause", ['status' => 'WAITING_USER', 'reason' => 'Waiting for details'])->assertOk();
        $this->travel(20)->minutes();
        $this->postJson("/api/v1/sla-management/tickets/{$ticket->id}/resume")->assertOk();
        $i = DB::table('ticket_sla_instances')->where('ticket_id', $ticket->id)->where('metric', 'RESOLUTION')->first();
        $this->assertSame(1200, (int) $i->paused_seconds);
        $e = $this->postJson("/api/v1/sla-management/instances/{$i->id}/extensions", ['minutes' => 30, 'reason_code' => 'COMPLEXITY', 'reason' => 'Needs extra investigation', 'approver_id' => $this->admin->id])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sla-management/extensions/{$e}/decision", ['approve' => true, 'reason' => 'Approved'])->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/sla-management/extensions/{$e}/decision", ['approve' => true, 'reason' => 'Approved'])->assertOk();
        $this->postJson("/api/v1/sla-management/extensions/{$e}/decision", ['approve' => true, 'reason' => 'Approved'])->assertStatus(409);
        $ticket->refresh(); $ticket->status_id = 7; $ticket->resolved_at = now(); $ticket->save();
        $this->assertDatabaseHas('ticket_sla_instances', ['id' => $i->id, 'status' => 'COMPLETED', 'extension_minutes' => 30]);
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $ticket->id, 'metric' => 'CLOSURE', 'status' => 'RUNNING']);
    }

    public function test_escalation_and_notification_outbox_are_idempotent(): void
    {
        Mail::fake();
        $config = $this->config(); $config['targets'] = [$config['targets'][4]];
        $this->publish($config); $ticket = $this->ticket();
        $ticket->assigned_user_id = $this->agent->id; $ticket->save();
        $this->travel(75)->minutes();
        app(SlaEngine::class)->tick(); app(SlaEngine::class)->tick();
        $this->assertDatabaseCount('sla_escalation_outbox', 1);
        app(SlaNotificationService::class)->process(); app(SlaNotificationService::class)->process();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_deliveries', 2);
        $this->travel(25)->minutes();
        app(SlaEngine::class)->tick(); app(SlaEngine::class)->tick();
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $ticket->id, 'status' => 'BREACHED']);
        $this->assertDatabaseCount('sla_escalation_outbox', 3);
        $this->travel(20)->minutes(); app(SlaEngine::class)->tick(); app(SlaEngine::class)->tick();
        $this->assertDatabaseCount('sla_escalation_outbox', 4);
    }

    public function test_monitoring_reports_risk_levels_and_filters_by_level(): void
    {
        $config = $this->config(); $config['targets'] = [$config['targets'][4]]; // faqat RESOLUTION, 100 daqiqa
        $this->publish($config);
        $ticket = $this->ticket();

        $ok = $this->getJson('/api/v1/sla-management/monitoring')->assertOk();
        $ok->assertJsonPath('summary.total', 0);
        $this->assertSame('OK', $ok->json('data.0.level'));
        $this->assertSame(6000, $ok->json('data.0.remaining_seconds'));

        $this->travel(80)->minutes(); // 80/100 = 80%
        $warning = $this->getJson('/api/v1/sla-management/monitoring')->assertOk();
        $this->assertSame('WARNING', $warning->json('data.0.level'));
        $this->assertSame(80, $warning->json('data.0.percent'));
        $warning->assertJsonPath('summary.warning', 1)->assertJsonPath('summary.total', 1);

        $this->travel(25)->minutes(); // muddat tugadi
        $breached = $this->getJson('/api/v1/sla-management/monitoring')->assertOk();
        $breached->assertJsonPath('summary.breached', 1)->assertJsonPath('summary.warning', 0);
        $this->assertSame('BREACHED', $breached->json('data.0.level'));

        $this->getJson('/api/v1/sla-management/monitoring?level=BREACHED')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/sla-management/monitoring?level=WARNING')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame($ticket->id, $breached->json('data.0.ticket_id'));
    }

    public function test_escalation_can_be_delivered_through_the_telegram_bot(): void
    {
        Mail::fake();
        $notifier = $this->mock(\App\Modules\Telegram\Infrastructure\Services\TelegramNotifierService::class);
        $notifier->shouldReceive('sendToUser')->once()
            ->withArgs(fn ($organizationId, $userId, $text) => $organizationId === 1 && $userId === $this->agent->id && str_contains($text, 'SLA 75%'))
            ->andReturnTrue();

        $config = $this->config();
        $config['targets'] = [$config['targets'][4]];
        $config['escalations'] = [['threshold' => 75, 'assignee' => true, 'user_ids' => [], 'channels' => ['TELEGRAM']]];
        $this->publish($config);

        $ticket = $this->ticket();
        $ticket->assigned_user_id = $this->agent->id;
        $ticket->save();

        $this->travel(80)->minutes();
        app(SlaEngine::class)->tick();
        app(SlaNotificationService::class)->process();
        // Ikkinchi yurish takroriy xabar yubormasligi kerak (`once()` shuni tekshiradi).
        app(SlaNotificationService::class)->process();

        $channel = DB::table('notification_channels')->where('code', 'TELEGRAM')->value('id');
        $this->assertDatabaseHas('notification_deliveries', ['channel_id' => $channel, 'status' => 'SENT', 'recipient' => (string) $this->agent->id]);
    }

    public function test_telegram_delivery_is_retried_when_the_account_is_not_linked(): void
    {
        Mail::fake();
        $notifier = $this->mock(\App\Modules\Telegram\Infrastructure\Services\TelegramNotifierService::class);
        $notifier->shouldReceive('sendToUser')->andReturnFalse();

        $config = $this->config();
        $config['targets'] = [$config['targets'][4]];
        $config['escalations'] = [['threshold' => 75, 'assignee' => true, 'user_ids' => [], 'channels' => ['TELEGRAM']]];
        $this->publish($config);

        $ticket = $this->ticket();
        $ticket->assigned_user_id = $this->agent->id;
        $ticket->save();

        $this->travel(80)->minutes();
        app(SlaEngine::class)->tick();
        app(SlaNotificationService::class)->process();

        $channel = DB::table('notification_channels')->where('code', 'TELEGRAM')->value('id');
        $delivery = DB::table('notification_deliveries')->where('channel_id', $channel)->first();
        $this->assertSame('PENDING', $delivery->status);
        $this->assertSame(1, (int) $delivery->attempt_count);
        $this->assertNotNull($delivery->next_attempt_at);
    }

    public function test_sla_follows_raw_column_updates_made_by_the_telegram_bot(): void
    {
        $this->publish();
        $ticket = $this->ticket();

        // Bot zayavka ustunlarini query builder orqali yangilaydi — Eloquent
        // hodisalari, demak SlaTicketObserver ham ishlamaydi.
        DB::table('tickets')->where('id', $ticket->id)->update([
            'assigned_user_id' => $this->agent->id,
            'started_at' => now(),
            'status_id' => 4,
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $ticket->id, 'metric' => 'ACCEPTANCE', 'status' => 'PENDING']);

        // BotConversationService::syncSla() aynan shu chaqiruvni bajaradi.
        app(SlaEngine::class)->sync(Ticket::findOrFail($ticket->id), $this->agent->id);

        foreach (['ASSIGNMENT', 'ACCEPTANCE', 'WORK_START'] as $metric) {
            $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $ticket->id, 'metric' => $metric, 'status' => 'COMPLETED']);
        }
        $this->assertDatabaseHas('ticket_sla_instances', ['ticket_id' => $ticket->id, 'metric' => 'RESOLUTION', 'status' => 'RUNNING']);
    }

    public function test_permissions_validation_and_cross_organization_access(): void
    {
        $id = $this->publish(); $ticket = $this->ticket();
        $this->getJson('/api/v1/sla-management/options')->assertOk();
        $this->getJson('/api/v1/sla-management/reports')->assertOk();
        $this->getJson('/api/v1/sla-management/monitoring')->assertOk();
        $this->postJson('/api/v1/sla-management/preview', $this->config())->assertOk();
        $this->publish(['code' => 'OTHER']);
        Sanctum::actingAs($this->agent);
        $this->postJson('/api/v1/sla-management/policies', $this->config())->assertForbidden();
        $this->getJson("/api/v1/sla-management/tickets/{$ticket->id}")->assertForbidden();
        DB::table('organizations')->insert(['id' => 2, 'public_id' => (string) Str::uuid(), 'name' => 'Other', 'code' => 'OTHER']);
        $this->admin->organization_id = 2; $this->admin->save(); Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/sla-management/policies')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/sla-management/policies/{$id}/archive")->assertNotFound();
        $this->getJson("/api/v1/sla-management/tickets/{$ticket->id}")->assertNotFound();
    }
}
