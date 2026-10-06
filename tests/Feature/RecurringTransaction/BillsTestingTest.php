<?php

namespace Tests\Feature\RecurringTransaction;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Notification;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillsTestingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected User $otherUser;
    protected Household $householdA;
    protected Household $householdB;
    protected Account $accountA;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Household A
        $this->owner = User::factory()->create();
        $this->householdA = Household::create(['name' => 'Keluarga A']);
        HouseholdMember::create([
            'user_id' => $this->owner->id,
            'household_id' => $this->householdA->id,
            'role' => 'household_owner',
        ]);

        $this->member = User::factory()->create();
        HouseholdMember::create([
            'user_id' => $this->member->id,
            'household_id' => $this->householdA->id,
            'role' => 'household_member',
        ]);

        $this->accountA = Account::create([
            'household_id' => $this->householdA->id,
            'name' => 'Bank A',
            'type' => 'bank',
            'initial_balance' => '1000000.00',
            'is_active' => true,
        ]);

        // Create Household B
        $this->otherUser = User::factory()->create();
        $this->householdB = Household::create(['name' => 'Keluarga B']);
        HouseholdMember::create([
            'user_id' => $this->otherUser->id,
            'household_id' => $this->householdB->id,
            'role' => 'household_owner',
        ]);
    }

    /** @test */
    public function upcoming_bill_is_detected_within_due_date_window_and_creates_notification()
    {
        $today = date('Y-m-d');

        $bill = RecurringTransaction::create([
            'household_id' => $this->householdA->id,
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => '750000.00',
            'description' => 'Tagihan Internet Speedy',
            'frequency' => 'monthly',
            'start_date' => $today,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/households/{$this->householdA->id}/recurring-transactions/upcoming-bills");

        $response->assertStatus(200)
            ->assertJsonPath('upcoming_bills.0.id', $bill->id)
            ->assertJsonPath('upcoming_bills.0.description', 'Tagihan Internet Speedy');

        // Check notification created for both household members
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'household_id' => $this->householdA->id,
            'type' => 'upcoming_bill',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->member->id,
            'household_id' => $this->householdA->id,
            'type' => 'upcoming_bill',
        ]);
    }

    /** @test */
    public function notification_is_not_duplicated_on_multiple_checks()
    {
        $today = date('Y-m-d');

        RecurringTransaction::create([
            'household_id' => $this->householdA->id,
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => '300000.00',
            'description' => 'Tagihan Air PDAM',
            'frequency' => 'monthly',
            'start_date' => $today,
            'is_active' => true,
        ]);

        // First check
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/households/{$this->householdA->id}/recurring-transactions/upcoming-bills");

        $initialCount = Notification::where('user_id', $this->owner->id)
            ->where('type', 'upcoming_bill')
            ->count();
        $this->assertEquals(1, $initialCount);

        // Second check
        $this->getJson("/api/v1/households/{$this->householdA->id}/recurring-transactions/upcoming-bills");

        $secondCount = Notification::where('user_id', $this->owner->id)
            ->where('type', 'upcoming_bill')
            ->count();
        $this->assertEquals(1, $secondCount);
    }

    /** @test */
    public function inactive_or_far_future_bills_do_not_produce_upcoming_notifications()
    {
        $farFuture = date('Y-m-d', strtotime('+30 days'));

        // Inactive bill
        RecurringTransaction::create([
            'household_id' => $this->householdA->id,
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => '100000.00',
            'description' => 'Tagihan Nonaktif',
            'frequency' => 'monthly',
            'start_date' => date('Y-m-d'),
            'is_active' => false,
        ]);

        // Far future bill
        RecurringTransaction::create([
            'household_id' => $this->householdA->id,
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => '200000.00',
            'description' => 'Tagihan Masa Depan Jauh',
            'frequency' => 'monthly',
            'start_date' => $farFuture,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/households/{$this->householdA->id}/recurring-transactions/upcoming-bills?days=7");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'upcoming_bills');
    }

    /** @test */
    public function household_isolation_prevents_cross_household_upcoming_bills_leak()
    {
        $today = date('Y-m-d');

        // Bill in Household A
        RecurringTransaction::create([
            'household_id' => $this->householdA->id,
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => '500000.00',
            'description' => 'Tagihan Listrik A',
            'frequency' => 'monthly',
            'start_date' => $today,
            'is_active' => true,
        ]);

        // User from Household B tries to fetch Household A's upcoming bills -> 403 Forbidden
        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/v1/households/{$this->householdA->id}/recurring-transactions/upcoming-bills");

        $response->assertStatus(403);

        // User B's notifications remain untouched
        $countB = Notification::where('user_id', $this->otherUser->id)->count();
        $this->assertEquals(0, $countB);
    }

    /** @test */
    public function unauthenticated_user_is_rejected_from_upcoming_bills()
    {
        $response = $this->getJson("/api/v1/households/{$this->householdA->id}/recurring-transactions/upcoming-bills");
        $response->assertStatus(401);
    }

    /** @test */
    public function existing_recurring_transaction_processing_generates_actual_transaction()
    {
        $recurring = RecurringTransaction::create([
            'household_id' => $this->householdA->id,
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => '250000.00',
            'description' => 'Langganan Streaming',
            'frequency' => 'monthly',
            'start_date' => date('Y-m-d'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/households/{$this->householdA->id}/recurring-transactions/{$recurring->id}/process", [
                'transaction_date' => date('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('transaction.type', 'expense')
            ->assertJsonPath('transaction.amount', '250000.00');

        $this->assertDatabaseHas('transactions', [
            'household_id' => $this->householdA->id,
            'account_id' => $this->accountA->id,
            'amount' => '250000.00',
        ]);
    }
}
