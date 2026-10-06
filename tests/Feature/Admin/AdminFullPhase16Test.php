<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFullPhase16Test extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;
    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'global_role' => 'super_admin',
        ]);

        $this->regularUser = User::factory()->create();

        $this->household = Household::create([
            'name' => 'Keluarga Tes Admin Phase 16',
            'status' => 'active',
        ]);

        HouseholdMember::create([
            'user_id' => $this->regularUser->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);
    }

    // ==========================================
    // 16.5 USER MANAGEMENT TESTS
    // ==========================================

    /** @test */
    public function super_admin_can_list_and_search_users()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=' . $this->regularUser->name);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->regularUser->id)
            ->assertJsonPath('data.0.email', $this->regularUser->email);
    }

    /** @test */
    public function super_admin_can_view_user_detail()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$this->regularUser->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->regularUser->id)
            ->assertJsonPath('data.households.0.household_id', $this->household->id);

        $this->assertStringNotContainsString('password', $response->getContent());
    }

    // ==========================================
    // 16.6 ADMIN RESET PASSWORD TESTS
    // ==========================================

    /** @test */
    public function super_admin_can_force_reset_user_password()
    {
        // Create token for regular user
        $this->regularUser->createToken('test_token');
        $this->assertEquals(1, $this->regularUser->tokens()->count());

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$this->regularUser->id}/reset-password", [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password user berhasil di-reset dan token aktif di-revoke.');

        $this->regularUser->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->regularUser->password));
        $this->assertEquals(0, $this->regularUser->tokens()->count());

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin_user_password_reset',
            'entity_id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function regular_user_cannot_reset_passwords()
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/v1/admin/users/{$this->superAdmin->id}/reset-password", [
                'password' => 'hackedpassword',
                'password_confirmation' => 'hackedpassword',
            ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // 16.7 PLAN MANAGEMENT TESTS
    // ==========================================

    /** @test */
    public function super_admin_can_crud_plans()
    {
        // Create plan
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Paket Pro Admin',
                'slug' => 'pro-admin',
                'price' => 150000,
                'duration_days' => 30,
                'max_members' => 10,
                'max_accounts' => 5,
            ]);

        $response->assertStatus(201);
        $planId = $response->json('plan.id');

        // Update plan
        $this->patchJson("/api/v1/admin/plans/{$planId}", [
            'name' => 'Paket Pro Admin Updated',
            'price' => 175000,
        ])->assertStatus(200);

        // Delete plan
        $this->deleteJson("/api/v1/admin/plans/{$planId}")
            ->assertStatus(200);
    }

    /** @test */
    public function cannot_delete_plan_assigned_to_subscription()
    {
        $plan = Plan::create([
            'name' => 'Basic Plan',
            'slug' => 'basic-plan',
            'price' => 50000,
            'duration_days' => 30,
        ]);

        Subscription::create([
            'household_id' => $this->household->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/plans/{$plan->id}");

        $response->assertStatus(422);
    }

    // ==========================================
    // 16.8 SUBSCRIPTION MANAGEMENT TESTS
    // ==========================================

    /** @test */
    public function super_admin_can_assign_and_update_subscription()
    {
        $plan = Plan::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 500000,
            'duration_days' => 365,
        ]);

        // Assign plan to household
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/households/{$this->household->id}/subscription", [
                'plan_id' => $plan->id,
            ]);

        $response->assertStatus(200);
        $subId = $response->json('subscription.id');

        // Update status / extend
        $this->patchJson("/api/v1/admin/subscriptions/{$subId}", [
            'status' => 'suspended',
        ])->assertStatus(200)
          ->assertJsonPath('subscription.status', 'suspended');
    }

    // ==========================================
    // 16.9 GLOBAL ACTIVITY LOG TESTS
    // ==========================================

    /** @test */
    public function super_admin_can_view_global_activity_logs()
    {
        ActivityLog::create([
            'user_id' => $this->regularUser->id,
            'household_id' => $this->household->id,
            'action' => 'test_action',
            'entity_type' => 'test',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/activity-logs');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.action', 'test_action');
    }

    // ==========================================
    // 16.10 ADMIN SETTINGS TESTS
    // ==========================================

    /** @test */
    public function super_admin_can_read_and_update_platform_settings()
    {
        $getRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/settings');

        $getRes->assertStatus(200)
            ->assertJsonPath('settings.app_name', 'SaaS Keuangan Keluarga');

        $patchRes = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                'app_name' => 'Keuangan Keluarga Enterprise',
                'maintenance_mode' => 'true',
            ],
        ]);

        $patchRes->assertStatus(200);

        $this->assertEquals('Keuangan Keluarga Enterprise', SystemSetting::get('app_name'));
        $this->assertEquals('true', SystemSetting::get('maintenance_mode'));
    }
}
