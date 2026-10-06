<?php

namespace Tests\Feature\Saving;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavingFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function createHouseholdWithUser(string $role = 'household_owner')
    {
        $user = User::factory()->create();
        $household = Household::create(['name' => 'Savings Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => $role,
        ]);

        return [$household, $user];
    }

    protected function createAccount(int $householdId, string $name = 'Bank BCA')
    {
        return Account::create([
            'household_id' => $householdId,
            'name' => $name,
            'type' => 'bank',
            'initial_balance' => '5000000.00',
            'is_active' => true,
        ]);
    }

    // 1. CREATE SAVING GOAL
    /** @test */
    public function owner_can_create_valid_saving_goal()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        $account = $this->createAccount($household->id);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/households/{$household->id}/savings", [
            'name' => 'Dana Darurat 6 Bulan',
            'target_amount' => 30000000.00,
            'current_amount' => 5000000.00,
            'target_date' => '2027-12-31',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('saving.name', 'Dana Darurat 6 Bulan')
            ->assertJsonPath('saving.target_amount', '30000000.00')
            ->assertJsonPath('saving.current_amount', '5000000.00')
            ->assertJsonPath('saving.remaining_amount', '25000000.00')
            ->assertJsonPath('saving.progress_percentage', 16.67)
            ->assertJsonPath('saving.status', 'active');

        $this->assertDatabaseHas('savings', [
            'household_id' => $household->id,
            'name' => 'Dana Darurat 6 Bulan',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'action' => 'saving_created',
        ]);
    }

    /** @test */
    public function validation_fails_for_missing_required_saving_fields()
    {
        [$household, $owner] = $this->createHouseholdWithUser();

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/households/{$household->id}/savings", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'target_amount']);
    }

    /** @test */
    public function validation_fails_for_cross_household_account()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser();
        [$householdB, $userB] = $this->createHouseholdWithUser();
        $accountB = $this->createAccount($householdB->id);

        Sanctum::actingAs($userA);

        $response = $this->postJson("/api/v1/households/{$householdA->id}/savings", [
            'name' => 'Cross Household Account Saving',
            'target_amount' => 10000000.00,
            'account_id' => $accountB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    // 2. LIST SAVINGS
    /** @test */
    public function member_can_list_household_savings()
    {
        [$household, $member] = $this->createHouseholdWithUser('household_member');

        Saving::create([
            'household_id' => $household->id,
            'name' => 'Saving 1',
            'target_amount' => '10000.00',
            'current_amount' => '2000.00',
        ]);
        Saving::create([
            'household_id' => $household->id,
            'name' => 'Saving 2',
            'target_amount' => '50000.00',
            'current_amount' => '50000.00',
            'is_completed' => true,
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/v1/households/{$household->id}/savings");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'savings');
    }

    // 3. DETAIL SAVING
    /** @test */
    public function user_can_view_saving_detail()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $saving = Saving::create([
            'household_id' => $household->id,
            'name' => 'Liburan',
            'target_amount' => '5000000.00',
            'current_amount' => '1000000.00',
            'target_date' => '2027-06-01',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/households/{$household->id}/savings/{$saving->id}");

        $response->assertStatus(200)
            ->assertJsonPath('saving.name', 'Liburan')
            ->assertJsonPath('saving.remaining_amount', '4000000.00')
            ->assertJsonPath('saving.progress_percentage', 20);
    }

    // 4. UPDATE SAVING
    /** @test */
    public function user_can_update_saving_goal_and_auto_complete()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $saving = Saving::create([
            'household_id' => $household->id,
            'name' => 'Laptop Baru',
            'target_amount' => '15000000.00',
            'current_amount' => '5000000.00',
            'is_completed' => false,
        ]);

        Sanctum::actingAs($user);

        // Update current amount to match target
        $response = $this->patchJson("/api/v1/households/{$household->id}/savings/{$saving->id}", [
            'current_amount' => 15000000.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('saving.is_completed', true)
            ->assertJsonPath('saving.progress_percentage', 100)
            ->assertJsonPath('saving.status', 'completed');

        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'action' => 'saving_updated',
        ]);
    }

    // 5. DELETE SAVING
    /** @test */
    public function user_can_delete_saving_goal()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $saving = Saving::create([
            'household_id' => $household->id,
            'name' => 'To Delete',
            'target_amount' => '1000.00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/households/{$household->id}/savings/{$saving->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('savings', ['id' => $saving->id]);
        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'action' => 'saving_deleted',
        ]);
    }

    // 6. CALCULATION & PROGRESS TESTS (Task 9.9)
    /** @test */
    public function saving_progress_and_remaining_calculated_correctly()
    {
        [$household, $user] = $this->createHouseholdWithUser();

        $saving = Saving::create([
            'household_id' => $household->id,
            'name' => 'Kamera Canon',
            'target_amount' => '10000000.00',
            'current_amount' => '2500000.00',
        ]);

        $this->assertEquals('7500000.00', $saving->remaining_amount);
        $this->assertEquals(25.0, $saving->progress_percentage);
        $this->assertEquals('active', $saving->status);
    }

    // 7. AUTHORIZATION TESTS (Task 9.10)
    /** @test */
    public function unauthenticated_user_cannot_access_savings()
    {
        [$household] = $this->createHouseholdWithUser();

        $this->getJson("/api/v1/households/{$household->id}/savings")->assertStatus(401);
    }

    /** @test */
    public function super_admin_without_membership_is_forbidden_from_savings()
    {
        [$household] = $this->createHouseholdWithUser();
        $superAdmin = User::factory()->create(['global_role' => 'super_admin']);

        Sanctum::actingAs($superAdmin);

        $this->getJson("/api/v1/households/{$household->id}/savings")->assertStatus(403);
    }

    /** @test */
    public function user_from_another_household_cannot_access_or_mutate_savings()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser();
        [$householdB, $userB] = $this->createHouseholdWithUser();

        $savingB = Saving::create([
            'household_id' => $householdB->id,
            'name' => 'Household B Saving Goal',
            'target_amount' => '5000000.00',
        ]);

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/households/{$householdB->id}/savings")->assertStatus(403);
        $this->getJson("/api/v1/households/{$householdB->id}/savings/{$savingB->id}")->assertStatus(403);
        $this->getJson("/api/v1/households/{$householdA->id}/savings/{$savingB->id}")->assertStatus(403);
        $this->patchJson("/api/v1/households/{$householdA->id}/savings/{$savingB->id}", ['name' => 'Hacked'])->assertStatus(403);
        $this->deleteJson("/api/v1/households/{$householdA->id}/savings/{$savingB->id}")->assertStatus(403);
    }
}
