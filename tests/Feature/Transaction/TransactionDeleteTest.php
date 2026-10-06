<?php

namespace Tests\Feature\Transaction;

use Tests\TestCase;
use App\Models\User;
use App\Models\Household;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_user_cannot_delete_transaction()
    {
        $household = Household::factory()->create();
        $account = Account::factory()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
        ]);

        $response = $this->deleteJson(route('api.v1.households.transactions.destroy', [
            'household' => $household->id,
            'transaction' => $transaction->id,
        ]));

        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_cannot_delete_transaction()
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create();
        $account = Account::factory()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
        ]);

        $nonMember = User::factory()->create();
        $this->actingAs($nonMember, 'sanctum');

        $response = $this->deleteJson(route('api.v1.households.transactions.destroy', [
            'household' => $household->id,
            'transaction' => $transaction->id,
        ]));
        $response->assertStatus(403);
    }

    /** @test */
    public function member_cannot_delete_transaction_of_another_household()
    {
        $user = User::factory()->create();
        $householdA = Household::factory()->create();
        $householdB = Household::factory()->create();
        $accountB = Account::factory()->create(['household_id' => $householdB->id]);
        $transactionB = Transaction::factory()->create([
            'household_id' => $householdB->id,
            'account_id' => $accountB->id,
        ]);
        $householdA->members()->create([
            'user_id' => $user->id,
            'role' => 'household_member',
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->deleteJson(route('api.v1.households.transactions.destroy', [
            'household' => $householdB->id,
            'transaction' => $transactionB->id,
        ]));
        $response->assertStatus(403);
    }

    /** @test */
    public function household_member_can_delete_own_transaction_and_logs_activity()
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        $account = Account::factory()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
        ]);
        $household->members()->create([
            'user_id' => $user->id,
            'role' => 'household_member',
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->deleteJson(route('api.v1.households.transactions.destroy', [
            'household' => $household->id,
            'transaction' => $transaction->id,
        ]));
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Transaction deleted successfully.']);

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'transaction_deleted',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'user_id' => $user->id,
            'household_id' => $household->id,
        ]);
    }

    /** @test */
    public function household_owner_can_delete_own_transaction()
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create();
        $account = Account::factory()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
        ]);
        $household->members()->create([
            'user_id' => $owner->id,
            'role' => 'household_owner',
        ]);
        $this->actingAs($owner, 'sanctum');

        $response = $this->deleteJson(route('api.v1.households.transactions.destroy', [
            'household' => $household->id,
            'transaction' => $transaction->id,
        ]));
        $response->assertStatus(200);
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }
}
