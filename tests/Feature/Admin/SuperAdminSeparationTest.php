<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $householdOwner;
    protected Household $household;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Super Admin User (SaaS Operator ONLY)
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'global_role' => 'super_admin',
        ]);

        // 2. Customer Household Owner User
        $this->householdOwner = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'global_role' => null,
        ]);

        // 3. Customer Household
        $this->household = Household::create([
            'name' => 'Keluarga Budi',
            'status' => 'active',
        ]);

        HouseholdMember::create([
            'user_id' => $this->householdOwner->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->householdOwner->id,
            'name' => 'Bank BCA',
            'type' => 'bank',
            'initial_balance' => '5000000.00',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_super_admin_is_not_a_household_member()
    {
        $this->assertDatabaseMissing('household_members', [
            'user_id' => $this->superAdmin->id,
        ]);

        $this->assertTrue($this->superAdmin->householdMembers->isEmpty());
        $this->assertTrue($this->superAdmin->households->isEmpty());
    }

    /** @test */
    public function test_household_owner_is_a_household_member()
    {
        $this->assertDatabaseHas('household_members', [
            'user_id' => $this->householdOwner->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->assertCount(1, $this->householdOwner->fresh()->householdMembers);
        $this->assertEquals($this->household->id, $this->householdOwner->fresh()->households->first()->id);
    }

    /** @test */
    public function test_super_admin_cannot_access_household_finance_endpoints()
    {
        Sanctum::actingAs($this->superAdmin);

        // Accounts list
        $this->getJson("/api/v1/households/{$this->household->id}/accounts")
            ->assertStatus(403);

        // Transactions list
        $this->getJson("/api/v1/households/{$this->household->id}/transactions")
            ->assertStatus(403);

        // Summary report
        $this->getJson("/api/v1/households/{$this->household->id}/reports/summary")
            ->assertStatus(403);

        // Budgets list
        $this->getJson("/api/v1/households/{$this->household->id}/budgets")
            ->assertStatus(403);
    }

    /** @test */
    public function test_household_owner_cannot_access_super_admin_panel()
    {
        Sanctum::actingAs($this->householdOwner);

        // Admin Stats
        $this->getJson('/api/v1/admin/stats')
            ->assertStatus(403);

        // Admin Households list
        $this->getJson('/api/v1/admin/households')
            ->assertStatus(403);

        // Admin Users list
        $this->getJson('/api/v1/admin/users')
            ->assertStatus(403);
    }

    /** @test */
    public function test_super_admin_can_access_super_admin_panel()
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/admin/stats')
            ->assertStatus(200);

        $response = $this->getJson('/api/v1/admin/households');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Keluarga Budi')
            ->assertJsonPath('data.0.owner.email', 'budi@example.com');
    }

    /** @test */
    public function test_household_owner_can_access_own_household_finance()
    {
        Sanctum::actingAs($this->householdOwner);

        $this->getJson("/api/v1/households/{$this->household->id}/accounts")
            ->assertStatus(200);

        $this->getJson("/api/v1/households/{$this->household->id}/transactions")
            ->assertStatus(200);
    }
}
