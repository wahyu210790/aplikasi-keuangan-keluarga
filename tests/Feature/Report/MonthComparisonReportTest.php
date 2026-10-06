<?php

namespace Tests\Feature\Report;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthComparisonReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Household $household;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create([
            'name' => 'Keluarga Tes Month Comparison',
        ]);

        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'name' => 'Bank Utama',
            'type' => 'bank',
            'initial_balance' => '1000000.00',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function user_can_get_month_comparison_report()
    {
        // Selected month: 2026-10
        Transaction::create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'amount' => '5000000.00',
            'transaction_date' => '2026-10-10',
            'account_id' => $this->account->id,
        ]);

        Transaction::create([
            'household_id' => $this->household->id,
            'type' => 'expense',
            'amount' => '2000000.00',
            'transaction_date' => '2026-10-15',
            'account_id' => $this->account->id,
        ]);

        // Previous month: 2026-09
        Transaction::create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'amount' => '4000000.00',
            'transaction_date' => '2026-09-10',
            'account_id' => $this->account->id,
        ]);

        Transaction::create([
            'household_id' => $this->household->id,
            'type' => 'expense',
            'amount' => '1000000.00',
            'transaction_date' => '2026-09-15',
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/households/{$this->household->id}/reports/month-comparison?month=2026-10");

        $response->assertStatus(200)
            ->assertJsonPath('selected_month', '2026-10')
            ->assertJsonPath('previous_month', '2026-09')
            ->assertJsonPath('current_month.income', '5000000.00')
            ->assertJsonPath('current_month.expense', '2000000.00')
            ->assertJsonPath('current_month.net', '3000000.00')
            ->assertJsonPath('previous_month_data.income', '4000000.00')
            ->assertJsonPath('previous_month_data.expense', '1000000.00')
            ->assertJsonPath('previous_month_data.net', '3000000.00')
            ->assertJsonPath('comparison.income_diff', '1000000.00')
            ->assertJsonPath('comparison.income_change_percentage', 25);
    }

    /** @test */
    public function non_member_cannot_access_month_comparison_report()
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/households/{$this->household->id}/reports/month-comparison?month=2026-10");

        $response->assertStatus(403);
    }
}
