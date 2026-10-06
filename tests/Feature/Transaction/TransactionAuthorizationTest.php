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

class TransactionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function createHouseholdWithMember(string $role = 'household_member')
    {
        $user = User::factory()->create();
        $household = Household::create(['name' => 'Test Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => $role,
        ]);

        return [$household, $user];
    }

    protected function createAccount(int $householdId)
    {
        return Account::create([
            'household_id' => $householdId,
            'name' => 'Account ' . rand(100, 999),
            'type' => 'bank',
            'initial_balance' => '1000.00',
            'is_active' => true,
        ]);
    }

    // 1. Unauthenticated user (401)
    /** @test */
    public function unauthenticated_user_cannot_access_any_transaction_endpoint()
    {
        [$household] = $this->createHouseholdWithMember();
        $account = $this->createAccount($household->id);

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '500.00',
            'description' => 'Salary',
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
            'to_account_id' => null,
        ]);

        $this->getJson("/api/v1/households/{$household->id}/transactions")->assertStatus(401);
        $this->postJson("/api/v1/households/{$household->id}/transactions", [])->assertStatus(401);
        $this->getJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}")->assertStatus(401);
        $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", [])->assertStatus(401);
        $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}")->assertStatus(401);
    }

    // 2. Household member (200 / 201)
    /** @test */
    public function household_member_can_access_transactions()
    {
        [$household, $member] = $this->createHouseholdWithMember('household_member');
        $account = $this->createAccount($household->id);

        Sanctum::actingAs($member);

        // Store
        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'income',
            'amount' => 1500.00,
            'description' => 'Freelance',
            'transaction_date' => '2026-10-02',
            'account_id' => $account->id,
        ]);
        $response->assertStatus(201);
        $txId = $response->json('transaction.id');

        // Index
        $this->getJson("/api/v1/households/{$household->id}/transactions")->assertStatus(200);

        // Show
        $this->getJson("/api/v1/households/{$household->id}/transactions/{$txId}")->assertStatus(200);

        // Update
        $this->patchJson("/api/v1/households/{$household->id}/transactions/{$txId}", [
            'amount' => 1600.00,
        ])->assertStatus(200);

        // Delete
        $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$txId}")->assertStatus(200);
    }

    // 3. Household owner (200 / 201)
    /** @test */
    public function household_owner_can_access_transactions()
    {
        [$household, $owner] = $this->createHouseholdWithMember('household_owner');
        $account = $this->createAccount($household->id);

        Sanctum::actingAs($owner);

        // Store
        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 250.00,
            'description' => 'Groceries',
            'transaction_date' => '2026-10-03',
            'account_id' => $account->id,
        ]);
        $response->assertStatus(201);
        $txId = $response->json('transaction.id');

        // Show
        $this->getJson("/api/v1/households/{$household->id}/transactions/{$txId}")->assertStatus(200);

        // Update
        $this->patchJson("/api/v1/households/{$household->id}/transactions/{$txId}", [
            'description' => 'Supermarket',
        ])->assertStatus(200);

        // Delete
        $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$txId}")->assertStatus(200);
    }

    // 4. Super admin without household membership (403)
    /** @test */
    public function super_admin_without_household_membership_is_forbidden()
    {
        [$household] = $this->createHouseholdWithMember('household_owner');
        $account = $this->createAccount($household->id);

        $superAdmin = User::factory()->create(['global_role' => 'super_admin']);

        Sanctum::actingAs($superAdmin);

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '1000.00',
            'transaction_date' => '2026-10-04',
            'account_id' => $account->id,
        ]);

        $this->getJson("/api/v1/households/{$household->id}/transactions")->assertStatus(403);
        $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'income',
            'amount' => 100,
            'transaction_date' => '2026-10-04',
            'account_id' => $account->id,
        ])->assertStatus(403);
        $this->getJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}")->assertStatus(403);
        $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", ['amount' => 200])->assertStatus(403);
        $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}")->assertStatus(403);
    }

    // 5. Non-member user (403)
    /** @test */
    public function non_member_user_is_forbidden()
    {
        [$household] = $this->createHouseholdWithMember('household_owner');
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/households/{$household->id}/transactions")->assertStatus(403);
    }

    // 6. Cross-household user (403)
    /** @test */
    public function cross_household_user_cannot_access_other_household_transactions()
    {
        [$householdA, $userA] = $this->createHouseholdWithMember('household_owner');
        [$householdB, $userB] = $this->createHouseholdWithMember('household_owner');
        $accountB = $this->createAccount($householdB->id);

        $transactionB = Transaction::create([
            'household_id' => $householdB->id,
            'type' => 'expense',
            'amount' => '100.00',
            'transaction_date' => '2026-10-05',
            'account_id' => $accountB->id,
        ]);

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/households/{$householdB->id}/transactions")->assertStatus(403);
        $this->getJson("/api/v1/households/{$householdB->id}/transactions/{$transactionB->id}")->assertStatus(403);
        $this->patchJson("/api/v1/households/{$householdB->id}/transactions/{$transactionB->id}", ['amount' => 200])->assertStatus(403);
        $this->deleteJson("/api/v1/households/{$householdB->id}/transactions/{$transactionB->id}")->assertStatus(403);
    }

    // 7. Cross-household transaction access (403)
    /** @test */
    public function accessing_other_household_transaction_via_own_household_route_is_forbidden()
    {
        [$householdA, $userA] = $this->createHouseholdWithMember('household_owner');
        [$householdB, $userB] = $this->createHouseholdWithMember('household_owner');
        $accountB = $this->createAccount($householdB->id);

        $transactionB = Transaction::create([
            'household_id' => $householdB->id,
            'type' => 'income',
            'amount' => '500.00',
            'transaction_date' => '2026-10-05',
            'account_id' => $accountB->id,
        ]);

        Sanctum::actingAs($userA);

        // User A tries to view Transaction B via Household A route
        $this->getJson("/api/v1/households/{$householdA->id}/transactions/{$transactionB->id}")->assertStatus(403);
        $this->patchJson("/api/v1/households/{$householdA->id}/transactions/{$transactionB->id}", ['amount' => 999])->assertStatus(403);
        $this->deleteJson("/api/v1/households/{$householdA->id}/transactions/{$transactionB->id}")->assertStatus(403);
    }

    // 8. Cross-household account usage (422 invalid account)
    /** @test */
    public function using_account_from_another_household_is_rejected()
    {
        [$householdA, $userA] = $this->createHouseholdWithMember('household_owner');
        [$householdB, $userB] = $this->createHouseholdWithMember('household_owner');
        $accountB = $this->createAccount($householdB->id);

        Sanctum::actingAs($userA);

        // User A tries to create transaction in Household A using Account from Household B
        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'income',
            'amount' => 500.00,
            'transaction_date' => '2026-10-06',
            'account_id' => $accountB->id,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['account_id']);
    }
}
