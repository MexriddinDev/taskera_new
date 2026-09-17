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
    /** Asosiy XIZMAT guruhlari — respublika, hududga biriktirilmagan. */
    private Team $tech;
    private Team $noc;
    private Team $bi;
    private int $andijon;
    private int $tashkent;
    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TicketCreated::class]);
        $this->seed(ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'code' => 'REGION-TEST', 'name' => 'Test']);
        // Asosiy xizmat guruhlari seederdan OLDIN yaratiladi: seeder har
        // hududga aynan shular uchun "Default holat" muddatini tayyorlaydi.
        $this->tech = Team::create(['organization_id' => 1, 'code' => 'TECH-SUP', 'name' => 'Texnik guruh']);
        $this->noc = Team::create(['organization_id' => 1, 'code' => 'NOC', 'name' => 'NOC monitoring']);
        $this->bi = Team::create(['organization_id' => 1, 'code' => 'BI', 'name' => 'BI hisobotlari', 'republic_only' => true]);
        $this->seed(RegionalSupportSeeder::class);
        $this->andijon = (int) DB::table('regions')->where('name', 'Andijon viloyati')->value('id');
        $this->tashkent = (int) DB::table('regions')->where('name', 'Toshkent shahri')->value('id');
        $this->branchId = DB::table('branches')->insertGetId(['organization_id' => 1, 'public_id' => (string) Str::uuid(), 'region_id' => $this->andijon, 'code' => 'HQ', 'name' => 'HQ']);
        DB::table('departments')->insert(['id' => 1, 'organization_id' => 1, 'public_id' => (string) Str::uuid(), 'code' => 'IT', 'name' => 'IT']);
        DB::table('positions')->insert(['id' => 1, 'organization_id' => 1, 'public_id' => (string) Str::uuid(), 'code' => 'STAFF', 'name' => 'Staff']);
        $this->admin = $this->user('superadmin');
        $this->requester = $this->user('requester', '09006', '00001');
        $this->support = $this->user('andijon_it1', '09006', '00001');
        $this->otherSupport = $this->user('andijon_it2', '09006', '00002');
        $this->central = $this->user('central');
        // BXM 09006 — Andijon; aniq local kod 00002 esa Toshkent shahri.
        $this->route('09006', '', $this->andijon);
        $this->route('09006', '00002', $this->tashkent);
        $this->regionalRole($this->support);
        $this->regionalRole($this->otherSupport);
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

    /** Ofisni HUDUDGA biriktiradi; IT guruhi ixtiyoriy. */
    private function route(string $bxm, string $local, int $regionId, ?Team $team = null): int
    {
        return DB::table('office_support_routes')->insertGetId(['organization_id' => 1, 'bxm_code' => $bxm, 'local_code' => $local,
            'name' => 'Office', 'team_id' => $team?->id, 'region_id' => $regionId]);
    }

    /**
     * Xodimga viloyat roli beriladi. Qaysi hududga tegishli ekani uning O'Z
     * BXM kodidan chiqadi — viloyat guruhiga a'zolik endi shart emas.
     */
    private function regionalRole(User $user): void
    {
        $roleId = DB::table('roles')->where('organization_id', 1)->where('name', 'Regional Support')->value('id');
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id, 'organization_id' => 1]);
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
        // Seeder GURUH yaratmaydi — faqat hudud muddatlarini tayyorlaydi.
        $this->assertSame(0, DB::table('teams')->whereNotNull('region_id')->count(),
            'Viloyatga avtomatik IT guruhi ochilmasligi kerak.');
        // Har hududga har XIZMAT guruhi uchun bitta muddat: 14 × 2 (BI chetda).
        $this->assertSame(28, DB::table('sla_rules')->whereNotNull('region_id')->where('is_default', true)->count());
        $this->assertSame(2, DB::table('roles')->whereIn('name', ['Regional Support', 'Regional Admin'])->count());
    }

    /**
     * Har viloyat respublika qoidalarining 1:1 nusxasi bilan boshlanadi.
     *
     * Nusxasiz viloyatda faqat "Default holat" turardi va zayavkada shablon
     * tanlab bo'lmasdi. BI chetda qoladi: uning zayavkasi doim respublikaga
     * boradi, ya'ni viloyat nusxasi hech qachon tanlanmasdi.
     */
    public function test_republic_templates_are_copied_to_every_region(): void
    {
        // Respublikada ikkita shablon; BI ga ham bittasini qo'shamiz.
        foreach ([['Printer', $this->tech->id], ['Tarmoq', $this->tech->id], ['Hisobot', $this->bi->id]] as [$name, $teamId]) {
            SlaRule::create(['organization_id' => 1, 'team_id' => $teamId, 'name' => $name,
                'accept_minutes' => 10, 'work_minutes' => 20, 'is_active' => true, 'is_default' => false]);
        }

        $this->seed(RegionalSupportSeeder::class);

        $regionIds = DB::table('regions')->pluck('id');
        foreach ($regionIds as $regionId) {
            $names = DB::table('sla_rules')->where('team_id', $this->tech->id)->where('region_id', $regionId)
                ->where('is_default', false)->pluck('name')->sort()->values()->all();
            $this->assertSame(['Printer', 'Tarmoq'], $names, "Hudud {$regionId} da respublika shablonlari bo'lishi kerak.");
        }

        // BI faqat respublikada qoladi.
        $this->assertSame(0, DB::table('sla_rules')->where('team_id', $this->bi->id)->whereNotNull('region_id')->count());

        // Takror ishga tushirish nusxani ikkilantirmaydi.
        $before = DB::table('sla_rules')->whereNotNull('region_id')->count();
        $this->seed(RegionalSupportSeeder::class);
        $this->assertSame($before, DB::table('sla_rules')->whereNotNull('region_id')->count());
    }

    public function test_bxm_default_and_exact_local_code_select_different_regions(): void
    {
        $first = $this->ticket(null, $this->tech);
        $second = $this->ticket($this->otherSupport, $this->tech);
        $this->assertSame('09006', $first->bxm_code);
        $this->assertSame('00001', $first->local_code);
        // Bir xil BXM, turli local kod — turli HUDUD.
        $this->assertSame($this->andijon, (int) $first->region_id);
        $this->assertSame($this->tashkent, (int) $second->region_id);
        // Tanlangan XIZMAT guruhi ikkalasida ham o'zgarmaydi.
        $this->assertEquals($this->tech->id, $first->assigned_team_id);
        $this->assertEquals($this->tech->id, $second->assigned_team_id);
        $this->assertSame('regional', $first->support_scope);
        $this->requester->employee->update(['bxm_code' => '77777', 'local_code' => '9']);
        $this->assertSame('09006', $first->fresh()->bxm_code, 'Historical ticket codes must not follow employee changes.');
    }

    public function test_web_create_stores_server_identity_and_ignores_forged_codes(): void
    {
        Sanctum::actingAs($this->requester);
        $response = $this->postJson('/api/v1/tickets', ['todo' => 'Printer muammosi', 'teamId' => $this->tech->id,
            'bxm_code' => 'FAKE', 'local_code' => '00002', 'region_id' => 999]);
        $response->assertCreated()->assertJsonPath('bxmCode', '09006')->assertJsonPath('localCode', '00001')->assertJsonPath('supportScope', 'regional');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.bxmCode', '09006');
    }

    public function test_unknown_code_is_saved_for_superadmin_and_not_sent_to_city_staff(): void
    {
        $unknown = $this->user('unknown', '99999', '1');
        $ticket = $this->ticket($unknown, $this->tech);
        $this->assertSame('unmapped', $ticket->support_scope);
        // Guruh SAQLANADI: qaysi xizmat so'ralgani superadminga kerak.
        $this->assertEquals($this->tech->id, $ticket->assigned_team_id);
        $this->assertFalse(RegionalRouting::canWork($this->central, $ticket));
        $this->assertFalse(RegionalRouting::canWork($this->support, $ticket));
        Sanctum::actingAs($unknown);
        $this->getJson('/api/v1/tickets/'.$ticket->id)->assertOk();
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/regional-support')->assertOk()->assertJsonPath('unmapped_count', 1);
        $this->postJson('/api/v1/regional-support/tickets/'.$ticket->id.'/reroute')->assertUnprocessable();
        $this->route('99999', '', $this->andijon);
        $this->postJson('/api/v1/regional-support/tickets/'.$ticket->id.'/reroute')->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'support_scope' => 'regional', 'bxm_code' => '99999']);
        $this->postJson('/api/v1/regional-support/tickets/'.$ticket->id.'/reroute')->assertUnprocessable();
    }

    public function test_bi_requests_stay_at_republic_with_origin_bxm_and_region(): void
    {
        $ticket = $this->ticket($this->requester, $this->bi);
        $this->assertSame('republic', $ticket->support_scope);
        $this->assertEquals($this->bi->id, $ticket->assigned_team_id);
        $this->assertEquals($this->andijon, (int) $ticket->region_id);
        $this->assertTrue(RegionalRouting::canWork($this->central, $ticket));
        $this->assertFalse(RegionalRouting::canWork($this->support, $ticket));
        // Viloyatdagi xodim ham ASOSIY xizmat guruhlarini ko'radi — zayavkada
        // qaysi xizmat kerakligini o'zi tanlaydi.
        Sanctum::actingAs($this->requester);
        $names = $this->getJson('/api/v1/teams')->assertOk()->json('data.*.name');
        $this->assertContains('Texnik guruh', $names);
        $this->assertContains('NOC monitoring', $names);
    }

    public function test_other_office_staff_and_city_staff_cannot_read_take_or_comment(): void
    {
        $ticket = $this->ticket(null, $this->tech);
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
        // Har viloyat o'z muddatiga ega: bitta "Texnik guruh" uchun Andijon
        // boshqa, Toshkent shahri boshqa muddat oladi.
        $rule = SlaRule::ensureDefaultFor(1, $this->tech->id, $this->andijon);
        $rule->update(['accept_minutes' => 60, 'work_minutes' => 240]);
        SlaRule::ensureDefaultFor(1, $this->tech->id, $this->tashkent)->update(['accept_minutes' => 25, 'work_minutes' => 50]);
        SlaRule::ensureDefaultFor(1, $this->bi->id)->update(['accept_minutes' => 5, 'work_minutes' => 20]);
        TicketSlaService::forgetRules();
        $sla = app(TicketSlaService::class);
        $this->assertSame(60, $sla->forTicket($this->ticket(null, $this->tech))[0]['minutes']);
        $this->assertSame(25, $sla->forTicket($this->ticket($this->otherSupport, $this->tech))[0]['minutes']);
        $this->assertSame(5, $sla->forTicket($this->ticket(null, $this->bi))[0]['minutes']);
        Sanctum::actingAs($this->support);
        $this->putJson('/api/v1/sla-rules/'.$rule->id, ['accept_minutes' => 1, 'work_minutes' => 1])->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/sla-rules/'.$rule->id, ['accept_minutes' => 90, 'work_minutes' => 360])->assertOk();
    }

    /**
     * Zayavka formasida shablon ro'yxati takrorlanmaydi: so'rovchi o'z
     * hududinikini ko'radi, hududda shablon bo'lmasa — respublikanikini.
     */
    public function test_ticket_form_lists_only_the_requesters_region_templates(): void
    {
        $template = fn (string $name, ?int $regionId, ?Team $team = null) => SlaRule::create([
            'organization_id' => 1, 'team_id' => ($team ?? $this->tech)->id, 'region_id' => $regionId, 'name' => $name,
            'accept_minutes' => 10, 'work_minutes' => 20, 'is_active' => true, 'is_default' => false]);
        $template('Printer', null);
        $template('Printer', $this->tashkent);
        $template('Andijon printer', $this->andijon);
        $template('Hisobot', null, $this->bi);
        $names = fn (Team $team) => collect($this->getJson('/api/v1/sla-rules?for_ticket=1&is_active=1&team_id='.$team->id)
            ->assertOk()->json('data'))->where('is_default', false)->pluck('name')->all();

        // Andijon so'rovchisi — faqat Andijon shabloni, respublika nusxasi emas.
        Sanctum::actingAs($this->requester);
        $this->assertSame(['Andijon printer'], $names($this->tech));
        // BI doim respublikaniki.
        $this->assertSame(['Hisobot'], $names($this->bi));

        // Hududi yo'q xodim — respublika shablonlari.
        Sanctum::actingAs($this->central);
        $this->assertSame(['Printer'], $names($this->tech));

        // Hududida shablon yo'q bo'lsa — respublikaga tushadi.
        SlaRule::where('region_id', $this->andijon)->where('is_default', false)->delete();
        Sanctum::actingAs($this->requester);
        $this->assertSame(['Printer'], $names($this->tech));
    }

    public function test_copy_from_republic_adds_only_missing_templates_and_keeps_regional_edits(): void
    {
        foreach (['Printer', 'Tarmoq'] as $name) {
            SlaRule::create(['organization_id' => 1, 'team_id' => $this->tech->id, 'name' => $name,
                'description' => 'Respublika matni', 'accept_minutes' => 10, 'work_minutes' => 20, 'is_active' => true, 'is_default' => false]);
        }
        // Andijon "Printer"ni o'zicha yozgan — u saqlanib qolishi kerak.
        SlaRule::create(['organization_id' => 1, 'team_id' => $this->tech->id, 'region_id' => $this->andijon, 'name' => 'Printer',
            'description' => 'Andijon matni', 'accept_minutes' => 5, 'work_minutes' => 7, 'is_active' => true, 'is_default' => false]);

        Sanctum::actingAs($this->support);
        $this->postJson('/api/v1/sla-rules/copy-from-republic', ['region_id' => $this->andijon])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/sla-rules/copy-from-republic', ['region_id' => $this->andijon])
            ->assertOk()->assertJsonPath('data.copied', 1);
        $this->postJson('/api/v1/sla-rules/copy-from-republic', ['region_id' => $this->andijon])
            ->assertOk()->assertJsonPath('data.copied', 0);

        $andijon = SlaRule::where('team_id', $this->tech->id)->where('region_id', $this->andijon)->where('is_default', false)
            ->orderBy('name')->get();
        $this->assertSame(['Printer', 'Tarmoq'], $andijon->pluck('name')->all());
        $this->assertSame('Andijon matni', $andijon[0]->description);
        $this->assertSame(5, $andijon[0]->accept_minutes);
        // Boshqa viloyatga tegilmaydi.
        $this->assertSame(0, SlaRule::where('region_id', $this->tashkent)->where('is_default', false)->count());
    }

    public function test_admin_config_validation_and_membership_are_scoped(): void
    {
        Sanctum::actingAs($this->support);
        $this->getJson('/api/v1/regional-support')->assertForbidden();
        $this->postJson('/api/v1/regional-support/initialize')->assertForbidden();
        Sanctum::actingAs($this->admin);
        // Bir xil BXM + local kod ikki marta biriktirilmaydi.
        $this->postJson('/api/v1/regional-support/routes', ['bxm_code' => '09006', 'local_code' => '00002', 'name' => 'Duplicate', 'region_id' => $this->andijon])->assertUnprocessable();
        // Bitta BXM turli hududlarga bo'linmaydi.
        $buxoro = (int) DB::table('regions')->where('name', 'Buxoro viloyati')->value('id');
        $this->postJson('/api/v1/regional-support/routes', ['bxm_code' => '09006', 'local_code' => '99', 'name' => 'Wrong region', 'region_id' => $buxoro])->assertUnprocessable();
        // IT guruhi ixtiyoriy — faqat hudud bilan ham biriktiriladi.
        $this->postJson('/api/v1/regional-support/routes', ['bxm_code' => '11111', 'local_code' => '', 'name' => 'Faqat hudud', 'region_id' => $this->andijon])->assertOk();
        // Qo'lda ochilgan viloyat guruhiga a'zolik beriladi.
        $manual = Team::create(['organization_id' => 1, 'code' => 'AN-IT-QO', 'name' => 'Andijon IT (qo\'lda)', 'region_id' => $this->andijon]);
        $this->postJson('/api/v1/regional-support/members', ['user_id' => $this->support->id, 'team_id' => $manual->id, 'role' => 'Regional Admin'])->assertOk();
        $this->assertTrue($this->support->fresh()->canAssignTickets());
    }

    public function test_revoked_membership_and_changed_bxm_do_not_grant_republic_access(): void
    {
        $ticket = $this->ticket(null, $this->tech);
        $this->assertTrue(RegionalRouting::canWork($this->support, $ticket));
        // BXM kodi o'zgarsa hudud yo'qoladi — va u bilan birga ruxsat ham.
        $this->support->employee->update(['bxm_code' => 'UNKNOWN']);
        $this->assertFalse(RegionalRouting::canWork($this->support->fresh(), $ticket));
        // Viloyat xodimi respublika zayavkasiga baribir o'tib keta olmaydi.
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
        $notifier->sendToStaff(1, 'Regional test', null, $this->ticket(null, $this->tech));
        $this->assertSame([$this->support->id], $notifier->recipients);
    }

    public function test_bot_crafted_ticket_callback_cannot_read_another_team_ticket(): void
    {
        $ticket = $this->ticket(null, $this->tech);
        $bot = new BotConversationService($this->mock(TelegramApiClient::class));
        $method = new \ReflectionMethod($bot, 'fetchTicket');
        $this->assertNull($method->invoke($bot, $ticket->id, $this->otherSupport));
        $this->assertNotNull($method->invoke($bot, $ticket->id, $this->support));
        $this->assertNotNull($method->invoke($bot, $ticket->id, $this->requester));
    }

    public function test_region_statistics_do_not_include_other_teams(): void
    {
        $this->ticket(null, $this->tech);
        $this->ticket($this->otherSupport, $this->tech);
        $this->ticket(null, $this->bi);
        Sanctum::actingAs($this->support);
        $this->getJson('/api/v1/regional-support/stats')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.total', 1);
        Sanctum::actingAs($this->central);
        $this->getJson('/api/v1/regional-support/stats')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.scope', 'republic');
    }
}
