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

class AccountOwnershipQuotaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function account_ownership_is_saved_on_creation()
    {
        $owner = User::factory()->create(['name' => 'Budi Santoso']);
        $household = Household::create(['name' => 'Keluarga Budi']);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => 'BCA Budi',
            'type' => 'bank',
            'initial_balance' => 5000000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('accounts', [
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'name' => 'BCA Budi',
        ]);
    }

    /** @test */
    public function member_can_create_their_own_account()
    {
        $owner = User::factory()->create(['name' => 'Budi']);
        $member = User::factory()->create(['name' => 'Siti']);
        $household = Household::create(['name' => 'Keluarga Budi']);

        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Sanctum::actingAs($member);

        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => 'BCA Siti',
            'type' => 'bank',
            'initial_balance' => 2000000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('accounts', [
            'household_id' => $household->id,
            'user_id' => $member->id,
            'name' => 'BCA Siti',
        ]);
    }

    /** @test */
    public function maximum_3_active_accounts_per_member_is_enforced()
    {
        $member = User::factory()->create(['name' => 'Budi']);
        $household = Household::create(['name' => 'Keluarga Budi']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($member);

        // Create 3 active accounts
        for ($i = 1; $i <= 3; $i++) {
            $res = $this->postJson("/api/v1/households/{$household->id}/accounts", [
                'name' => "Account {$i}",
                'type' => 'bank',
                'initial_balance' => 100000,
            ]);
            $res->assertStatus(201);
        }

        // Attempt to create 4th account
        $fourthRes = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => 'Account 4',
            'type' => 'bank',
            'initial_balance' => 100000,
        ]);

        $fourthRes->assertStatus(422);
        $fourthRes->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function archived_account_does_not_count_towards_quota()
    {
        $member = User::factory()->create(['name' => 'Budi']);
        $household = Household::create(['name' => 'Keluarga Budi']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($member);

        // Create 3 accounts
        $acc1 = Account::create(['household_id' => $household->id, 'user_id' => $member->id, 'name' => 'Acc 1', 'type' => 'bank', 'initial_balance' => 100, 'is_active' => true]);
        $acc2 = Account::create(['household_id' => $household->id, 'user_id' => $member->id, 'name' => 'Acc 2', 'type' => 'bank', 'initial_balance' => 100, 'is_active' => true]);
        $acc3 = Account::create(['household_id' => $household->id, 'user_id' => $member->id, 'name' => 'Acc 3', 'type' => 'bank', 'initial_balance' => 100, 'is_active' => true]);

        // Archive acc1
        $archiveRes = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$acc1->id}/archive");
        $archiveRes->assertStatus(200);

        // Now create 4th account (should succeed since active count is now 2)
        $newRes = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => 'Acc 4 New',
            'type' => 'cash',
            'initial_balance' => 50000,
        ]);

        $newRes->assertStatus(201);
    }

    /** @test */
    public function member_cannot_update_or_archive_another_members_account()
    {
        $owner = User::factory()->create(['name' => 'Budi']);
        $memberA = User::factory()->create(['name' => 'Member A']);
        $memberB = User::factory()->create(['name' => 'Member B']);
        $household = Household::create(['name' => 'Keluarga']);

        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $memberA->id, 'household_id' => $household->id, 'role' => 'household_member']);
        HouseholdMember::create(['user_id' => $memberB->id, 'household_id' => $household->id, 'role' => 'household_member']);

        $accA = Account::create([
            'household_id' => $household->id,
            'user_id' => $memberA->id,
            'name' => 'BCA Member A',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);

        // Member B attempts to edit Member A's account
        Sanctum::actingAs($memberB);
        $editRes = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$accA->id}", [
            'name' => 'Hacked Name',
        ]);
        $editRes->assertStatus(403);

        // Member B attempts to archive Member A's account
        $archiveRes = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$accA->id}/archive");
        $archiveRes->assertStatus(403);
    }

    /** @test */
    public function household_owner_can_manage_any_household_account()
    {
        $owner = User::factory()->create(['name' => 'Owner Budi']);
        $member = User::factory()->create(['name' => 'Member Siti']);
        $household = Household::create(['name' => 'Keluarga']);

        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        $accSiti = Account::create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'name' => 'BCA Siti',
            'type' => 'bank',
            'initial_balance' => 1000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        // Owner edits Siti's account
        $editRes = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$accSiti->id}", [
            'name' => 'BCA Siti Updated',
        ]);
        $editRes->assertStatus(200);

        // Owner archives Siti's account
        $archiveRes = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$accSiti->id}/archive");
        $archiveRes->assertStatus(200);
    }

    /** @test */
    public function cross_household_transfer_or_account_access_is_rejected()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $householdA = Household::create(['name' => 'Household A']);
        $householdB = Household::create(['name' => 'Household B']);

        HouseholdMember::create(['user_id' => $userA->id, 'household_id' => $householdA->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $userB->id, 'household_id' => $householdB->id, 'role' => 'household_owner']);

        $accB = Account::create([
            'household_id' => $householdB->id,
            'user_id' => $userB->id,
            'name' => 'Acc B',
            'type' => 'bank',
            'initial_balance' => 5000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($userA);

        $res = $this->patchJson("/api/v1/households/{$householdB->id}/accounts/{$accB->id}", ['name' => 'Test']);
        $res->assertStatus(403);
    }

    /** @test */
    public function transfer_between_members_budi_and_siti_correctly_updates_derived_balances()
    {
        $budi = User::factory()->create(['name' => 'Budi']);
        $siti = User::factory()->create(['name' => 'Siti']);
        $household = Household::create(['name' => 'Keluarga Budi & Siti']);

        HouseholdMember::create(['user_id' => $budi->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $siti->id, 'household_id' => $household->id, 'role' => 'household_member']);

        $accBudi = Account::create([
            'household_id' => $household->id,
            'user_id' => $budi->id,
            'name' => 'BCA Budi',
            'type' => 'bank',
            'initial_balance' => 2000000,
            'is_active' => true,
        ]);

        $accSiti = Account::create([
            'household_id' => $household->id,
            'user_id' => $siti->id,
            'name' => 'BCA Siti',
            'type' => 'bank',
            'initial_balance' => 1000000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($budi);

        // Transfer 500,000 from Budi to Siti
        $transferRes = $this->postJson("/api/v1/households/{$household->id}/transactions", [
            'type' => 'transfer',
            'amount' => 500000,
            'description' => 'Transfer Uang Belanja Budi -> Siti',
            'transaction_date' => date('Y-m-d'),
            'account_id' => $accBudi->id,
            'to_account_id' => $accSiti->id,
        ]);

        $transferRes->assertStatus(201);

        // Verify derived balances
        $accBudi->refresh();
        $accSiti->refresh();

        $this->assertEquals('1500000.00', $accBudi->calculateBalance());
        $this->assertEquals('1500000.00', $accSiti->calculateBalance());
    }
}
