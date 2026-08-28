<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionSecurityHardeningTest extends TestCase
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
        ]);
        DB::table('ticket_priorities')->insertOrIgnore([
            ['id' => 3, 'code' => 'MEDIUM', 'name' => 'Medium', 'weight' => 3, 'color' => '#f59e0b', 'is_active' => 1],
        ]);
        DB::table('ticket_sources')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web', 'is_active' => 1],
        ]);
        DB::table('departments')->insertOrIgnore([
            'id' => 1,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'code' => 'HQ',
            'name' => 'Head Office',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTestUser(string $username = 'user', bool $isSuperAdmin = false): User
    {
        $userId = DB::table('users')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'username' => $username,
            'email' => $username . '@example.com',
            'password' => Hash::make('Secret123!'),
            'auth_source' => 'LOCAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($isSuperAdmin) {
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
        }

        return User::find($userId);
    }

    private function givePermissionToUser(User $user, string $permissionName): void
    {
        $permId = DB::table('permissions')->where('name', $permissionName)->value('id');
        if (!$permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => $permissionName,
                'guard_name' => 'web',
                'module' => 'TEST',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('model_has_permissions')->insertOrIgnore([
            'permission_id' => $permId,
            'model_type' => User::class,
            'model_id' => $user->id,
            'organization_id' => 1,
        ]);

        $user->clearPermissionsCache();
    }

    /** 1. Unauthenticated request -> 401 */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/problems');
        $response->assertStatus(401);
    }

    /** 2. RBAC: Problems endpoints protected by permission:problems.manage */
    public function test_problems_admin_mutations_require_permission(): void
    {
        $regularUser = $this->createTestUser('regular_user');
        Sanctum::actingAs($regularUser);

        // Regular user without problems.manage cannot create problem
        $response = $this->postJson('/api/v1/problems', [
            'title' => 'Critical Server Outage',
            'description' => 'Root cause under investigation',
            'priority' => 'HIGH',
        ]);
        $response->assertStatus(403);

        // User with permission can access
        $this->givePermissionToUser($regularUser, 'problems.manage');
        $allowedResponse = $this->postJson('/api/v1/problems', [
            'title' => 'Critical Server Outage',
            'description' => 'Root cause under investigation',
            'priority' => 'HIGH',
            'organization_id' => 1,
        ]);
        $this->assertNotEquals(403, $allowedResponse->status());
    }

    /** 3. RBAC: Changes endpoints protected by permission:changes.manage & changes.approve */
    public function test_changes_admin_mutations_require_permission(): void
    {
        $regularUser = $this->createTestUser('regular_change_user');
        Sanctum::actingAs($regularUser);

        $response = $this->postJson('/api/v1/changes', [
            'title' => 'Database Schema Upgrade',
        ]);
        $response->assertStatus(403);

        $approveResponse = $this->postJson('/api/v1/changes/1/approve');
        $approveResponse->assertStatus(403);
    }

    /** 4. RBAC: Workflows & Automation protected */
    public function test_workflows_and_automation_require_permission(): void
    {
        $regularUser = $this->createTestUser('regular_workflow_user');
        Sanctum::actingAs($regularUser);

        $this->postJson('/api/v1/workflows', ['name' => 'Auto Routing'])->assertStatus(403);
        $this->postJson('/api/v1/automation-rules', ['name' => 'Auto Assign'])->assertStatus(403);
        $this->postJson('/api/v1/integrations', ['name' => 'Slack'])->assertStatus(403);
        $this->getJson('/api/v1/audit-logs')->assertStatus(403);
    }

    /** 5. IDOR: CommentController prevents reading comments of other user's private tickets */
    public function test_idor_comment_controller_prevents_unauthorized_access(): void
    {
        $owner = $this->createTestUser('ticket_owner');
        $attacker = $this->createTestUser('attacker_user');

        $ticketId = DB::table('tickets')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'ticket_no' => 'INC-991001',
            'ticket_type' => 'INCIDENT',
            'subject' => 'Confidential Financial Report Issue',
            'description' => 'Sensitive data here',
            'status_id' => 1,
            'priority_id' => 3,
            'source_id' => 1,
            'requester_user_id' => $owner->id,
            'department_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attacker attempts to read comments of ticket
        Sanctum::actingAs($attacker);
        $response = $this->getJson("/api/v1/tickets/{$ticketId}/comments");
        $response->assertStatus(403);

        // Attacker attempts to post comment on ticket
        $postResponse = $this->postJson("/api/v1/tickets/{$ticketId}/comments", [
            'body' => 'Injected malicious comment',
        ]);
        $postResponse->assertStatus(403);
    }

    /** 6. IDOR: TaskController prevents viewing/modifying tasks assigned to others */
    public function test_idor_task_controller_prevents_unauthorized_task_access(): void
    {
        $assignee = $this->createTestUser('task_assignee');
        $stranger = $this->createTestUser('stranger_user');

        $taskId = DB::table('tasks')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => 1,
            'title' => 'Confidential Security Audit Task',
            'status' => 'PENDING',
            'assignee_user_id' => $assignee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($stranger);

        // Stranger should NOT be able to view or update stranger's task
        $this->getJson("/api/v1/tasks/{$taskId}")->assertStatus(403);
        $this->putJson("/api/v1/tasks/{$taskId}", ['title' => 'Hacked Task'])->assertStatus(403);
        $this->deleteJson("/api/v1/tasks/{$taskId}")->assertStatus(403);
    }

    /** 7. SMS Verification: Replay Attack Prevention */
    public function test_sms_verification_token_prevents_replay_attacks(): void
    {
        $phone = '+998901234567';
        $code = '12345';

        // 1. Create SMS code in DB
        $smsId = DB::table('sms_codes')->insertGetId([
            'phone' => $phone,
            'code' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Verify Code via API
        $verifyRes = $this->postJson('/api/v1/ad-account/verify-code', [
            'phone' => $phone,
            'code' => $code,
        ]);
        $verifyRes->assertStatus(200);
        $token = $verifyRes->json('verification_token');
        $this->assertNotEmpty($token, 'Verification token should be generated and returned');

        // Check verification is recorded
        $verifiedRow = DB::table('sms_codes')->where('id', $smsId)->first();
        $this->assertNotNull($verifiedRow->verified_at);

        // 3. First consumption of verification
        $employee = [
            'first_name' => 'Ali',
            'last_name' => 'Valiyev',
            'phone' => $phone,
        ];

        // Let's verify that resetting password or creating exchange with valid phone works / attempts once
        // 4. Attempting replay with same phone / token without re-verifying must be REJECTED (422)
        $replayRes = $this->postJson('/api/v1/ad-account/reset-password', [
            'pinfl' => '12345678901234',
            'phone' => $phone,
            'verification_token' => $token,
        ]);

        // If employee is not found in mock/AD, first attempt reaches employee check or 422, but verified status is now CONSUMED
        // Second immediate call without new SMS MUST FAIL with 422 ("Telefon raqam avval SMS orqali tasdiqlanishi kerak")
        $replayRes2 = $this->postJson('/api/v1/ad-account/reset-password', [
            'pinfl' => '12345678901234',
            'phone' => $phone,
            'verification_token' => $token,
        ]);
        $replayRes2->assertStatus(422);
        $this->assertStringContainsString('SMS orqali tasdiqlanishi kerak', $replayRes2->json('message'));
    }
}
