<?php

namespace Tests\Feature\ITSM;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SlaBaselineSeederTest extends TestCase
{
    use RefreshDatabase;

    private int $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Baseline', 'code' => 'BASE',
            'created_at' => now(), 'updated_at' => now()]);
        $this->requester = DB::table('users')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'username' => 'baseline-requester', 'email' => 'baseline@example.com', 'password' => bcrypt('test-password'),
            'auth_source' => 'LOCAL', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_seeder_publishes_baseline_policies_and_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\SlaBaselineSeeder::class);
        $this->seed(\Database\Seeders\SlaBaselineSeeder::class);

        $this->assertDatabaseCount('sla_policies', 5);
        $this->assertDatabaseCount('sla_policy_versions', 5);
        $this->assertSame(5, DB::table('sla_policies')->where('publication_status', 'ACTIVE')->whereNotNull('published_version_id')->count());
        $this->assertSame(2, DB::table('business_calendars')->where('organization_id', 1)->count());
        $this->assertSame(5, DB::table('business_hours')->count(), 'Ish vaqti kalendari Du–Ju kunlarini qamrashi kerak.');
    }

    public function test_critical_ticket_matches_the_p1_policy(): void
    {
        $this->seed(\Database\Seeders\SlaBaselineSeeder::class);

        $ticket = Ticket::create(['organization_id' => 1, 'ticket_no' => 'SLA-'.Str::random(8), 'subject' => 'Server ishlamayapti',
            'description' => 'Server ishlamayapti', 'status_id' => 1, 'priority_id' => 1, 'source_id' => 1, 'requester_user_id' => $this->requester]);

        $run = DB::table('ticket_sla_runs')->where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($run, 'Kritik zayavkaga SLA sikli yaratilishi kerak.');
        $version = DB::table('sla_policy_versions')->find($run->policy_version_id);
        $this->assertSame('SLA-P1', DB::table('sla_policies')->where('id', $version->sla_policy_id)->value('code'));

        $resolution = DB::table('ticket_sla_instances')->where('run_id', $run->id)->where('metric', 'RESOLUTION')->first();
        $this->assertSame(120, (int) $resolution->target_minutes);
    }

    public function test_ticket_without_matching_priority_falls_back_to_the_default_policy(): void
    {
        $this->seed(\Database\Seeders\SlaBaselineSeeder::class);
        DB::table('sla_policies')->where('code', 'SLA-P3')->update(['is_active' => false, 'publication_status' => 'ARCHIVED']);

        $ticket = Ticket::create(['organization_id' => 1, 'ticket_no' => 'SLA-'.Str::random(8), 'subject' => 'Printer',
            'description' => 'Printer', 'status_id' => 1, 'priority_id' => 3, 'source_id' => 1, 'requester_user_id' => $this->requester]);

        $run = DB::table('ticket_sla_runs')->where('ticket_id', $ticket->id)->first();
        $version = DB::table('sla_policy_versions')->find($run->policy_version_id);
        $this->assertSame('DEFAULT-SLA', DB::table('sla_policies')->where('id', $version->sla_policy_id)->value('code'));
    }
}
