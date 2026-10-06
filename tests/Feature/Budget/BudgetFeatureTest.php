<?php

namespace Tests\Feature\Budget;

use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function createHouseholdWithUser(string $role = 'household_owner')
    {
        $user = User::factory()->create();
        $household = Household::create(['name' => 'Family Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => $role,
        ]);

        return [$household, $user];
    }

    protected function createCategory(int $householdId, string $name = 'Food', string $type = 'expense')
    {
        return Category::create([
            'household_id' => $householdId,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]);
    }

    // 1. CREATE BUDGET TESTS (Task 8.3, 8.4)
    /** @test */
    public function owner_can_create_valid_budget()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        $category = $this->createCategory($household->id);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/households/{$household->id}/budgets", [
            'name' => 'Monthly Groceries',
            'category_id' => $category->id,
            'amount' => 5000000.00,
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('budget.name', 'Monthly Groceries')
            ->assertJsonPath('budget.amount', '5000000.00');

        $this->assertDatabaseHas('budgets', [
            'household_id' => $household->id,
            'name' => 'Monthly Groceries',
            'period_type' => 'monthly',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'action' => 'budget_created',
        ]);
    }

    /** @test */
    public function validation_fails_if_required_budget_fields_are_missing()
    {
        [$household, $owner] = $this->createHouseholdWithUser();

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/households/{$household->id}/budgets", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'amount', 'period_type', 'start_date', 'end_date']);
    }

    /** @test */
    public function validation_fails_if_category_belongs_to_another_household()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser();
        [$householdB, $userB] = $this->createHouseholdWithUser();
        $categoryB = $this->createCategory($householdB->id);

        Sanctum::actingAs($userA);

        $response = $this->postJson("/api/v1/households/{$householdA->id}/budgets", [
            'name' => 'Invalid Category Budget',
            'category_id' => $categoryB->id,
            'amount' => 100000.00,
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    // 2. LIST BUDGETS TESTS (Task 8.5)
    /** @test */
    public function member_can_list_household_budgets()
    {
        [$household, $member] = $this->createHouseholdWithUser('household_member');

        Budget::create([
            'household_id' => $household->id,
            'name' => 'Budget 1',
            'amount' => '1000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);
        Budget::create([
            'household_id' => $household->id,
            'name' => 'Budget 2',
            'amount' => '2000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/v1/households/{$household->id}/budgets");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'budgets');
    }

    // 3. SHOW BUDGET DETAIL (Task 8.6)
    /** @test */
    public function user_can_view_budget_detail()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $budget = Budget::create([
            'household_id' => $household->id,
            'name' => 'Entertainment',
            'amount' => '1500000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/households/{$household->id}/budgets/{$budget->id}");

        $response->assertStatus(200)
            ->assertJsonPath('budget.name', 'Entertainment')
            ->assertJsonPath('budget.spent_amount', '0.00')
            ->assertJsonPath('budget.remaining_amount', '1500000.00')
            ->assertJsonPath('budget.spent_percentage', 0);
    }

    // 4. UPDATE BUDGET (Task 8.7)
    /** @test */
    public function user_can_update_budget()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $budget = Budget::create([
            'household_id' => $household->id,
            'name' => 'Old Name',
            'amount' => '1000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/households/{$household->id}/budgets/{$budget->id}", [
            'name' => 'New Name',
            'amount' => 2500.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('budget.name', 'New Name')
            ->assertJsonPath('budget.amount', '2500.00');

        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'action' => 'budget_updated',
        ]);
    }

    // 5. DELETE BUDGET (Task 8.8)
    /** @test */
    public function user_can_delete_budget()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $budget = Budget::create([
            'household_id' => $household->id,
            'name' => 'To Delete',
            'amount' => '1000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/households/{$household->id}/budgets/{$budget->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'action' => 'budget_deleted',
        ]);
    }

    // 6. BUDGET CALCULATION TESTS (Task 8.9)
    /** @test */
    public function budget_spent_amount_and_remaining_calculated_correctly()
    {
        [$household, $user] = $this->createHouseholdWithUser();

        $budget = Budget::create([
            'household_id' => $household->id,
            'name' => 'Monthly Expenses',
            'amount' => '1000000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        // Expense transaction within period
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => '300000.00',
            'transaction_date' => '2026-10-05',
        ]);

        // Income transaction within period (should be ignored)
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '500000.00',
            'transaction_date' => '2026-10-05',
        ]);

        // Expense transaction outside period (should be ignored)
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => '200000.00',
            'transaction_date' => '2026-11-01',
        ]);

        $this->assertEquals('300000.00', $budget->calculateSpent());
        $this->assertEquals('700000.00', $budget->remaining_amount);
        $this->assertEquals(30.0, $budget->spent_percentage);
    }

    // 7. AUTHORIZATION TESTS (Task 8.10)
    /** @test */
    public function unauthenticated_user_cannot_access_budgets()
    {
        [$household] = $this->createHouseholdWithUser();

        $this->getJson("/api/v1/households/{$household->id}/budgets")->assertStatus(401);
    }

    /** @test */
    public function super_admin_without_membership_is_forbidden_from_budgets()
    {
        [$household] = $this->createHouseholdWithUser();
        $superAdmin = User::factory()->create(['global_role' => 'super_admin']);

        Sanctum::actingAs($superAdmin);

        $this->getJson("/api/v1/households/{$household->id}/budgets")->assertStatus(403);
    }

    /** @test */
    public function user_from_another_household_cannot_access_or_mutate_budgets()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser();
        [$householdB, $userB] = $this->createHouseholdWithUser();

        $budgetB = Budget::create([
            'household_id' => $householdB->id,
            'name' => 'Household B Budget',
            'amount' => '5000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/households/{$householdB->id}/budgets")->assertStatus(403);
        $this->getJson("/api/v1/households/{$householdB->id}/budgets/{$budgetB->id}")->assertStatus(403);
        $this->getJson("/api/v1/households/{$householdA->id}/budgets/{$budgetB->id}")->assertStatus(403);
        $this->patchJson("/api/v1/households/{$householdA->id}/budgets/{$budgetB->id}", ['name' => 'Hacked'])->assertStatus(403);
        $this->deleteJson("/api/v1/households/{$householdA->id}/budgets/{$budgetB->id}")->assertStatus(403);
    }
}
