<?php

namespace Tests\Feature\Report;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function createHouseholdWithUser(string $role = 'household_owner')
    {
        $user = User::factory()->create();
        $household = Household::create(['name' => 'Report Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => $role,
        ]);

        return [$household, $user];
    }

    /** @test */
    public function financial_summary_report_returns_accurate_totals()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        Sanctum::actingAs($owner);

        // Account
        Account::create([
            'household_id' => $household->id,
            'name' => 'Bank BCA',
            'type' => 'bank',
            'initial_balance' => '5000000.00',
            'is_active' => true,
        ]);

        // Transactions in period
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '10000000.00',
            'transaction_date' => '2026-10-05',
        ]);
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => '3000000.00',
            'transaction_date' => '2026-10-10',
        ]);

        // Active Budget & Saving
        Budget::create([
            'household_id' => $household->id,
            'name' => 'Food Budget',
            'amount' => '2000000.00',
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);
        Saving::create([
            'household_id' => $household->id,
            'name' => 'Car Saving',
            'target_amount' => '50000000.00',
            'current_amount' => '10000000.00',
        ]);

        $response = $this->getJson("/api/v1/households/{$household->id}/reports/summary?start_date=2026-10-01&end_date=2026-10-31");

        $response->assertStatus(200)
            ->assertJsonPath('summary.total_income', '10000000.00')
            ->assertJsonPath('summary.total_expense', '3000000.00')
            ->assertJsonPath('summary.net_cashflow', '7000000.00')
            ->assertJsonPath('summary.active_budgets_count', 1)
            ->assertJsonPath('summary.active_savings_count', 1);
    }

    /** @test */
    public function income_vs_expense_report_groups_by_date()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        Sanctum::actingAs($user);

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '5000.00',
            'transaction_date' => '2026-10-15',
        ]);
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => '2000.00',
            'transaction_date' => '2026-10-15',
        ]);

        $response = $this->getJson("/api/v1/households/{$household->id}/reports/income-vs-expense?start_date=2026-10-01&end_date=2026-10-31");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-10-15')
            ->assertJsonPath('data.0.income', '5000.00')
            ->assertJsonPath('data.0.expense', '2000.00')
            ->assertJsonPath('data.0.net', '3000.00');
    }

    /** @test */
    public function report_authorization_rejects_unauthenticated_and_cross_household_users()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser();
        [$householdB, $userB] = $this->createHouseholdWithUser();

        // Unauthenticated
        $this->getJson("/api/v1/households/{$householdA->id}/reports/summary")->assertStatus(401);

        // Cross-household
        Sanctum::actingAs($userA);
        $this->getJson("/api/v1/households/{$householdB->id}/reports/summary")->assertStatus(403);
    }
}
