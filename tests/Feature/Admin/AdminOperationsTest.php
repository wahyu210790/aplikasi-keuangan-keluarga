<?php

namespace Tests\Feature\Admin;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $householdOwner;
    protected User $householdMember;
    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin user (global_role = super_admin)
        $this->superAdmin = User::factory()->create([
            'global_role' => 'super_admin',
        ]);

        // Create Regular Owner & Household
        $this->householdOwner = User::factory()->create();
        $this->household = Household::create([
            'name' => 'Keluarga Utama',
            'description' => 'Household testing 1',
            'status' => 'active',
        ]);

        HouseholdMember::create([
            'user_id' => $this->householdOwner->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        // Create Regular Member
        $this->householdMember = User::factory()->create();
        HouseholdMember::create([
            'user_id' => $this->householdMember->id,
            'household_id' => $this->household->id,
            'role' => 'household_member',
        ]);
    }

    /** @test */
    public function super_admin_can_access_global_platform_stats()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_users',
                    'total_households',
                    'active_households',
                    'suspended_households',
                    'total_transactions',
                    'total_volume',
                    'active_subscriptions',
                    'subscription_overview',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.total_users'));
        $this->assertEquals(1, $response->json('data.total_households'));
        $this->assertEquals(1, $response->json('data.active_households'));
    }

    /** @test */
    public function super_admin_can_list_and_filter_households()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/households?search=Keluarga');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Keluarga Utama')
            ->assertJsonPath('data.0.owner.email', $this->householdOwner->email);
    }

    /** @test */
    public function super_admin_can_view_household_detail()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/households/{$this->household->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->household->id)
            ->assertJsonPath('data.name', 'Keluarga Utama');
    }

    /** @test */
    public function super_admin_can_update_household_status()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/households/{$this->household->id}", [
                'status' => 'suspended',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('households', [
            'id' => $this->household->id,
            'status' => 'suspended',
        ]);
    }

    /** @test */
    public function household_owner_is_forbidden_from_admin_endpoints()
    {
        $response = $this->actingAs($this->householdOwner, 'sanctum')
            ->getJson('/api/v1/admin/stats');

        $response->assertStatus(403);
    }

    /** @test */
    public function household_member_is_forbidden_from_admin_endpoints()
    {
        $response = $this->actingAs($this->householdMember, 'sanctum')
            ->getJson('/api/v1/admin/stats');

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_users_are_rejected()
    {
        $response = $this->getJson('/api/v1/admin/stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function sensitive_user_fields_are_never_exposed_in_admin_api()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/households/{$this->household->id}");

        $response->assertStatus(200);

        $jsonString = $response->getContent();
        $this->assertStringNotContainsString('password', $jsonString);
        $this->assertStringNotContainsString('remember_token', $jsonString);
    }
}
