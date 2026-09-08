<?php

namespace Tests\Feature\ITSM;

use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    private int $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Catalog', 'code' => 'CAT',
            'created_at' => now(), 'updated_at' => now()]);
        $this->requester = DB::table('users')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'username' => 'catalog-requester', 'email' => 'catalog@example.com', 'password' => bcrypt('test-password'),
            'auth_source' => 'LOCAL', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_catalog_services_and_their_sla_policies_are_imported_once(): void
    {
        $this->seed(\Database\Seeders\ServiceCatalogSeeder::class);
        $this->seed(\Database\Seeders\ServiceCatalogSeeder::class);

        $this->assertSame(25, DB::table('services')->count());
        $this->assertSame(25, DB::table('service_offerings')->count());
        // 25 ta xizmat qoidasi + baseline seeder chaqirgan 5 ta prioritet qoidasi.
        $this->assertSame(30, DB::table('sla_policies')->where('publication_status', 'ACTIVE')->count());

        $config = json_decode(DB::table('sla_policies')->where('code', 'SLA-ORG-006')->value('draft_config'), true);
        $this->assertSame(30, collect($config['targets'])->firstWhere('metric', 'FIRST_RESPONSE')['minutes']);
        $this->assertSame(480, collect($config['targets'])->firstWhere('metric', 'RESOLUTION')['minutes'], '8 soat = 480 daqiqa.');
        $this->assertSame('BUSINESS', $config['targets'][0]['calendar_mode'], '12/6 support — ish vaqti kalendari.');

        $round = json_decode(DB::table('sla_policies')->where('code', 'SLA-ORG-001')->value('draft_config'), true);
        $this->assertSame('24X7', $round['targets'][0]['calendar_mode'], '24/7 support — uzluksiz kalendar.');
        $this->assertSame(7200, collect($round['targets'])->firstWhere('metric', 'RESOLUTION')['minutes'], '5 kun × 24 soat.');
    }

    public function test_ticket_on_a_catalog_service_uses_that_services_sla(): void
    {
        $this->seed(\Database\Seeders\ServiceCatalogSeeder::class);
        $offering = DB::table('service_offerings')->where('code', 'ORG-009')->first(); // 15 daqiqa / 1 soat

        $ticket = Ticket::create(['organization_id' => 1, 'ticket_no' => 'SLA-'.Str::random(8), 'subject' => 'Zoom link',
            'description' => 'Zoom link kerak', 'status_id' => 1, 'priority_id' => 3, 'source_id' => 1,
            'requester_user_id' => $this->requester, 'service_offering_id' => $offering->id]);

        $run = DB::table('ticket_sla_runs')->where('ticket_id', $ticket->id)->first();
        $version = DB::table('sla_policy_versions')->find($run->policy_version_id);
        $this->assertSame('SLA-ORG-009', DB::table('sla_policies')->where('id', $version->sla_policy_id)->value('code'));

        $response = DB::table('ticket_sla_instances')->where('run_id', $run->id)->where('metric', 'FIRST_RESPONSE')->first();
        $this->assertSame(15, (int) $response->target_minutes);
    }
}
