<?php

namespace Tests\Feature\RecurringTransaction;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringTransactionFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Test Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'name' => 'Bank BCA',
            'type' => 'bank',
            'current_balance' => 1000000,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Utilitas',
            'type' => 'expense',
            'is_active' => true,
        ]);
    }

    public function test_user_can_list_recurring_transactions(): void
    {
        Sanctum::actingAs($this->user);

        RecurringTransaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 150000,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'description' => 'Tagihan Listrik',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/recurring-transactions");

        $response->assertStatus(200)
            ->assertJsonPath('recurring_transactions.0.description', 'Tagihan Listrik');
    }

    public function test_user_can_create_recurring_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'type' => 'expense',
            'amount' => 1500000,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'description' => 'Sewa Rumah',
            'is_active' => true,
        ];

        $response = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('recurring_transaction.description', 'Sewa Rumah');

        $this->assertDatabaseHas('recurring_transactions', [
            'household_id' => $this->household->id,
            'description' => 'Sewa Rumah',
            'frequency' => 'monthly',
        ]);
    }

    public function test_user_can_process_recurring_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = RecurringTransaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 200000,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'description' => 'Tagihan Air',
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}/process");

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Transaction generated successfully from recurring template.');

        $this->assertDatabaseHas('transactions', [
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'amount' => 200000,
            'type' => 'expense',
        ]);

        $recurring->refresh();
        $this->assertNotNull($recurring->last_generated_at);
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

        $recurring = RecurringTransaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 100000,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'description' => 'Tagihan Privat',
            'is_active' => true,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/recurring-transactions");
        $response->assertStatus(403);

        $response2 = $this->deleteJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}");
        $response2->assertStatus(403);
    }
}
