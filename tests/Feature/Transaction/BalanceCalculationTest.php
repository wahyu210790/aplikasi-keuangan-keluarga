<?php

namespace Tests\Feature\Transaction;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BalanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a household with a user as owner.
     */
    protected function createHouseholdWithUser(string $role = 'household_owner')
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => $role,
        ]);
        Sanctum::actingAs($user);

        return [$household, $user];
    }

    /** Helper to create explicit account with initial balance */
    protected function createAccount(int $householdId, string $initialBalance = '0.00', bool $isActive = true)
    {
        return Account::create([
            'household_id' => $householdId,
            'name' => 'Account ' . rand(100, 999),
            'type' => 'bank',
            'initial_balance' => $initialBalance,
            'is_active' => $isActive,
        ]);
    }

    // ==========================================
    // BASIC BALANCE TESTS
    // ==========================================

    /** @test */
    public function account_without_transactions_returns_initial_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $accountZero = $this->createAccount($household->id, '0.00');
        $accountInitial = $this->createAccount($household->id, '1000.00');

        $this->assertEquals('0.00', $accountZero->calculateBalance());
        $this->assertEquals('1000.00', $accountInitial->calculateBalance());
        $this->assertEquals('1000.00', $accountInitial->current_balance);
    }

    /** @test */
    public function income_increases_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => 500.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $this->assertEquals('1500.00', $account->calculateBalance());
    }

    /** @test */
    public function expense_decreases_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 300.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $this->assertEquals('700.00', $account->calculateBalance());
    }

    /** @test */
    public function transfer_source_decreases_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $source = $this->createAccount($household->id, '1000.00');
        $dest = $this->createAccount($household->id, '500.00');

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'transfer',
            'amount' => 200.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $this->assertEquals('800.00', $source->calculateBalance());
    }

    /** @test */
    public function transfer_destination_increases_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $source = $this->createAccount($household->id, '1000.00');
        $dest = $this->createAccount($household->id, '500.00');

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'transfer',
            'amount' => 200.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $this->assertEquals('700.00', $dest->calculateBalance());
    }

    // ==========================================
    // COMBINED TRANSACTIONS TESTS
    // ==========================================

    /** @test */
    public function income_and_expense_combined()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 500.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 200.00, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);

        $this->assertEquals('1300.00', $account->calculateBalance());
    }

    /** @test */
    public function income_expense_and_transfer_combined()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');
        $other = $this->createAccount($household->id, '500.00');

        // Income +500
        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 500.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        // Expense -200
        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 200.00, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);
        // Transfer out -300
        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 300.00, 'transaction_date' => '2026-10-03', 'account_id' => $account->id, 'to_account_id' => $other->id]);
        // Transfer in +100
        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 100.00, 'transaction_date' => '2026-10-04', 'account_id' => $other->id, 'to_account_id' => $account->id]);

        // 1000 + 500 - 200 - 300 + 100 = 1100
        $this->assertEquals('1100.00', $account->calculateBalance());
    }

    /** @test */
    public function multiple_incomes_calculated_correctly()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '0.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 100.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 200.00, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 300.00, 'transaction_date' => '2026-10-03', 'account_id' => $account->id]);

        $this->assertEquals('600.00', $account->calculateBalance());
    }

    /** @test */
    public function multiple_expenses_calculated_correctly()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 50.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 150.00, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 75.00, 'transaction_date' => '2026-10-03', 'account_id' => $account->id]);

        // 1000 - 50 - 150 - 75 = 725
        $this->assertEquals('725.00', $account->calculateBalance());
    }

    /** @test */
    public function multiple_transfers_calculated_correctly()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $acc1 = $this->createAccount($household->id, '1000.00');
        $acc2 = $this->createAccount($household->id, '500.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 100.00, 'transaction_date' => '2026-10-01', 'account_id' => $acc1->id, 'to_account_id' => $acc2->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 50.00, 'transaction_date' => '2026-10-02', 'account_id' => $acc1->id, 'to_account_id' => $acc2->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 200.00, 'transaction_date' => '2026-10-03', 'account_id' => $acc2->id, 'to_account_id' => $acc1->id]);

        // acc1: 1000 - 100 - 50 + 200 = 1050
        // acc2: 500 + 100 + 50 - 200 = 450
        $this->assertEquals('1050.00', $acc1->calculateBalance());
        $this->assertEquals('450.00', $acc2->calculateBalance());
    }

    // ==========================================
    // PRECISION TESTS
    // ==========================================

    /** @test */
    public function decimal_precision_calculated_correctly()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 100000.50, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 25000.25, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);

        // 1000.00 + 100000.50 - 25000.25 = 76000.25
        $this->assertEquals('76000.25', $account->calculateBalance());
    }

    /** @test */
    public function no_floating_point_rounding_errors()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '0.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 0.10, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 0.20, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);

        $this->assertEquals('0.30', $account->calculateBalance());
    }

    // ==========================================
    // LIFECYCLE TESTS (CREATE / UPDATE / DELETE)
    // ==========================================

    /** @test */
    public function creating_transaction_updates_derived_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        $this->assertEquals('1000.00', $account->calculateBalance());

        $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'income',
            'amount' => 400.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ])->assertStatus(201);

        $this->assertEquals('1400.00', $account->calculateBalance());
    }

    /** @test */
    public function updating_transaction_amount_updates_derived_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => 500.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);
        $this->assertEquals('1500.00', $account->calculateBalance());

        // Update amount to 800
        $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", [
            'amount' => 800.00,
        ])->assertStatus(200);

        $this->assertEquals('1800.00', $account->calculateBalance());
    }

    /** @test */
    public function updating_transaction_account_updates_both_accounts()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $accA = $this->createAccount($household->id, '1000.00');
        $accB = $this->createAccount($household->id, '500.00');

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => 300.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $accA->id,
        ]);
        $this->assertEquals('1300.00', $accA->calculateBalance());
        $this->assertEquals('500.00', $accB->calculateBalance());

        // Change transaction account from A to B
        $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", [
            'account_id' => $accB->id,
        ])->assertStatus(200);

        $this->assertEquals('1000.00', $accA->calculateBalance());
        $this->assertEquals('800.00', $accB->calculateBalance());
    }

    /** @test */
    public function deleting_transaction_restores_previous_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 250.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);
        $this->assertEquals('750.00', $account->calculateBalance());

        $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}")
            ->assertStatus(200);

        $this->assertEquals('1000.00', $account->calculateBalance());
    }

    /** @test */
    public function updating_transfer_source_and_destination_updates_balances()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $source = $this->createAccount($household->id, '1000.00');
        $dest = $this->createAccount($household->id, '500.00');

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'transfer',
            'amount' => 200.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
        $this->assertEquals('800.00', $source->calculateBalance());
        $this->assertEquals('700.00', $dest->calculateBalance());

        // Update transfer amount to 400
        $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", [
            'amount' => 400.00,
        ])->assertStatus(200);

        $this->assertEquals('600.00', $source->calculateBalance());
        $this->assertEquals('900.00', $dest->calculateBalance());
    }

    // ==========================================
    // TENANT ISOLATION TESTS
    // ==========================================

    /** @test */
    public function other_household_transactions_are_excluded()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser();
        [$householdB, $userB] = $this->createHouseholdWithUser();

        $accA = $this->createAccount($householdA->id, '1000.00');
        $accB = $this->createAccount($householdB->id, '2000.00');

        // Transaction in Household B
        Transaction::create([
            'household_id' => $householdB->id,
            'type' => 'income',
            'amount' => 5000.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $accB->id,
        ]);

        $this->assertEquals('1000.00', $accA->calculateBalance());
    }

    /** @test */
    public function other_household_accounts_are_isolated()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser();
        [$householdB, $userB] = $this->createHouseholdWithUser();

        $accA = $this->createAccount($householdA->id, '1000.00');
        $accB = $this->createAccount($householdB->id, '2000.00');

        Transaction::create([
            'household_id' => $householdA->id,
            'type' => 'income',
            'amount' => 300.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $accA->id,
        ]);

        $this->assertEquals('1300.00', $accA->calculateBalance());
        $this->assertEquals('2000.00', $accB->calculateBalance());
    }

    // ==========================================
    // EDGE CASES & DATE FILTERING
    // ==========================================

    /** @test */
    public function account_with_many_transactions()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        for ($i = 0; $i < 10; $i++) {
            Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 100.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
            Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 40.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        }

        // 1000 + (10 * 100) - (10 * 40) = 1000 + 1000 - 400 = 1600
        $this->assertEquals('1600.00', $account->calculateBalance());
    }

    /** @test */
    public function only_expense_transactions()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 100.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'expense', 'amount' => 200.00, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);

        $this->assertEquals('700.00', $account->calculateBalance());
    }

    /** @test */
    public function only_income_transactions()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '500.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 1000.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 2000.00, 'transaction_date' => '2026-10-02', 'account_id' => $account->id]);

        $this->assertEquals('3500.00', $account->calculateBalance());
    }

    /** @test */
    public function only_transfer_transactions()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $accA = $this->createAccount($household->id, '1000.00');
        $accB = $this->createAccount($household->id, '500.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 300.00, 'transaction_date' => '2026-10-01', 'account_id' => $accA->id, 'to_account_id' => $accB->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 100.00, 'transaction_date' => '2026-10-02', 'account_id' => $accB->id, 'to_account_id' => $accA->id]);

        $this->assertEquals('800.00', $accA->calculateBalance());
    }

    /** @test */
    public function transfer_internal_between_same_household_accounts_preserves_total_household_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $accA = $this->createAccount($household->id, '1000.00');
        $accB = $this->createAccount($household->id, '500.00');

        $totalBefore = (float) $accA->calculateBalance() + (float) $accB->calculateBalance();
        $this->assertEquals(1500.00, $totalBefore);

        Transaction::create(['household_id' => $household->id, 'type' => 'transfer', 'amount' => 200.00, 'transaction_date' => '2026-10-01', 'account_id' => $accA->id, 'to_account_id' => $accB->id]);

        $totalAfter = (float) $accA->calculateBalance() + (float) $accB->calculateBalance();
        $this->assertEquals(1500.00, $totalAfter);
        $this->assertEquals('800.00', $accA->calculateBalance());
        $this->assertEquals('700.00', $accB->calculateBalance());
    }

    /** @test */
    public function balance_as_of_date_filtering()
    {
        [$household, $user] = $this->createHouseholdWithUser();
        $account = $this->createAccount($household->id, '1000.00');

        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 500.00, 'transaction_date' => '2026-10-01', 'account_id' => $account->id]);
        Transaction::create(['household_id' => $household->id, 'type' => 'income', 'amount' => 300.00, 'transaction_date' => '2026-10-05', 'account_id' => $account->id]);

        // Cutoff as of 2026-10-02 -> includes only first transaction (1000 + 500 = 1500)
        $this->assertEquals('1500.00', $account->calculateBalance('2026-10-02'));
        // Cutoff as of 2026-10-05 -> includes both transactions (1000 + 500 + 300 = 1800)
        $this->assertEquals('1800.00', $account->calculateBalance('2026-10-05'));
    }
}
