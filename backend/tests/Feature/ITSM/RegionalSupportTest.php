<?php

declare(strict_types=1);

namespace Tests\Feature\ITSM;

use App\Models\User;
use App\Models\Itms\SlaRule;
use App\Modules\Organization\Infrastructure\Eloquent\Employee;
use App\Modules\Organization\Infrastructure\Eloquent\Team;
use App\Modules\Telegram\Infrastructure\Integrations\TelegramApiClient;
use App\Modules\Telegram\Infrastructure\Services\BotConversationService;
use App\Modules\Telegram\Infrastructure\Services\TelegramNotifierService;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Services\TicketSlaService;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Support\RegionalRouting;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RegionalSupportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RegionalSupportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $requester;
    private User $support;
    private User $otherSupport;
    private User $central;
    private Team $firstTeam;
    private Team $secondTeam;
    private Team $bi;
    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TicketCreated::class]);
        $this->seed(ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'code' => 'REGION-TEST', 'name' => 'Test']);
        $this->seed(RegionalSupportSeeder::class);
        $this->firstTeam = Team::where('code', 'UZ-AN-IT-1')->firstOrFail();
        $this->secondTeam = Team::create(['organization_id' => 1, 'code' => 'AN-IT-2', 'name' => 'Andijon IT 2', 'region_id' => $this->firstTeam->region_id]);
        $this->bi = Team::create(['organization_id' => 1, 'code' => 'BI', 'name' => 'BI hisobotlari', 'republic_only' => true]);
        $this->branchId = DB::table('branches')->insertGetId(['organization_id' => 1, 'public_id' => (string) Str::uuid(), 'region_id' => $this->firstTeam->region_id, 'code' => 'HQ', 'name' => 'HQ']);
        DB::table('departments')->insert(['id' => 1, 'organization_id' => 1, 'public_id' => (string) Str::uuid(), 'code' => 'IT', 'name' => 'IT']);
        DB::table('positions')->insert(['id' => 1, 'organization_id' => 1, 'public_id' => (string) Str::uuid(), 'code' => 'STAFF', 'name' => 'Staff']);
        $this->admin = $this->user('superadmin');
        $this->requester = $this->user('requester', '09006', '00001');
        $this->support = $this->user('andijon_it1', '09006', '00001');
        $this->otherSupport = $this->user('andijon_it2', '09006', '00002');
        $this->central = $this->user('central');
        $this->route('09006', '', $this->firstTeam);
        $this->route('09006', '00002', $this->secondTeam);
        $this->member($this->support, $this->firstTeam);
        $this->member($this->otherSupport, $this->secondTeam);
        $permission = DB::table('permissions')->where('name', 'tickets.transition')->value('id');
        DB::table('model_has_permissions')->insert(['model_id' => $this->central->id, 'model_type' => User::class, 'organization_id' => 1, 'permission_id' => $permission]);
        $this->mock(\App\Services\AdAuthService::class)->shouldReceive('lookupByUsername')->andReturn(null);
        TicketSlaService::forgetRules();
    }

    private function user(string $name, ?string $bxm = null, ?string $local = null): User
    {
        $employee = Employee::create(['organization_id' => 1, 'employee_no' => $name, 'first_name' => $name, 'last_name' => 'Test',
            'department_id' => 1, 'branch_id' => $this->branchId, 'position_id' => 1, 'employment_status_id' => 1,
            'bxm_code' => $bxm, 'local_code' => $local]);
        return User::create(['organization_id' => 1, 'public_id' => (string) Str::uuid(), 'username' => $name, 'employee_id' => $employee->id,
            'email' => $name.'@example.test', 'auth_source' => 'LOCAL', 'status' => 'ACTIVE']);
    }

    private function route(string $bxm, string $local, Team $team): int
    {
        return DB::table('office_support_routes')->insertGetId(['organization_id' => 1, 'bxm_code' => $bxm, 'local_code' => $local,
            'name' => 'Office', 'team_id' => $team->id, 'region_id' => $team->region_id]);
    }

    private function member(User $user, Team $team): void
    {
        $roleId = DB::table('roles')->where('organization_id', 1)->where('name', 'Regional Support')->value('id');
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id, 'organization_id' => 1]);
        DB::table('team_members')->insert(['team_id' => $team->id, 'user_id' => $user->id, 'joined_at' => now()]);
    }

    private function ticket(?User $owner = null, ?Team $team = null): Ticket
    {
        return Ticket::create(['organization_id' => 1, 'ticket_no' => 'REG-'.Str::random(10), 'ticket_type' => 'INCIDENT',
            'subject' => 'Printer ishlamayapti', 'description' => 'Printer ishlamayapti', 'status_id' => 1, 'priority_id' => 3, 'source_id' => 1,
            'requester_user_id' => ($owner ?? $this->requester)->id, 'assigned_team_id' => $team?->id, 'department_id' => 1]);
    }

    public function test_fourteen_regions_have_separate_default_sla_and_initialization_is_idempotent(): void
    {
        $this->seed(RegionalSupportSeeder::class);
        $this->assertSame(14, DB::table('regions')->count());
        $this->assertSame(14, DB::table('sla_rules')->where('is_default', true)->count());
        $this->assertSame(2, DB::table('roles')->whereIn('name', ['Regional Support', 'Regional Admin'])->count());
    }

    public function test_bxm_default_and_exact_local_code_select_different_it_teams(): void
    {
        $first = $this->ticket();
        $second = $this->ticket($this->otherSupport);
        $this->assertSame('09006', $first->bxm_code);
        $this->assertSame('00001', $first->local_code);
        $this->assertEquals($this->firstTeam->id, $first->assigned_team_id);
        $this->assertEquals($this->secondTeam->id, $second->assigned_team_id);
        $this->assertSame('regional', $first->support_scope);
        $this->requester->employee->update(['bxm_code' => '77777', 'local_code' => '9']);
        $this->assertSame('09006', $first->fresh()->bxm_code, 'Historical ticket codes must not follow employee changes.');
    }

    public function test_web_create_stores_server_identity_and_ignores_forged_codes(): void
    {
        Sanctum::actingAs($this->requester);
        $response = $this->postJson('/api/v1/tickets', ['todo' => 'Printer muammosi', 'teamId' => $this->firstTeam->id,
            'bxm_code' => 'FAKE', 'local_code' => '00002', 'region_id' => 999]);
        $response->assertCreated()->assertJsonPath('bxmCode', '09006')->assertJsonPath('localCode', '00001')->assertJsonPath('supportScope', 'regional');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.bxmCode', '09006');
    }

    public function test_unknown_code_is_saved_for_superadmin_and_not_sent_to_city_staff(): void
    {
        $unknown = $this->user('unknown', '99999', '1');
        $ticket = $this->ticket($unknown);
        $this->assertSame('unmapped', $ticket->support_scope);
        $this->assertNull($ticket->assigned_team_id);
        $this->assertFalse(RegionalRouting::canWork($this->central, $ticket));
        $this->assertFalse(RegionalRouting::canWork($this->support, $ticket));
        Sanctum::actingAs($unknown);
        $this->getJson('/api/v1/tickets/'.$ticket->id)->assertOk();
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/regional-support')->assertOk()->assertJsonPath('unmapped_count', 1);
        $this->postJson('/api/v1/regional-support/tickets/'.$ticket->id.'/reroute')->assertUnprocessable();
        $this->route('99999', '', $this->firstTeam);
        $this->postJson('/api/v1/regional-support/tickets/'.$ticket->id.'/reroute')->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'support_scope' => 'regional', 'bxm_code' => '99999']);
        $this->postJson('/api/v1/regional-support/tickets/'.$ticket->id.'/reroute')->assertUnprocessable();
    }

    public function test_bi_requests_stay_at_republic_with_origin_bxm_and_region(): void
    {
        $ticket = $this->ticket($this->requester, $this->bi);
        $this->assertSame('republic', $ticket->support_scope);
        $this->assertEquals($this->bi->id, $ticket->assigned_team_id);
        $this->assertEquals($this->firstTeam->region_id, $ticket->region_id);
        $this->assertTrue(RegionalRouting::canWork($this->central, $ticket));
        $this->assertFalse(RegionalRouting::canWork($this->support, $ticket));
        Sanctum::actingAs($this->requester);
        $this->getJson('/api/v1/teams')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_other_office_staff_and_city_staff_cannot_read_take_or_comment(): void
    {
        $ticket = $this->ticket();
        foreach ([$this->otherSupport, $this->central] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/tickets/'.$ticket->id)->assertNotFound();
            $this->putJson('/api/v1/tickets/'.$ticket->id, ['assignToMe' => true])->assertNotFound();
            $this->getJson('/api/v1/tickets/'.$ticket->id.'/comments')->assertNotFound();
            $this->getJson('/api/v1/tickets')->assertOk()->assertJsonPath('total', 0);
        }
        Sanctum::actingAs($this->support);
        $this->getJson('/api/v1/tickets/'.$ticket->id)->assertOk();
        $this->getJson('/api/v1/tickets')->assertOk()->assertJsonPath('total', 1);
        $this->putJson('/api/v1/tickets/'.$ticket->id, ['assignToMe' => true])->assertOk();
    }

    public function test_separate_sla_standards_are_selected_and_only_superadmin_can_change_regional_rules(): void
    {
        $rule = SlaRule::ensureDefaultFor(1, $this->firstTeam->id);
        $rule->update(['accept_minutes' => 60, 'work_minutes' => 240]);
        SlaRule::ensureDefaultFor(1, $this->bi->id)->update(['accept_minutes' => 5, 'work_minutes' => 20]);
        TicketSlaService::forgetRules();
        $this->assertSame(60, app(TicketSlaService::class)->forTicket($this->ticket())[0]['minutes']);
        $this->assertSame(5, app(TicketSlaService::class)->forTicket($this->ticket(null, $this->bi))[0]['minutes']);
        Sanctum::actingAs($this->support);
        $this->putJson('/api/v1/sla-rules/'.$rule->id, ['accept_minutes' => 1, 'work_minutes' => 1])->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/sla-rules/'.$rule->id, ['accept_minutes' => 90, 'work_minutes' => 360])->assertOk();
    }

    public function test_admin_config_validation_and_membership_are_scoped(): void
    {
        Sanctum::actingAs($this->support);
        $this->getJson('/api/v1/regional-support')->assertForbidden();
        $this->postJson('/api/v1/regional-support/initialize')->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/regional-support/routes', ['bxm_code' => '09006', 'local_code' => '00002', 'name' => 'Duplicate', 'team_id' => $this->firstTeam->id])->assertUnprocessable();
        $foreignRegion = Team::where('code', 'UZ-BU-IT-1')->firstOrFail();
        $this->postJson('/api/v1/regional-support/routes', ['bxm_code' => '09006', 'local_code' => '99', 'name' => 'Wrong region', 'team_id' => $foreignRegion->id])->assertUnprocessable();
        $this->postJson('/api/v1/regional-support/members', ['user_id' => $this->support->id, 'team_id' => $foreignRegion->id, 'role' => 'Regional Admin'])->assertUnprocessable();
        $this->postJson('/api/v1/regional-support/members', ['user_id' => $this->support->id, 'team_id' => $this->firstTeam->id, 'role' => 'Regional Admin'])->assertOk();
        $this->assertTrue($this->support->fresh()->canAssignTickets());
    }

    public function test_revoked_membership_and_changed_bxm_do_not_grant_republic_access(): void
    {
        $ticket = $this->ticket();
        $this->assertTrue(RegionalRouting::canWork($this->support, $ticket));
        $this->support->employee->update(['bxm_code' => 'UNKNOWN']);
        $this->assertFalse(RegionalRouting::canWork($this->support->fresh(), $ticket));
        DB::table('team_members')->where('user_id', $this->support->id)->update(['left_at' => now()]);
        $this->assertFalse(RegionalRouting::canWork($this->support->fresh(), $this->ticket(null, $this->bi)));
    }

    public function test_telegram_notification_goes_only_to_the_mapped_it_team(): void
    {
        foreach ([$this->support, $this->otherSupport, $this->central, $this->admin] as $user) {
            DB::table('telegram_accounts')->insert(['organization_id' => 1, 'user_id' => $user->id, 'public_id' => (string) Str::uuid(),
                'telegram_user_id' => (string) $user->id, 'private_chat_id' => (string) $user->id, 'verified_at' => now()]);
        }
        $notifier = new class extends TelegramNotifierService {
            public array $recipients = [];
            public function sendToUser(int $organizationId, int $userId, string $text, ?array $replyMarkup = null): bool
            { $this->recipients[] = $userId; return true; }
        };
        $notifier->sendToStaff(1, 'Regional test', null, $this->ticket());
        $this->assertSame([$this->support->id], $notifier->recipients);
    }

    public function test_bot_crafted_ticket_callback_cannot_read_another_team_ticket(): void
    {
        $ticket = $this->ticket();
        $bot = new BotConversationService($this->mock(TelegramApiClient::class));
        $method = new \ReflectionMethod($bot, 'fetchTicket');
        $this->assertNull($method->invoke($bot, $ticket->id, $this->otherSupport));
        $this->assertNotNull($method->invoke($bot, $ticket->id, $this->support));
        $this->assertNotNull($method->invoke($bot, $ticket->id, $this->requester));
    }

    public function test_region_statistics_do_not_include_other_teams(): void
    {
        $this->ticket();
        $this->ticket($this->otherSupport);
        $this->ticket(null, $this->bi);
        Sanctum::actingAs($this->support);
        $this->getJson('/api/v1/regional-support/stats')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.total', 1);
        Sanctum::actingAs($this->central);
        $this->getJson('/api/v1/regional-support/stats')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.scope', 'republic');
    }
}
