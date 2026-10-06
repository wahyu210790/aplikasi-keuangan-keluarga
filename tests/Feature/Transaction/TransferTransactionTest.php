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

class TransferTransactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a household with a user and two active accounts.
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
        $sourceAccount = Account::factory()->active()->create(['household_id' => $household->id]);
        $destAccount = Account::factory()->active()->create(['household_id' => $household->id]);
        Sanctum::actingAs($user);

        return [$household, $user, $sourceAccount, $destAccount];
    }

    // ==========================================
    // TASK 7.16 — TRANSFER VALIDATION
    // ==========================================

    /** @test */
    public function amount_is_required_for_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function amount_must_be_numeric_for_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 'invalid-numeric',
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function amount_must_be_greater_than_zero_for_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $responseZero = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 0,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
        $responseZero->assertStatus(422)->assertJsonValidationErrors(['amount']);

        $responseNegative = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => -200,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
        $responseNegative->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function description_is_optional_and_max_255_for_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        // Too long description
        $longDescription = str_repeat('t', 256);
        $responseLong = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
            'description' => $longDescription,
        ]);
        $responseLong->assertStatus(422)->assertJsonValidationErrors(['description']);

        // Valid description
        $responseValid = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
            'description' => 'Transfer ke rekening tabungan',
        ]);
        $responseValid->assertStatus(201)
            ->assertJsonPath('transaction.description', 'Transfer ke rekening tabungan');
    }

    /** @test */
    public function transaction_date_is_required_and_must_be_valid_date_for_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        // Missing date
        $responseMissing = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
        $responseMissing->assertStatus(422)->assertJsonValidationErrors(['transaction_date']);

        // Invalid date
        $responseInvalid = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => 'not-a-date',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
        $responseInvalid->assertStatus(422)->assertJsonValidationErrors(['transaction_date']);
    }

    /** @test */
    public function account_id_is_required_for_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function to_account_id_is_required_for_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    /** @test */
    public function source_account_must_belong_to_same_household()
    {
        [$householdA, $userA, $sourceA, $destA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();
        $sourceB = Account::factory()->active()->create(['household_id' => $householdB->id]);

        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $sourceB->id,
            'to_account_id' => $destA->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function destination_account_must_belong_to_same_household()
    {
        [$householdA, $userA, $sourceA, $destA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();
        $destB = Account::factory()->active()->create(['household_id' => $householdB->id]);

        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $sourceA->id,
            'to_account_id' => $destB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    /** @test */
    public function source_account_must_be_active()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();
        $inactiveSource = Account::factory()->inactive()->create(['household_id' => $household->id]);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $inactiveSource->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function destination_account_must_be_active()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();
        $inactiveDest = Account::factory()->inactive()->create(['household_id' => $household->id]);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $inactiveDest->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    /** @test */
    public function source_and_destination_accounts_must_be_different()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $source->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    /** @test */
    public function type_must_be_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'invalid_type',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    // ==========================================
    // TASK 7.17 — TRANSFER API
    // ==========================================

    /** @test */
    public function owner_can_create_transfer()
    {
        [$household, $owner, $source, $dest] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 5000.00,
            'description' => 'Pindah Dana Kas ke Bank',
            'transaction_date' => '2026-10-05',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'transaction' => ['id', 'type', 'amount', 'description', 'transaction_date', 'account_id', 'to_account_id']])
            ->assertJsonPath('transaction.type', 'transfer')
            ->assertJsonPath('transaction.amount', '5000.00')
            ->assertJsonPath('transaction.account_id', $source->id)
            ->assertJsonPath('transaction.to_account_id', $dest->id);

        $this->assertDatabaseHas('transactions', [
            'household_id' => $household->id,
            'type' => 'transfer',
            'amount' => 5000.00,
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
    }

    /** @test */
    public function member_can_create_transfer()
    {
        [$household, $member, $source, $dest] = $this->createHouseholdWithUser('household_member');

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 1200.00,
            'description' => 'Transfer Anggaran',
            'transaction_date' => '2026-10-06',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('transaction.type', 'transfer');

        $this->assertDatabaseHas('transactions', [
            'household_id' => $household->id,
            'type' => 'transfer',
            'amount' => 1200.00,
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
    }

    /** @test */
    public function transfer_stores_type_as_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 300.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(201);
        $transactionId = $response->json('transaction.id');

        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'type' => 'transfer',
        ]);
    }

    /** @test */
    public function transfer_stores_source_and_destination_accounts_correctly()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 750.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(201);
        $transactionId = $response->json('transaction.id');

        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);
    }

    /** @test */
    public function transfer_stores_amount_transaction_date_and_description_correctly()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 888.88,
            'transaction_date' => '2026-10-04',
            'description' => 'Transfer Khusus',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('transaction.amount', '888.88')
            ->assertJsonPath('transaction.description', 'Transfer Khusus');

        $this->assertDatabaseHas('transactions', [
            'household_id' => $household->id,
            'amount' => 888.88,
            'description' => 'Transfer Khusus',
        ]);
    }

    /** @test */
    public function activity_log_created_exactly_once_on_successful_transfer()
    {
        [$household, $user, $source, $dest] = $this->createHouseholdWithUser();

        $this->assertDatabaseCount('activity_logs', 0);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 450.00,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(201);
        $transactionId = $response->json('transaction.id');

        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'action' => 'transaction_created',
            'entity_type' => 'transaction',
            'entity_id' => $transactionId,
        ]);
    }

    // ==========================================
    // SECURITY / TENANT ISOLATION
    // ==========================================

    /** @test */
    public function unauthenticated_user_cannot_create_transfer()
    {
        $household = Household::factory()->create();
        $source = Account::factory()->active()->create(['household_id' => $household->id]);
        $dest = Account::factory()->active()->create(['household_id' => $household->id]);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_cannot_create_transfer()
    {
        $household = Household::factory()->create();
        $source = Account::factory()->active()->create(['household_id' => $household->id]);
        $dest = Account::factory()->active()->create(['household_id' => $household->id]);
        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);

        $response = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $source->id,
            'to_account_id' => $dest->id,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_cannot_create_transfer()
    {
        [$householdA, $userA, $sourceA, $destA] = $this->createHouseholdWithUser();
        [$householdB, $userB, $sourceB, $destB] = $this->createHouseholdWithUser();

        // User A tries to create transfer in Household B
        Sanctum::actingAs($userA);
        $response = $this->postJson("/api/v1/households/{$householdB->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $sourceB->id,
            'to_account_id' => $destB->id,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function account_from_another_household_cannot_be_used_as_source()
    {
        [$householdA, $userA, $sourceA, $destA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();
        $sourceB = Account::factory()->active()->create(['household_id' => $householdB->id]);

        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $sourceB->id,
            'to_account_id' => $destA->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    /** @test */
    public function account_from_another_household_cannot_be_used_as_destination()
    {
        [$householdA, $userA, $sourceA, $destA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();
        $destB = Account::factory()->active()->create(['household_id' => $householdB->id]);

        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
            'transaction_date' => '2026-10-01',
            'account_id' => $sourceA->id,
            'to_account_id' => $destB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    /** @test */
    public function request_body_household_id_cannot_override_route_household()
    {
        [$householdA, $userA, $sourceA, $destA] = $this->createHouseholdWithUser();
        $householdB = Household::factory()->create();

        $response = $this->postJson("/api/v1/households/{$householdA->id}/transactions", [
            'type' => 'transfer',
            'amount' => 350,
            'transaction_date' => '2026-10-01',
            'account_id' => $sourceA->id,
            'to_account_id' => $destA->id,
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
}
