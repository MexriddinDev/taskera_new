<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('locales')->insertOrIgnore([
            ['id' => 1, 'code' => 'uz', 'name' => 'Uzbek', 'is_active' => 1, 'sort_order' => 1],
        ]);
        DB::table('timezones')->insertOrIgnore([
            ['id' => 1, 'name' => 'Asia/Tashkent', 'utc_offset_hint' => 300, 'is_active' => 1],
        ]);
        DB::table('organizations')->insertOrIgnore([
            'id' => 1,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Org',
            'code' => 'TEST',
            'default_locale_id' => 1,
            'default_timezone_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ticket_statuses')->insertOrIgnore([
            ['id' => 1, 'code' => 'NEW', 'name' => 'New', 'status_group' => 'OPEN', 'sort_order' => 1],
            ['id' => 7, 'code' => 'RESOLVED', 'name' => 'Resolved', 'status_group' => 'CLOSED', 'sort_order' => 7],
        ]);
        DB::table('ticket_priorities')->insertOrIgnore([
            ['id' => 1, 'code' => 'CRITICAL', 'name' => 'Critical', 'weight' => 1, 'color' => '#ef4444', 'is_active' => 1],
            ['id' => 3, 'code' => 'MEDIUM', 'name' => 'Medium', 'weight' => 3, 'color' => '#f59e0b', 'is_active' => 1],
        ]);
        DB::table('ticket_sources')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web', 'is_active' => 1],
        ]);
        DB::table('departments')->insertOrIgnore([
            'id' => 1,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'code' => 'IT',
            'name' => 'IT Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAdminUser(): User
    {
        $userId = DB::table('users')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'username' => 'admin_user',
            'email' => 'admin@example.com',
            'password' => Hash::make('Secret123!'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'organization_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
            'organization_id' => 1,
        ]);

        return User::find($userId);
    }

    /** 1. Test RoleController::usersWithRoles query efficiency */
    public function test_users_with_roles_pre_fetches_efficiently(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Seed multiple users
        for ($i = 1; $i <= 5; $i++) {
            DB::table('users')->insert([
                'public_id' => (string) \Illuminate\Support\Str::uuid(),
                'organization_id' => 1,
                'username' => "employee_{$i}",
                'email' => "emp_{$i}@example.com",
                'password' => Hash::make('Pass123!'),
                'auth_source' => 'LOCAL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/users/roles');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(6, count($data));

        // Ensure query count is capped (constant <= 6 queries total including auth middleware, instead of 18+)
        $this->assertLessThanOrEqual(6, $queryCount, "Query count should be pre-fetched in <= 6 queries, was {$queryCount}");
    }

    /** 2. Test RoleController::index pre-fetching efficiency */
    public function test_roles_index_pre_fetches_efficiently(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        for ($i = 1; $i <= 4; $i++) {
            DB::table('roles')->insert([
                'name' => "Custom Role {$i}",
                'guard_name' => 'web',
                'organization_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/roles');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(5, count($data));

        // Must execute in <= 5 queries total including auth middleware (roles + model_has_roles + role_has_permissions + auth)
        $this->assertLessThanOrEqual(5, $queryCount, "Roles index should be pre-fetched in <= 5 queries, was {$queryCount}");
    }

    /** 3. Test DashboardApiController::stats single query aggregation */
    public function test_dashboard_stats_aggregation_accuracy(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Insert open critical ticket with breached SLA
        DB::table('tickets')->insert([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => 'INC-DASH-1',
            'ticket_type' => 'INCIDENT',
            'subject' => 'Critical DB Latency',
            'description' => 'Urgent attention required',
            'status_id' => 1, // OPEN
            'priority_id' => 1, // CRITICAL
            'source_id' => 1,
            'requester_user_id' => $admin->id,
            'due_at' => now()->subHour(), // BREACHED
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert resolved ticket today
        DB::table('tickets')->insert([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => 'INC-DASH-2',
            'ticket_type' => 'INCIDENT',
            'subject' => 'Resolved Ticket',
            'description' => 'Done',
            'status_id' => 7, // RESOLVED
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $admin->id,
            'resolved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/dashboard/stats');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        $res = $response->json('data');

        $this->assertEquals(1, $res['open_tickets']);
        $this->assertEquals(1, $res['sla_breach_tickets']);
        $this->assertEquals(1, $res['critical_tickets']);
        $this->assertEquals(1, $res['today_resolved']);
        $this->assertGreaterThanOrEqual(1, $res['active_engineers']);

        // 4 queries total: `permission:dashboard.view` middleware o'qiydigan
        // model_has_roles + roles, so'ng 1 ta ticket agregatsiyasi va 1 ta
        // foydalanuvchilar soni. Agregatsiya bitta so'rovda qolishi shart.
        $this->assertLessThanOrEqual(4, $queryCount, "Dashboard stats should execute in <= 4 queries, was {$queryCount}");
    }
}
