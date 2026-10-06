<?php

namespace Tests\Feature\Transaction;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_view_own_transactions()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        // create two transactions
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '5000.00',
            'description' => 'Salary',
            'transaction_date' => '2026-10-01',
            'account_id' => null,
            'to_account_id' => null,
        ]);
        Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => '200.00',
            'description' => 'Groceries',
            'transaction_date' => '2026-10-02',
            'account_id' => null,
            'to_account_id' => null,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/transactions");
        $response->assertStatus(200)
                 ->assertJsonStructure(['transactions' => [['id','type','amount','description','transaction_date','account_id','to_account_id']]])
                 ->assertJsonCount(2, 'transactions');
    }

    /** @test */
    public function member_can_view_own_transactions()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '1000.00',
            'description' => 'Bonus',
            'transaction_date' => '2026-10-03',
            'account_id' => null,
            'to_account_id' => null,
        ]);

        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/households/{$household->id}/transactions");
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'transactions');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_transactions()
    {
        $household = Household::create(['name' => 'Family', 'description' => null]);
        $response = $this->getJson("/api/v1/households/{$household->id}/transactions");
        $response->assertStatus(401);
    }

    /** @test */
    public function user_from_other_household_is_forbidden()
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $householdA = Household::create(['name' => 'A', 'description' => null]);
        $householdB = Household::create(['name' => 'B', 'description' => null]);
        HouseholdMember::create(['user_id' => $ownerA->id, 'household_id' => $householdA->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $ownerB->id, 'household_id' => $householdB->id, 'role' => 'household_owner']);

        Transaction::create([
            'household_id' => $householdB->id,
            'type' => 'expense',
            'amount' => '300.00',
            'description' => 'Travel',
            'transaction_date' => '2026-10-04',
            'account_id' => null,
            'to_account_id' => null,
        ]);

        Sanctum::actingAs($ownerA);
        $response = $this->getJson("/api/v1/households/{$householdB->id}/transactions");
        $response->assertStatus(403);
    }

    /** @test */
    public function empty_transactions_return_empty_array()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Empty', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/transactions");
        $response->assertStatus(200)
                 ->assertExactJson(['transactions' => []]);
    }

    /** @test */
    public function response_excludes_unwanted_fields()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '1500.00',
            'description' => 'Freelance',
            'transaction_date' => '2026-10-05',
            'account_id' => null,
            'to_account_id' => null,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/transactions");
        $response->assertStatus(200);
        $data = $response->json('transactions')[0];
        $allowed = ['id','type','amount','description','transaction_date','account_id','to_account_id'];
        $this->assertEquals($allowed, array_keys($data));
    }

    /** @test */
    public function transactions_are_ordered_by_date_desc_then_id_desc()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        // same date, different ids
        $t1 = Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '100.00',
            'description' => 'A',
            'transaction_date' => '2026-10-06',
            'account_id' => null,
            'to_account_id' => null,
        ]);
        $t2 = Transaction::create([
            'household_id' => $household->id,
            'type' => 'income',
            'amount' => '200.00',
            'description' => 'B',
            'transaction_date' => '2026-10-06',
            'account_id' => null,
            'to_account_id' => null,
        ]);
        // later date
        $t3 = Transaction::create([
            'household_id' => $household->id,
            'type' => 'expense',
            'amount' => '50.00',
            'description' => 'C',
            'transaction_date' => '2026-10-07',
            'account_id' => null,
            'to_account_id' => null,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/transactions");
        $response->assertStatus(200);
        $data = $response->json('transactions');
        // Expect order: t3 (latest date), then t2 (same date newer id), then t1
        $this->assertEquals([$t3->id, $t2->id, $t1->id], array_column($data, 'id'));
    }
}

?>
