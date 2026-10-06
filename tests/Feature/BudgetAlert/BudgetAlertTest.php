<?php

namespace Tests\Feature\BudgetAlert;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Category $category;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Alert Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'name' => 'BCA Alert',
            'type' => 'bank',
            'current_balance' => 5000000,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Makanan',
            'type' => 'expense',
            'is_active' => true,
        ]);
    }

    public function test_user_can_get_budget_alerts_and_exceeded_status(): void
    {
        Sanctum::actingAs($this->user);

        $budget = Budget::create([
            'household_id' => $this->household->id,
            'category_id' => $this->category->id,
            'name' => 'Anggaran Makanan',
            'amount' => 100000,
            'period_type' => 'monthly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'is_active' => true,
        ]);

        Transaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 120000,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/budgets/alerts");

        $response->assertStatus(200)
            ->assertJsonPath('summary.overbudget_count', 1)
            ->assertJsonPath('alerts.0.status', 'exceeded')
            ->assertJsonPath('alerts.0.is_overbudget', true);
    }

    public function test_cross_household_isolation(): void
    {
        $otherUser = User::factory()->create();
        $otherHousehold = Household::create(['name' => 'Other Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $otherUser->id,
            'household_id' => $otherHousehold->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/budgets/alerts");
        $response->assertStatus(403);
    }
}
