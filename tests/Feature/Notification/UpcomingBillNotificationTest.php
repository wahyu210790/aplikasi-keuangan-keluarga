<?php

namespace Tests\Feature\Notification;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Notification;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingBillNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Household $household;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Keluarga Tes Upcoming Bill']);

        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'name' => 'Kas',
            'type' => 'cash',
            'initial_balance' => '5000000.00',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function user_can_get_upcoming_bills_and_triggers_notifications()
    {
        $today = date('Y-m-d');
        $recurring = RecurringTransaction::create([
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '500000.00',
            'description' => 'Tagihan Listrik',
            'frequency' => 'monthly',
            'start_date' => $today,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/households/{$this->household->id}/recurring-transactions/upcoming-bills");

        $response->assertStatus(200)
            ->assertJsonPath('upcoming_bills.0.description', 'Tagihan Listrik');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'type' => 'upcoming_bill',
        ]);

        // Calling again does not duplicate notifications
        $this->getJson("/api/v1/households/{$this->household->id}/recurring-transactions/upcoming-bills");
        $count = Notification::where('user_id', $this->user->id)
            ->where('type', 'upcoming_bill')
            ->count();
        $this->assertEquals(1, $count);
    }
}
