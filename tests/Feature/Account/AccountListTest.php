<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_view_own_accounts()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        // create two accounts for this household
        Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '5000000.00',
            'is_active' => true,
        ]);
        Account::create([
            'household_id' => $household->id,
            'name' => 'Cash',
            'type' => 'cash',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/accounts");
        $response->assertStatus(200)
                 ->assertJsonStructure(['accounts' => [['id','name','type','initial_balance','is_active']]])
                 ->assertJsonCount(2, 'accounts');
    }

    /** @test */
    public function member_can_view_own_accounts()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Account::create([
            'household_id' => $household->id,
            'name' => 'Ewallet',
            'type' => 'e_wallet',
            'initial_balance' => '10000.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/households/{$household->id}/accounts");
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'accounts');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_accounts()
    {
        $household = Household::create(['name' => 'Family', 'description' => null]);
        $response = $this->getJson("/api/v1/households/{$household->id}/accounts");
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

        Account::create([
            'household_id' => $householdB->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($ownerA);
        $response = $this->getJson("/api/v1/households/{$householdB->id}/accounts");
        $response->assertStatus(403);
    }

    /** @test */
    public function household_without_accounts_returns_empty_array()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Empty', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/accounts");
        $response->assertStatus(200)
                 ->assertExactJson(['accounts' => []]);
        }

    /** @test */
    public function response_excludes_unwanted_fields()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '5000.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/accounts");
        $response->assertStatus(200);
        $data = $response->json('accounts');
        $this->assertCount(1, $data);
        $allowed = ['id', 'household_id', 'user_id', 'user_name', 'name', 'type', 'initial_balance', 'is_active'];
        $this->assertEquals($allowed, array_keys($data[0]));
    }

    /** @test */
    public function inactive_account_is_still_returned()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Account::create([
            'household_id' => $household->id,
            'name' => 'Old Account',
            'type' => 'cash',
            'initial_balance' => '0.00',
            'is_active' => false,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/accounts");
        $response->assertStatus(200)
                 ->assertJsonFragment(['is_active' => false]);
    }

    /** @test */
    public function only_accounts_of_requested_household_are_returned()
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $householdA = Household::create(['name' => 'A', 'description' => null]);
        $householdB = Household::create(['name' => 'B', 'description' => null]);
        HouseholdMember::create(['user_id' => $ownerA->id, 'household_id' => $householdA->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $ownerB->id, 'household_id' => $householdB->id, 'role' => 'household_owner']);

        $accountA = Account::create([
            'household_id' => $householdA->id,
            'name' => 'A-Acc',
            'type' => 'bank',
            'initial_balance' => '1000.00',
            'is_active' => true,
        ]);
        $accountB = Account::create([
            'household_id' => $householdB->id,
            'name' => 'B-Acc',
            'type' => 'cash',
            'initial_balance' => '2000.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($ownerA);
        $response = $this->getJson("/api/v1/households/{$householdA->id}/accounts");
        $response->assertStatus(200);
        $data = $response->json('accounts');
        $this->assertCount(1, $data);
        $this->assertEquals($accountA->id, $data[0]['id']);
    }

    /** @test */
    public function accounts_are_ordered_by_id_ascending()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        // Create accounts out of order (insert with explicit IDs via factory is not possible, so we rely on auto‑increment)
        $first = Account::create([
            'household_id' => $household->id,
            'name' => 'First',
            'type' => 'bank',
            'initial_balance' => '10.00',
            'is_active' => true,
        ]);
        $second = Account::create([
            'household_id' => $household->id,
            'name' => 'Second',
            'type' => 'cash',
            'initial_balance' => '20.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/accounts");
        $response->assertStatus(200);
        $data = $response->json('accounts');
        $this->assertEquals([$first->id, $second->id], array_column($data, 'id'));
    }
}
