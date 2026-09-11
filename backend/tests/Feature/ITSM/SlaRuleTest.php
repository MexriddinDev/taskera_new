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

        // Zayavka yaratish oqimi 1-departamentga tayanadi (employee kartochkasi
        // yo'q foydalanuvchi uchun zaxira qiymat) — FK buzilmasligi uchun kerak.
        $regionId = DB::table('regions')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'code' => 'TSH',
            'name' => 'Toshkent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'region_id' => $regionId,
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'code' => 'HQ',
            'name' => 'Bosh ofis',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('departments')->insert([
            'id' => 1,
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'branch_id' => $branchId,
            'code' => 'IT',
            'name' => 'IT departament',
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

        // Bitta guruhga ikkinchi qoida — cheklov yo'q.
        $this->postJson('/api/v1/sla-rules', [
            'team_id' => $this->teamId,
            'name' => 'Ikkinchi qoida',
            'accept_minutes' => 10,
            'work_minutes' => 20,
        ])->assertCreated();

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

        // Qoida passiv qilinsa zayavka SLA siz qolmaydi — tizim standarti
        // (15 / 30 daqiqa) qo'llanadi.
        DB::table('sla_rules')->where('team_id', $this->teamId)->update(['is_active' => false]);
        TicketSlaService::forgetRules();
        $fallback = app(TicketSlaService::class)->forTicket($ticket);
        $this->assertSame('Standart', $fallback[0]['slaName']);
        $this->assertSame(15, $fallback[0]['minutes']);
        $this->assertSame(30, $fallback[1]['minutes']);
    }

    public function test_team_can_have_several_rules_split_by_priority(): void
    {
        Sanctum::actingAs($this->admin);

        // Umumiy qoida (barcha muhimliklar uchun).
        $this->postJson('/api/v1/sla-rules', [
            'team_id' => $this->teamId,
            'name' => 'Umumiy',
            'accept_minutes' => 30,
            'work_minutes' => 120,
        ])->assertCreated()->assertJsonPath('data.priority_id', null);

        // Shu guruhga kritik muhimlik uchun ALOHIDA qoida — ruxsat etiladi.
        $this->postJson('/api/v1/sla-rules', [
            'team_id' => $this->teamId,
            'priority_id' => 1,
            'name' => 'Kritik',
            'accept_minutes' => 5,
            'work_minutes' => 20,
        ])->assertCreated()->assertJsonPath('data.priority.name', 'Kritik');

        // Ayni muhimlik uchun ikkinchi qoida ham mumkin.
        $this->postJson('/api/v1/sla-rules', [
            'team_id' => $this->teamId,
            'priority_id' => 1,
            'name' => 'Kritik — tezkor xizmat',
            'accept_minutes' => 7,
            'work_minutes' => 25,
        ])->assertCreated();

        $this->getJson("/api/v1/sla-rules?team_id={$this->teamId}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_selected_template_defines_the_sla_and_default_applies_without_one(): void
    {
        $this->travelTo('2026-09-08 10:00:00');

        $generalId = $this->rule('Umumiy', null, 30, 120);
        $criticalId = $this->rule('E-Imzo o‘rnatish', 1, 5, 20);
        TicketSlaService::forgetRules();

        // Shablon tanlangan — aynan o'sha qoidaning muddatlari.
        $withTemplate = $this->ticket(3, $criticalId);
        $sla = app(TicketSlaService::class)->forTicket($withTemplate);
        $this->assertSame('E-Imzo o‘rnatish', $sla[0]['slaName']);
        $this->assertSame(5, $sla[0]['minutes']);
        $this->assertSame(20, $sla[1]['minutes']);

        // Shablon tanlanmagan — default holat: guruhning umumiy qoidasi.
        $withoutTemplate = $this->ticket(1);
        $sla = app(TicketSlaService::class)->forTicket($withoutTemplate);
        $this->assertSame('Umumiy', $sla[0]['slaName']);
        $this->assertSame(30, $sla[0]['minutes']);
        $this->assertSame($generalId, $sla[0]['slaId']);
    }

    public function test_system_default_applies_when_team_has_no_general_rule(): void
    {
        $this->travelTo('2026-09-08 10:00:00');

        // Guruhda faqat muhimlikka bog'langan qoida bor — umumiysi yo'q.
        $this->rule('Kritik', 1, 5, 20);
        TicketSlaService::forgetRules();

        $sla = app(TicketSlaService::class)->forTicket($this->ticket(3));
        $this->assertSame('Standart', $sla[0]['slaName']);
        $this->assertSame(15, $sla[0]['minutes']);
        $this->assertSame(30, $sla[1]['minutes']);
    }

    public function test_strictest_rule_wins_when_several_match(): void
    {
        $this->travelTo('2026-09-08 10:00:00');

        // Guruhda uchta umumiy qoida — shablon tanlanmagan zayavkaga ular
        // ichidan eng qisqa muddatlisi qo'llanadi.
        $this->rule('Antivirus o‘rnatish', null, 30, 90);
        $this->rule('E-Imzo o‘rnatish', null, 10, 40);
        $this->rule('Tarmoq uzilishi', null, 20, 30);
        TicketSlaService::forgetRules();

        $sla = app(TicketSlaService::class)->forTicket($this->ticket(1));

        $this->assertSame('E-Imzo o‘rnatish', $sla[0]['slaName']);
        $this->assertSame(10, $sla[0]['minutes']);
        $this->assertSame(40, $sla[1]['minutes']);
    }

    public function test_new_ticket_priority_comes_from_the_selected_rule(): void
    {
        Sanctum::actingAs($this->regular);
        $criticalRuleId = $this->rule('E-Imzo o‘rnatish', 1, 5, 20);

        // Shablon tanlangan — muhimlik o'sha qoidaniki (Kritik).
        $this->postJson('/api/v1/tickets', [
            'todo' => 'E-Imzo ishlamayapti',
            'teamId' => $this->teamId,
            'slaRuleId' => $criticalRuleId,
            // Murojaatchi yuborgan muhimlik E'TIBORGA OLINMAYDI.
            'priority' => 'low',
        ])->assertCreated();

        $this->assertSame(1, (int) DB::table('tickets')->latest('id')->value('priority_id'));

        // Shablonsiz zayavka — "O'rta".
        $this->postJson('/api/v1/tickets', [
            'todo' => 'Kompyuter yonmayapti',
            'teamId' => $this->teamId,
            'priority' => 'high',
        ])->assertCreated();

        $this->assertSame(3, (int) DB::table('tickets')->latest('id')->value('priority_id'));
    }

    /**
     * Ish vaqtidan tashqari kelgan zayavkada QABUL bosqichi kuzatilmaydi.
     *
     * Ilgari 15 daqiqalik qabul muddati astronomik vaqtda yurardi: kechqurun
     * 19:00 da kelgan zayavka ertalab xodim ishga kelgunicha allaqachon
     * "buzilgan" bo'lib turardi. Qo'yilmagan navbatni buzilgan deb sanash
     * SLA foizini soxta pasaytirardi.
     */
    public function test_accept_stage_is_not_tracked_outside_work_hours(): void
    {
        $this->rule('Standart qoida', null, 15, 30);
        TicketSlaService::forgetRules();

        // Payshanba 19:30 (Toshkent) — ish kuni tugagan.
        $ticket = $this->ticketCreatedAt('2026-09-10 19:30');
        $sla = app(TicketSlaService::class)->forTicket($ticket);

        $this->assertSame('accept', $sla[0]['key']);
        $this->assertSame('OFF_HOURS', $sla[0]['status']);
        $this->assertNull($sla[0]['dueAt']);
        $this->assertSame(0, $sla[0]['overdueMinutes']);
    }

    public function test_accept_stage_is_not_tracked_before_the_work_day_starts(): void
    {
        $this->rule('Standart qoida', null, 15, 30);
        TicketSlaService::forgetRules();

        // Payshanba 07:40 — hali ochilmagan.
        $sla = app(TicketSlaService::class)->forTicket($this->ticketCreatedAt('2026-09-10 07:40'));

        $this->assertSame('OFF_HOURS', $sla[0]['status']);
    }

    public function test_accept_stage_is_not_tracked_on_the_weekend(): void
    {
        $this->rule('Standart qoida', null, 15, 30);
        TicketSlaService::forgetRules();

        // Shanba 11:00 — soat ish vaqtida, lekin kun dam olish kuni. Sana
        // o'tmishda: astronomik hisobda bu allaqachon BREACHED bo'lardi.
        $sla = app(TicketSlaService::class)->forTicket($this->ticketCreatedAt('2026-09-05 11:00'));

        $this->assertSame('OFF_HOURS', $sla[0]['status']);
    }

    public function test_accept_stage_is_still_tracked_during_work_hours(): void
    {
        $this->rule('Standart qoida', null, 15, 30);
        TicketSlaService::forgetRules();

        // Payshanba 10:00 — ish vaqti; qabul qilinmagan va muddat o'tgan.
        $sla = app(TicketSlaService::class)->forTicket($this->ticketCreatedAt('2026-09-10 10:00'));

        $this->assertSame('BREACHED', $sla[0]['status']);
        $this->assertNotNull($sla[0]['dueAt']);
    }

    public function test_off_hours_ticket_is_not_counted_as_breached_in_team_stats(): void
    {
        $this->rule('Standart qoida', null, 15, 30);
        TicketSlaService::forgetRules();

        $offHours = $this->ticketCreatedAt('2026-09-10 19:30');
        $inHours = $this->ticketCreatedAt('2026-09-10 10:00');

        $stats = app(TicketSlaService::class)->breachStatsByTeam([$offHours, $inHours]);

        $this->assertSame(2, $stats[$this->teamId]['tracked']);
        $this->assertSame(1, $stats[$this->teamId]['acceptBreached']);
        $this->assertSame(50.0, $stats[$this->teamId]['compliancePercent']);
    }

    /** Berilgan Toshkent vaqtida yaratilgan, hali qabul qilinmagan zayavka. */
    private function ticketCreatedAt(string $tashkentTime): Ticket
    {
        // Bazada vaqtlar UTC'da turadi (app.timezone). Toshkent vaqtini shu
        // yerda UTC'ga o'giramiz — aks holda Eloquent uni zonasiz yozib,
        // test tasodifan to'g'ri/noto'g'ri natija berardi.
        $createdAt = \Illuminate\Support\Carbon::parse($tashkentTime, 'Asia/Tashkent')->utc();

        return Ticket::create([
            'organization_id' => 1,
            'ticket_no' => 'T-'.Str::random(6),
            'ticket_type' => 'INCIDENT',
            'subject' => 'Printer ishlamayapti',
            'description' => 'Printer ishlamayapti',
            'status_id' => 1,
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $this->regular->id,
            'assigned_team_id' => $this->teamId,
            'started_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function rule(string $name, ?int $priorityId, int $accept, int $work): int
    {
        return DB::table('sla_rules')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'team_id' => $this->teamId,
            'priority_id' => $priorityId,
            'name' => $name,
            'accept_minutes' => $accept,
            'work_minutes' => $work,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ticket(int $priorityId, ?int $slaRuleId = null): Ticket
    {
        return Ticket::create([
            'sla_rule_id' => $slaRuleId,
            'organization_id' => 1,
            'ticket_no' => 'T-'.Str::random(6),
            'ticket_type' => 'INCIDENT',
            'subject' => 'Printer ishlamayapti',
            'description' => 'Printer ishlamayapti',
            'status_id' => 4,
            'priority_id' => $priorityId,
            'source_id' => 1,
            'requester_user_id' => $this->regular->id,
            'assigned_team_id' => $this->teamId,
            'started_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(3),
            'updated_at' => now(),
        ]);
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
