<?php

namespace Tests\Feature\Transaction;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthorized_user_cannot_update_transaction()
    {
        $household = Household::factory()->create();
        $transaction = Transaction::factory()->create(['household_id' => $household->id]);
        $payload = ['type' => 'income'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", $payload);
        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_cannot_update_transaction()
    {
        $household = Household::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $transaction = Transaction::factory()->create(['household_id' => $household->id]);
        $payload = ['type' => 'income'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", $payload);
        $response->assertStatus(403);
    }

    /** @test */
    public function cannot_update_transaction_of_another_household()
    {
        $household = Household::factory()->create();
        $otherHousehold = Household::factory()->create();
        $member = User::factory()->create();
        $household->members()->create(['user_id' => $member->id, 'role' => 'household_member']);
        Sanctum::actingAs($member);
        $transaction = Transaction::factory()->create(['household_id' => $otherHousehold->id]);
        $payload = ['type' => 'income'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", $payload);
        $response->assertStatus(403);
    }

    /** @test */
    public function validation_requires_at_least_one_updatable_field()
    {
        $household = Household::factory()->create();
        $member = User::factory()->create();
        $household->members()->create(['user_id' => $member->id, 'role' => 'household_member']);
        Sanctum::actingAs($member);
        $transaction = Transaction::factory()->create(['household_id' => $household->id]);
        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", []);
        $response->assertStatus(422)->assertJsonFragment(['message' => 'At least one updatable field must be provided.']);
    }

    /** @test */
    public function successful_update_of_allowed_fields()
    {
        $household = Household::factory()->create();
        $member = User::factory()->create();
        $household->members()->create(['user_id' => $member->id, 'role' => 'household_member']);
        Sanctum::actingAs($member);
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $toAccount = Account::factory()->active()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'type' => 'income',
            'account_id' => $account->id,
            'to_account_id' => null,
        ]);
        $payload = [
            'type' => 'transfer',
            'amount' => 150.75,
            'account_id' => $account->id,
            'to_account_id' => $toAccount->id,
            'description' => 'Updated transfer',
            'transaction_date' => '2023-01-01',
        ];
        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", $payload);
        $response->assertStatus(200);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => 'transfer',
            'amount' => number_format(150.75, 2, '.', ''),
            'account_id' => $account->id,
            'to_account_id' => $toAccount->id,
            'description' => 'Updated transfer',
        ]);
        $response->assertJsonFragment(['message' => 'Transaction updated successfully.']);
    }

    /** @test */
    public function cannot_assign_inactive_account()
    {
        $household = Household::factory()->create();
        $member = User::factory()->create();
        $household->members()->create(['user_id' => $member->id, 'role' => 'household_member']);
        Sanctum::actingAs($member);
        $activeAccount = Account::factory()->active()->create(['household_id' => $household->id]);
        $inactiveAccount = Account::factory()->inactive()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'type' => 'income',
            'account_id' => $activeAccount->id,
        ]);
        $payload = ['account_id' => $inactiveAccount->id];
        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", $payload);
        $response->assertStatus(422);
    }

    /** @test */
    public function activity_log_is_created_on_successful_update()
    {
        $household = Household::factory()->create();
        $member = User::factory()->create();
        $household->members()->create(['user_id' => $member->id, 'role' => 'household_member']);
        Sanctum::actingAs($member);
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $transaction = Transaction::factory()->create([
            'household_id' => $household->id,
            'type' => 'income',
            'account_id' => $account->id,
        ]);
        $payload = ['description' => 'New description'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/transactions/{$transaction->id}", $payload);
        $response->assertStatus(200);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'transaction_updated',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
        ]);
    }
}
