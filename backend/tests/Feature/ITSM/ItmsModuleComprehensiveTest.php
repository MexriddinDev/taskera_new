<?php

namespace Tests\Feature\ITSM;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItmsModuleComprehensiveTest extends TestCase
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
            'name' => 'ITSM Corp',
            'code' => 'ITSM',
            'default_locale_id' => 1,
            'default_timezone_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ticket_statuses')->insertOrIgnore([
            ['id' => 1, 'code' => 'NEW', 'name' => 'New', 'status_group' => 'OPEN', 'sort_order' => 1],
            ['id' => 4, 'code' => 'IN_PROGRESS', 'name' => 'In Progress', 'status_group' => 'OPEN', 'sort_order' => 4],
            ['id' => 7, 'code' => 'RESOLVED', 'name' => 'Resolved', 'status_group' => 'CLOSED', 'sort_order' => 7],
            ['id' => 9, 'code' => 'REJECTED', 'name' => 'Rejected', 'status_group' => 'CLOSED', 'sort_order' => 9],
        ]);
        DB::table('ticket_priorities')->insertOrIgnore([
            ['id' => 1, 'code' => 'CRITICAL', 'name' => 'Critical', 'weight' => 1, 'color' => '#ef4444', 'is_active' => 1],
            ['id' => 2, 'code' => 'HIGH', 'name' => 'High', 'weight' => 2, 'color' => '#f97316', 'is_active' => 1],
            ['id' => 3, 'code' => 'MEDIUM', 'name' => 'Medium', 'weight' => 3, 'color' => '#f59e0b', 'is_active' => 1],
        ]);
        DB::table('ticket_sources')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web Portal', 'is_active' => 1],
            ['id' => 2, 'code' => 'TELEGRAM', 'name' => 'Telegram Bot', 'is_active' => 1],
        ]);
        DB::table('departments')->insertOrIgnore([
            'id' => 1,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'code' => 'IT_OPS',
            'name' => 'IT Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('asset_types')->insertOrIgnore([
            ['id' => 1, 'code' => 'SERVER', 'name' => 'Server', 'is_active' => 1, 'sort_order' => 1],
        ]);
        DB::table('asset_statuses')->insertOrIgnore([
            ['id' => 1, 'code' => 'ACTIVE', 'name' => 'Active', 'is_active' => 1, 'sort_order' => 1],
        ]);
    }

    private function createAdminWithPermissions(array $permissionNames): User
    {
        $userId = DB::table('users')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'username' => 'itsm_admin_' . uniqid(),
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => Hash::make('Secret123!'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'ITSM Staff ' . uniqid(),
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

        foreach ($permissionNames as $pName) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => $pName,
                'guard_name' => 'web',
                'module' => 'itsm',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_has_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permId,
            ]);
        }

        return User::find($userId);
    }

    /** 1. Test Problem lifecycle: creation, validation, and listing */
    public function test_problem_management_lifecycle(): void
    {
        $user = $this->createAdminWithPermissions(['problems.view', 'problems.manage']);
        Sanctum::actingAs($user);

        // Validation rejection on missing title
        $invalidRes = $this->postJson('/api/v1/problems', []);
        $invalidRes->assertStatus(422);

        // Successful creation
        $createRes = $this->postJson('/api/v1/problems', [
            'title' => 'Database Latency Spike during Peak Hours',
            'description' => 'Root cause under investigation',
            'status' => 1,
            'known_error' => true,
            'workaround' => 'Restart replica pods',
        ]);
        $createRes->assertStatus(201);
        $problemId = $createRes->json('data.id');
        $this->assertNotEmpty($problemId);

        // Listing problems
        $listRes = $this->getJson('/api/v1/problems');
        $listRes->assertStatus(200);
        $this->assertCount(1, $listRes->json('data'));
    }

    /** 2. Test Change Management lifecycle and status updates */
    public function test_change_management_lifecycle(): void
    {
        $user = $this->createAdminWithPermissions(['changes.view', 'changes.manage']);
        Sanctum::actingAs($user);

        $createRes = $this->postJson('/api/v1/changes', [
            'title' => 'Upgrade MySQL Replica Node',
            'description' => 'Upgrading to latest version for index optimization',
            'change_type' => 'NORMAL',
            'risk_level' => 'MEDIUM',
            'impact' => 'HIGH',
            'status' => 'DRAFT',
            'planned_start_at' => now()->addDay()->toDateTimeString(),
            'planned_end_at' => now()->addDays(2)->toDateTimeString(),
            'backout_plan' => 'Revert DNS to Primary',
        ]);
        $createRes->assertStatus(201);
        $changeId = $createRes->json('data.id');

        // Update change status
        $updateRes = $this->putJson("/api/v1/changes/{$changeId}", [
            'status' => 'SCHEDULED',
            'risk_level' => 'LOW',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('SCHEDULED', $updateRes->json('data.status'));
    }

    /** 3. Test Asset / CMDB creation and retrieval */
    public function test_asset_management_lifecycle(): void
    {
        $user = $this->createAdminWithPermissions(['assets.view', 'assets.manage']);
        Sanctum::actingAs($user);

        $createRes = $this->postJson('/api/v1/assets', [
            'asset_tag' => 'AST-SRV-901',
            'asset_type_id' => 1,
            'status_id' => 1,
            'serial_number' => 'SN-8899001122',
            'hostname' => 'srv-prod-01.corp.internal',
        ]);
        $createRes->assertStatus(201);
        $assetId = $createRes->json('data.id');

        $showRes = $this->getJson("/api/v1/assets/{$assetId}");
        $showRes->assertStatus(200);
        $this->assertEquals('AST-SRV-901', $showRes->json('data.asset_tag'));
    }

    /** 4. Test Category & Service Catalog reference master data */
    public function test_category_and_service_catalog_master_data(): void
    {
        $user = $this->createAdminWithPermissions(['services.manage', 'sla.manage']);
        Sanctum::actingAs($user);

        $createRes = $this->postJson('/api/v1/categories', [
            'code' => 'NETWORKING',
            'name' => 'Network & VPN Infrastructure',
            'description' => 'Issues related to office networking and switches',
            'is_active' => true,
        ]);
        $createRes->assertStatus(201);

        $listRes = $this->getJson('/api/v1/categories');
        $listRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));
    }
}
