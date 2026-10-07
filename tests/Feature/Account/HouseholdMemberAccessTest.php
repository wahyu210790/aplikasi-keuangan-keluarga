<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;
    private User $owner;
    private User $adult;
    private User $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::create(['name' => 'Keluarga Wahyu', 'description' => null]);

        $this->owner = User::factory()->create(['name' => 'Wahyu', 'email' => 'wahyu@example.com']);
        HouseholdMember::create([
            'user_id' => $this->owner->id,
            'household_id' => $this->household->id,
            'role' => HouseholdMember::ROLE_OWNER,
        ]);

        $this->adult = User::factory()->create(['name' => 'Istri', 'email' => 'istri@example.com']);
        HouseholdMember::create([
            'user_id' => $this->adult->id,
            'household_id' => $this->household->id,
            'role' => HouseholdMember::ROLE_ADULT,
        ]);

        $this->child = User::factory()->create(['name' => 'Anak', 'email' => 'anak@example.com']);
        HouseholdMember::create([
            'user_id' => $this->child->id,
            'household_id' => $this->household->id,
            'role' => HouseholdMember::ROLE_CHILD,
        ]);
    }

    /** @test */
    public function test_01_household_owner_functions_properly()
    {
        Sanctum::actingAs($this->owner);
        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $response->assertStatus(200);
    }

    /** @test */
    public function test_02_existing_household_member_migrated_or_compatible_as_adult_member()
    {
        $legacyUser = User::factory()->create();
        $legacyMember = HouseholdMember::create([
            'user_id' => $legacyUser->id,
            'household_id' => $this->household->id,
            'role' => 'household_member',
        ]);

        $this->assertTrue($legacyMember->isAdult());
    }

    /** @test */
    public function test_03_adult_member_can_access_household()
    {
        Sanctum::actingAs($this->adult);
        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $response->assertStatus(200);
    }

    /** @test */
    public function test_04_child_member_restricted_access()
    {
        Sanctum::actingAs($this->child);
        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $response->assertStatus(200);
    }

    /** @test */
    public function test_05_owner_can_view_all_household_accounts()
    {
        Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'name' => 'BCA Wahyu',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);
        Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->adult->id,
            'name' => 'BCA Istri',
            'type' => 'bank',
            'initial_balance' => 500,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('accounts'));
    }

    /** @test */
    public function test_06_adult_member_can_view_shared_household_accounts()
    {
        Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'name' => 'BCA Wahyu',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);
        Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->adult->id,
            'name' => 'BCA Istri',
            'type' => 'bank',
            'initial_balance' => 500,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adult);
        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('accounts'));
    }

    /** @test */
    public function test_07_child_only_views_accessible_or_own_accounts()
    {
        Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'name' => 'BCA Wahyu',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);
        $childAcc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->child->id,
            'name' => 'Dompet Anak',
            'type' => 'cash',
            'initial_balance' => 50,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->child);
        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $response->assertStatus(200);
        $accounts = $response->json('accounts');
        $this->assertCount(1, $accounts);
        $this->assertEquals($childAcc->id, $accounts[0]['id']);
    }

    /** @test */
    public function test_08_member_cannot_access_other_household_accounts()
    {
        $otherHousehold = Household::create(['name' => 'Other Family']);
        $otherAccount = Account::create([
            'household_id' => $otherHousehold->id,
            'user_id' => User::factory()->create()->id,
            'name' => 'Other Acc',
            'type' => 'bank',
            'initial_balance' => 100,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adult);
        $response = $this->patchJson("/api/v1/households/{$this->household->id}/accounts/{$otherAccount->id}", [
            'name' => 'Hacked',
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function test_09_account_ownership_remains_correct()
    {
        Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->adult->id,
            'name' => 'BCA Istri',
            'type' => 'bank',
            'initial_balance' => 100,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adult);
        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $this->assertEquals($this->adult->id, $response->json('accounts.0.user_id'));
        $this->assertEquals('Istri', $response->json('accounts.0.user_name'));
    }

    /** @test */
    public function test_10_quota_3_active_accounts_per_member_enforced()
    {
        for ($i = 1; $i <= 3; $i++) {
            Account::create([
                'household_id' => $this->household->id,
                'user_id' => $this->adult->id,
                'name' => "Acc {$i}",
                'type' => 'bank',
                'initial_balance' => 100,
                'is_active' => true,
            ]);
        }

        Sanctum::actingAs($this->adult);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/accounts", [
            'name' => 'Acc 4',
            'type' => 'bank',
            'initial_balance' => 100,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_11_archived_account_is_exempt_from_quota()
    {
        for ($i = 1; $i <= 3; $i++) {
            Account::create([
                'household_id' => $this->household->id,
                'user_id' => $this->adult->id,
                'name' => "Acc {$i}",
                'type' => 'bank',
                'initial_balance' => 100,
                'is_active' => $i === 1 ? false : true,
            ]);
        }

        Sanctum::actingAs($this->adult);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/accounts", [
            'name' => 'New Active Acc',
            'type' => 'bank',
            'initial_balance' => 100,
        ]);
        $response->assertStatus(201);
    }

    /** @test */
    public function test_12_adult_member_can_create_transaction_on_accessible_account()
    {
        $acc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'name' => 'BCA Wahyu',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adult);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/transactions", [
            'type' => 'expense',
            'amount' => 100,
            'transaction_date' => now()->toDateString(),
            'account_id' => $acc->id,
        ]);
        $response->assertStatus(201);
    }

    /** @test */
    public function test_13_child_member_can_create_transaction_on_accessible_account()
    {
        $childAcc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->child->id,
            'name' => 'Dompet Anak',
            'type' => 'cash',
            'initial_balance' => 100,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->child);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/transactions", [
            'type' => 'expense',
            'amount' => 10,
            'transaction_date' => now()->toDateString(),
            'account_id' => $childAcc->id,
        ]);
        $response->assertStatus(201);
    }

    /** @test */
    public function test_14_transaction_on_inaccessible_account_rejected()
    {
        $ownerAcc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'name' => 'BCA Private Wahyu',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->child);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/transactions", [
            'type' => 'expense',
            'amount' => 50,
            'transaction_date' => now()->toDateString(),
            'account_id' => $ownerAcc->id,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_15_cross_household_transaction_rejected()
    {
        $otherHousehold = Household::create(['name' => 'Other Family']);
        $otherAcc = Account::create([
            'household_id' => $otherHousehold->id,
            'user_id' => User::factory()->create()->id,
            'name' => 'Other Acc',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adult);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/transactions", [
            'type' => 'expense',
            'amount' => 50,
            'transaction_date' => now()->toDateString(),
            'account_id' => $otherAcc->id,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_16_17_18_19_transfer_wahyu_to_istri_successful_single_entry_balance_updated()
    {
        $wahyuAcc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'name' => 'BCA Wahyu',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);
        $istriAcc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->adult->id,
            'name' => 'BCA Istri',
            'type' => 'bank',
            'initial_balance' => 200,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 500,
            'transaction_date' => now()->toDateString(),
            'account_id' => $wahyuAcc->id,
            'to_account_id' => $istriAcc->id,
            'description' => 'Transfer Uang Belanja',
        ]);

        $response->assertStatus(201);

        // Verify single entry
        $this->assertEquals(1, Transaction::where('household_id', $this->household->id)->count());

        // Verify derived balances
        $this->assertEquals('500.00', $wahyuAcc->fresh()->calculateBalance());
        $this->assertEquals('700.00', $istriAcc->fresh()->calculateBalance());
    }

    /** @test */
    public function test_20_transfer_to_inaccessible_account_rejected()
    {
        $childAcc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->child->id,
            'name' => 'Dompet Anak',
            'type' => 'cash',
            'initial_balance' => 50,
            'is_active' => true,
        ]);
        $ownerAcc = Account::create([
            'household_id' => $this->household->id,
            'user_id' => $this->owner->id,
            'name' => 'BCA Private Wahyu',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->child);
        $response = $this->postJson("/api/v1/households/{$this->household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 10,
            'transaction_date' => now()->toDateString(),
            'account_id' => $childAcc->id,
            'to_account_id' => $ownerAcc->id,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_21_super_admin_without_membership_cannot_access_financial_endpoint()
    {
        $superAdmin = User::factory()->create(['global_role' => 'super_admin']);
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/accounts");
        $response->assertStatus(403);
    }

    /** @test */
    public function test_22_super_admin_can_access_admin_panel()
    {
        $superAdmin = User::factory()->create(['global_role' => 'super_admin']);
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/admin/stats');
        $response->assertStatus(200);
    }
}
