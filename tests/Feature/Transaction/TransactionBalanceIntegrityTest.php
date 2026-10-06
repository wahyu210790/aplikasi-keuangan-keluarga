<?php

namespace Tests\Feature\Transaction;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ensure that transaction operations never directly modify account balances.
 * The current balance is calculated from transactions, therefore the
 * `initial_balance` field on accounts must remain unchanged after any
 * transaction create / update / delete operation.
 */
class TransactionBalanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** Helper to create a household with a user as member */
    protected function createHouseholdWithUser(string $role = 'household_owner')
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        $household->members()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
        Sanctum::actingAs($user);
        return [$household, $user];
    }

    /** @test */
    public function income_transaction_creation_does_not_change_account_initial_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 1000.00,
        ]);
        $originalBalance = $account->initial_balance;

        $payload = [
            'type' => 'income',
            'amount' => 200,
            'transaction_date' => now()->toDateString(),
            'account_id' => $account->id,
            'to_account_id' => null,
        ];

        $response = $this->postJson(route('api.v1.households.transactions.store', ['household' => $household->id]), $payload);
        $response->assertStatus(201);

        $account->refresh();
        $this->assertEquals($originalBalance, $account->initial_balance, 'Initial balance should remain unchanged after income create');
    }

    /** @test */
    public function expense_transaction_creation_does_not_change_account_initial_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_member');
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 500.00,
        ]);
        $original = $account->initial_balance;

        $payload = [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => now()->toDateString(),
            'account_id' => $account->id,
            'to_account_id' => null,
        ];

        $this->postJson(route('api.v1.households.transactions.store', ['household' => $household->id]), $payload)
            ->assertStatus(201);

        $account->refresh();
        $this->assertEquals($original, $account->initial_balance);
    }

    /** @test */
    public function transfer_transaction_creation_does_not_change_initial_balance_of_both_accounts()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $source = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 800.00,
        ]);
        $dest = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 300.00,
        ]);
        $srcOriginal = $source->initial_balance;
        $destOriginal = $dest->initial_balance;

        $payload = [
            'type' => 'transfer',
            'amount' => 120,
            'transaction_date' => now()->toDateString(),
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ];

        $this->postJson(route('api.v1.households.transactions.store', ['household' => $household->id]), $payload)
            ->assertStatus(201);

        $source->refresh();
        $dest->refresh();
        $this->assertEquals($srcOriginal, $source->initial_balance);
        $this->assertEquals($destOriginal, $dest->initial_balance);
    }

    /** @test */
    public function updating_transaction_does_not_change_account_initial_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_member');
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 600.00,
        ]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => 100,
            'account_id' => $account->id,
        ]);
        $original = $account->initial_balance;

        $payload = [
            'amount' => 250,
        ];

        $this->patchJson(route('api.v1.households.transactions.update', ['household' => $household->id, 'transaction' => $transaction->id]), $payload)
            ->assertStatus(200);

        $account->refresh();
        $this->assertEquals($original, $account->initial_balance);
    }

    /** @test */
    public function deleting_transaction_does_not_change_account_initial_balance()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 400.00,
        ]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 50,
            'account_id' => $account->id,
        ]);
        $original = $account->initial_balance;

        $this->deleteJson(route('api.v1.households.transactions.destroy', ['household' => $household->id, 'transaction' => $transaction->id]))
            ->assertStatus(200);

        $account->refresh();
        $this->assertEquals($original, $account->initial_balance);
    }

    /** @test */
    public function transaction_operations_do_not_affect_another_household_accounts()
    {
        // Household A – actor
        [$householdA, $userA] = $this->createHouseholdWithUser('household_owner');
        $accountA = Account::factory()->create([
            'household_id' => $householdA->id,
            'initial_balance' => 1000.00,
        ]);
        $originalA = $accountA->initial_balance;

        // Household B – untouched
        $householdB = Household::factory()->create();
        $accountB = Account::factory()->create([
            'household_id' => $householdB->id,
            'initial_balance' => 2000.00,
        ]);
        $originalB = $accountB->initial_balance;

        // Create income in Household A
        $payload = [
            'type' => 'income',
            'amount' => 300,
            'transaction_date' => now()->toDateString(),
            'account_id' => $accountA->id,
            'to_account_id' => null,
        ];
        $this->postJson(route('api.v1.households.transactions.store', ['household' => $householdA->id]), $payload)
            ->assertStatus(201);

        $accountA->refresh();
        $accountB->refresh();
        $this->assertEquals($originalA, $accountA->initial_balance);
        $this->assertEquals($originalB, $accountB->initial_balance, 'Other household account must stay unchanged');
    }
}
