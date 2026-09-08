<?php

namespace Tests\Feature\ITSM;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Zayavkalarim" bo'limidagi sana oralig'i filtri (frontend `MyRequestsPage`
 * `startDate` / `endDate` / `dateField` parametrlarini yuboradi).
 */
class MyRequestsDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Filter', 'code' => 'FLT',
            'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('users')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'username' => 'date-filter-user', 'email' => 'date-filter@example.com', 'password' => bcrypt('test-password'),
            'auth_source' => 'LOCAL', 'created_at' => now(), 'updated_at' => now()]);
        $this->requester = User::findOrFail($id);
        Sanctum::actingAs($this->requester);
    }

    private function ticket(string $subject, string $createdAt, ?string $resolvedAt = null): int
    {
        return DB::table('tickets')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => 1,
            'ticket_no' => 'INC-'.Str::random(8), 'ticket_type' => 'INCIDENT', 'subject' => $subject, 'description' => $subject,
            'status_id' => $resolvedAt ? 7 : 1, 'priority_id' => 3, 'source_id' => 1, 'requester_user_id' => $this->requester->id,
            'resolved_at' => $resolvedAt, 'created_at' => $createdAt, 'updated_at' => $createdAt]);
    }

    public function test_submitted_tickets_can_be_filtered_by_a_creation_date_range(): void
    {
        $this->ticket('Eski zayavka', '2026-08-01 09:00:00');
        $inRange = $this->ticket('Oraliqdagi zayavka', '2026-09-05 09:00:00');
        $this->ticket('Yangi zayavka', '2026-09-20 09:00:00');

        $response = $this->getJson('/api/v1/tickets?scope=my_submitted&startDate=2026-09-01&endDate=2026-09-10')->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame($inRange, $response->json('tasks.0.id'));
    }

    public function test_an_open_end_shows_everything_from_that_day_onwards(): void
    {
        $this->ticket('Eski zayavka', '2026-08-01 09:00:00');
        $this->ticket('Sentabr', '2026-09-05 09:00:00');
        $this->ticket('Keyinroq', '2026-09-20 09:00:00');

        $response = $this->getJson('/api/v1/tickets?scope=my_submitted&startDate=2026-09-01')->assertOk();

        $this->assertSame(2, $response->json('total'));
    }

    public function test_filtering_by_resolution_date_keeps_unresolved_tickets_visible(): void
    {
        $this->ticket('Ochiq zayavka', '2026-09-02 09:00:00');
        $this->ticket('Bajarilgan', '2026-09-02 09:00:00', '2026-09-03 12:00:00');
        $this->ticket('Boshqa oyda bajarilgan', '2026-09-02 09:00:00', '2026-10-03 12:00:00');

        $response = $this->getJson('/api/v1/tickets?scope=my_submitted&dateField=resolved_at&startDate=2026-09-01&endDate=2026-09-30')->assertOk();

        // Hali bajarilmagan zayavka sanadan qat'i nazar ro'yxatda qoladi.
        $this->assertSame(2, $response->json('total'));
    }
}
