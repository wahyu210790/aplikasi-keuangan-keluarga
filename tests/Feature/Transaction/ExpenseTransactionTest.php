<?php

namespace Tests\Feature\Transaction;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTransactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a household with a user and an active account.
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
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        Sanctum::actingAs($user);

        return [$household, $user, $account];
    }

    // ==========================================
    // TASK 7.10 — EXPENSE VALIDATION
    // ==========================================

    /** @test */
    public function amount_is_required()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function amount_must_be_numeric()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 'not-a-number',
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function amount_must_be_greater_than_zero()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $responseZero = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 0,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);
        $responseZero->assertStatus(422)->assertJsonValidationErrors(['amount']);

        $responseNegative = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => -50,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);
        $responseNegative->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function transaction_date_is_required()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'account_id' => $account->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_date']);
    }

    /** @test */
    public function transaction_date_must_be_valid_date()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => 'invalid-date',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_date']);
    }

    /** @test */
    public function account_id_is_required()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => '2026-10-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function account_id_must_belong_to_same_household()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();
        $accountB = Account::factory()->active()->create(['household_id' => $householdB->id]);

        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => '2026-10-01',
            'account_id' => $accountB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function account_id_must_be_active()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();
        $inactiveAccount = Account::factory()->inactive()->create(['household_id' => $household->id]);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => '2026-10-01',
            'account_id' => $inactiveAccount->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function description_is_optional_and_max_255()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        // Too long description (> 255 chars)
        $longDescription = str_repeat('b', 256);
        $responseLong = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
            'description' => $longDescription,
        ]);
        $responseLong->assertStatus(422)->assertJsonValidationErrors(['description']);

        // Valid description
        $responseValid = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
            'description' => 'Makan malam',
        ]);
        $responseValid->assertStatus(201)
            ->assertJsonPath('transaction.description', 'Makan malam');
    }

    /** @test */
    public function to_account_id_must_be_null_for_expense()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();
        $account2 = Account::factory()->active()->create(['household_id' => $household->id]);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
            'to_account_id' => $account2->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    // ==========================================
    // TASK 7.11 — EXPENSE CREATE API
    // ==========================================

    /** @test */
    public function owner_can_create_expense()
    {
        [$household, $owner, $account] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 350.00,
            'description' => 'Belanja Supermarket',
            'transaction_date' => '2026-10-05',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'transaction' => ['id', 'type', 'amount', 'description', 'transaction_date', 'account_id', 'to_account_id']])
            ->assertJsonPath('transaction.type', 'expense')
            ->assertJsonPath('transaction.amount', '350.00')
            ->assertJsonPath('transaction.account_id', $account->id)
            ->assertJsonPath('transaction.to_account_id', null);

        $this->assertDatabaseHas('transactions', [
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 350.00,
            'account_id' => $account->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'action' => 'transaction_created',
            'entity_type' => 'transaction',
        ]);
    }

    /** @test */
    public function member_can_create_expense()
    {
        [$household, $member, $account] = $this->createHouseholdWithUser('household_member');

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 45.00,
            'description' => 'Bensin',
            'transaction_date' => '2026-10-06',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('transaction.type', 'expense');

        $this->assertDatabaseHas('transactions', [
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 45.00,
            'account_id' => $account->id,
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_expense()
    {
        $household = Household::factory()->create();
        $account = Account::factory()->active()->create(['household_id' => $household->id]);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_cannot_create_expense()
    {
        $household = Household::factory()->create();
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'expense',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_cannot_create_expense()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        [$householdB, $userB, $accountB] = $this->createHouseholdWithUser();

        // User A tries to create expense in Household B
        Sanctum::actingAs($userA);
        $response = $this->postJson("/api/v1/households/{$householdB->id}/transactions", [
            'type' => 'expense',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $accountB->id,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function request_body_household_id_cannot_override_route_household()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();

        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'expense',
            'amount' => 200,
            'transaction_date' => '2026-10-01',
            'account_id' => $accountA->id,
            'household_id' => $householdB->id,
        ]);

        $response->assertStatus(201);
        $transactionId = $response->json('transaction.id');

        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'household_id' => $householdA->id,
        ]);
        $this->assertDatabaseMissing('transactions', [
            'id' => $transactionId,
            'household_id' => $householdB->id,
        ]);
    }

    // ==========================================
    // TASK 7.12 — EXPENSE LIST API
    // ==========================================

    /** @test */
    public function member_and_owner_can_list_expense_transactions()
    {
        [$household, $owner, $account] = $this->createHouseholdWithUser('household_owner');

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 120.00,
            'description' => 'Listrik',
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
            'to_account_id' => null,
        ]);

        $response = $this->getJson("/api/v1/households/{$household->id}/transactions");

        $response->assertStatus(200)
            ->assertJsonStructure(['transactions' => [['id', 'type', 'amount', 'description', 'transaction_date', 'account_id', 'to_account_id']]])
            ->assertJsonFragment(['type' => 'expense', 'amount' => '120.00']);
    }

    /** @test */
    public function list_expense_transactions_with_type_filter()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $exp = Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 50.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);
        $inc = Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => 1000.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $response = $this->getJson("/api/v1/households/{$household->id}/transactions?type=expense");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'transactions')
            ->assertJsonFragment(['id' => $exp->id])
            ->assertJsonMissing(['id' => $inc->id]);
    }

    /** @test */
    public function list_expense_transactions_is_tenant_isolated()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        [$householdB, $userB, $accountB] = $this->createHouseholdWithUser();

        $tA = Transaction::create([
            'household_id' => $householdA->id,
            'type' => 'expense',
            'amount' => 30.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $accountA->id,
        ]);
        $tB = Transaction::create([
            'household_id' => $householdB->id,
            'type' => 'expense',
            'amount' => 60.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $accountB->id,
        ]);

        Sanctum::actingAs($userA);
        $response = $this->getJson("/api/v1/households/{$householdA->id}/transactions");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'transactions')
            ->assertJsonFragment(['id' => $tA->id])
            ->assertJsonMissing(['id' => $tB->id]);
    }

    // ==========================================
    // TASK 7.13 — EXPENSE DETAIL API
    // ==========================================

    /** @test */
    public function owner_and_member_can_view_expense_detail()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 75.00,
            'description' => 'Makan Siang',
            'transaction_date' => '2026-10-02',
            'account_id' => $account->id,
        ]);

        $response = $this->getJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['transaction' => ['id', 'type', 'amount', 'description', 'transaction_date', 'account_id', 'to_account_id']])
            ->assertJsonPath('transaction.id', $transaction->id)
            ->assertJsonPath('transaction.type', 'expense')
            ->assertJsonPath('transaction.amount', '75.00')
            ->assertJsonPath('transaction.account_id', $account->id);
    }

    /** @test */
    public function unauthenticated_user_cannot_view_expense_detail()
    {
        $household = Household::factory()->create();
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create(['household_id' => $household->id, 'account_id' => $account->id, 'type' => 'expense']);

        $response = $this->getJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_cannot_view_expense_detail()
    {
        $household = Household::factory()->create();
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create(['household_id' => $household->id, 'account_id' => $account->id, 'type' => 'expense']);

        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);

        $response = $this->getJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_cannot_view_expense_detail()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        [$householdB, $userB, $accountB] = $this->createHouseholdWithUser();

        $transactionB = Transaction::factory()->create(['household_id' => $householdB->id, 'account_id' => $accountB->id, 'type' => 'expense']);

        // User A tries to view transaction B using route of Household B
        Sanctum::actingAs($userA);
        $response = $this->getJson("/api/v1/households/{$householdB->id}/transactions/{$transactionB->id}");

        $response->assertStatus(403);
    }

    // ==========================================
    // TASK 7.14 — EXPENSE UPDATE API
    // ==========================================

    /** @test */
    public function owner_and_member_can_update_expense()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();
        $newAccount = Account::factory()->active()->create(['household_id' => $household->id]);

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 100.00,
            'description' => 'Old description',
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", [
            'amount' => 125.00,
            'description' => 'Updated expense description',
            'transaction_date' => '2026-10-03',
            'account_id' => $newAccount->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('transaction.amount', '125.00')
            ->assertJsonPath('transaction.description', 'Updated expense description')
            ->assertJsonPath('transaction.account_id', $newAccount->id);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 125.00,
            'description' => 'Updated expense description',
            'account_id' => $newAccount->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'action' => 'transaction_updated',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
        ]);
    }

    /** @test */
    public function cannot_update_expense_with_inactive_account()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();
        $inactiveAccount = Account::factory()->inactive()->create(['household_id' => $household->id]);

        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
            'account_id' => $account->id,
        ]);

        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", [
            'account_id' => $inactiveAccount->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function cannot_update_expense_with_to_account_id()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();
        $account2 = Account::factory()->active()->create(['household_id' => $household->id]);

        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
            'account_id' => $account->id,
        ]);

        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", [
            'to_account_id' => $account2->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    /** @test */
    public function cannot_mutate_household_id_on_expense_update()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();

        $transaction = Transaction::factory()->create([
            'household_id' => $householdA->id,
            'type' => 'expense',
            'account_id' => $accountA->id,
        ]);

        $response = $this->patchJson("/api/v1/households/{$householdA->id}/transactions/{$transaction->id}", [
            'household_id' => $householdB->id,
            'amount' => 300,
        ]);

        $response->assertStatus(200);

        // Verify household_id did NOT change
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'household_id' => $householdA->id,
        ]);
    }

    /** @test */
    public function cross_household_cannot_update_expense()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        [$householdB, $userB, $accountB] = $this->createHouseholdWithUser();

        $transactionB = Transaction::factory()->create([
            'household_id' => $householdB->id,
            'type' => 'expense',
            'account_id' => $accountB->id,
        ]);

        // User A tries to update transaction B
        Sanctum::actingAs($userA);
        $response = $this->patchJson("/api/v1/households/{$householdB->id}/transactions/{$transactionB->id}", [
            'amount' => 9999,
        ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // TASK 7.15 — EXPENSE DELETE API
    // ==========================================

    /** @test */
    public function owner_and_member_can_delete_expense()
    {
        [$household, $user, $account] = $this->createHouseholdWithUser();

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => 50.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $account->id,
        ]);

        $response = $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Transaction deleted successfully.']);

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'action' => 'transaction_deleted',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_delete_expense()
    {
        $household = Household::factory()->create();
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create(['household_id' => $household->id, 'account_id' => $account->id, 'type' => 'expense']);

        $response = $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_cannot_delete_expense()
    {
        $household = Household::factory()->create();
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create(['household_id' => $household->id, 'account_id' => $account->id, 'type' => 'expense']);

        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);

        $response = $this->deleteJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_cannot_delete_expense()
    {
        [$householdA, $userA, $accountA] = $this->createHouseholdWithUser();
        [$householdB, $userB, $accountB] = $this->createHouseholdWithUser();

        $transactionB = Transaction::factory()->create(['household_id' => $householdB->id, 'account_id' => $accountB->id, 'type' => 'expense']);

        // User A tries to delete transaction B
        Sanctum::actingAs($userA);
        $response = $this->deleteJson("/api/v1/households/{$householdB->id}/transactions/{$transactionB->id}");

        $response->assertStatus(403);
    }
}
