<?php

namespace Tests\Feature\RecurringTransaction;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
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
            ->assertJsonPath('recurring_transactions.0.description', 'Tagihan Listrik')
            ->assertJsonPath('recurring_transactions.0.is_paid_current_period', false)
            ->assertJsonPath('recurring_transactions.0.payment_status', 'unpaid');
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

    public function test_payment_creates_exactly_one_transaction(): void
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
            ->assertJsonPath('message', 'Transaksi berhasil dicatat.');

        $this->assertEquals(1, Transaction::where('household_id', $this->household->id)->count());

        $recurring->refresh();
        $this->assertNotNull($recurring->last_generated_at);
    }

    public function test_duplicate_api_request_for_same_period_prevented(): void
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
            'description' => 'Tagihan Internet',
            'is_active' => true,
        ]);

        // First request - Success
        $res1 = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}/process", [
            'transaction_date' => '2026-10-05',
        ]);
        $res1->assertStatus(201);

        // Second request for same month (period duplicate) - Error 422
        $res2 = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}/process", [
            'transaction_date' => '2026-10-10',
        ]);
        $res2->assertStatus(422)
            ->assertJsonPath('message', 'Jadwal rutin ini sudah diproses/dibayar untuk periode ini.');

        $this->assertEquals(1, Transaction::where('household_id', $this->household->id)->count());
    }

    public function test_next_period_can_be_paid_again(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = RecurringTransaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 300000,
            'frequency' => 'monthly',
            'start_date' => '2026-09-01',
            'last_generated_at' => '2026-09-05', // Paid in September
            'description' => 'SPP Sekolah',
            'is_active' => true,
        ]);

        // October payment (next period)
        $response = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}/process", [
            'transaction_date' => '2026-10-05',
        ]);

        $response->assertStatus(201);

        $recurring->refresh();
        $this->assertEquals('2026-10-05', $recurring->last_generated_at->format('Y-m-d'));
        $this->assertEquals(1, Transaction::where('household_id', $this->household->id)->count());
    }

    public function test_failed_transaction_does_not_change_status_to_paid(): void
    {
        Sanctum::actingAs($this->user);

        // Deactivate account
        $this->account->update(['is_active' => false]);

        $recurring = RecurringTransaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 200000,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'description' => 'Tagihan Asuransi',
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}/process");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Rekening sumber tidak valid atau tidak aktif.');

        $recurring->refresh();
        $this->assertNull($recurring->last_generated_at);
        $this->assertEquals(0, Transaction::where('household_id', $this->household->id)->count());
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

        $response2 = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}/process");
        $response2->assertStatus(403);

        $response3 = $this->deleteJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}");
        $response3->assertStatus(403);
    }

    public function test_income_recurring_transaction_works(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = RecurringTransaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'income',
            'amount' => 5000000,
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'description' => 'Gaji Rutin',
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/households/{$this->household->id}/recurring-transactions/{$recurring->id}/process");

        $response->assertStatus(201)
            ->assertJsonPath('transaction.type', 'income');

        $this->assertDatabaseHas('transactions', [
            'household_id' => $this->household->id,
            'type' => 'income',
            'amount' => 5000000,
        ]);
    }
}
