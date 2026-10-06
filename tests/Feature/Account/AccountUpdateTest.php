<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_update_account_successfully()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '1000.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $payload = ['name' => 'BCA Utama', 'type' => 'bank', 'initial_balance' => 2000];
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'account' => ['id', 'name', 'type', 'initial_balance', 'is_active']]);
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'BCA Utama',
            'type' => 'bank',
            'initial_balance' => number_format(2000, 2, '.', ''),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'action' => 'account_updated',
            'entity_type' => 'account',
            'entity_id' => $account->id,
        ]);
    }

    /** @test */
    public function member_can_update_account_successfully()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Cash',
            'type' => 'cash',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($member);
        $payload = ['name' => 'Cash Updated'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(200);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Cash Updated']);
    }

    /** @test */
    public function unauthenticated_user_cannot_update_account()
    {
        $household = Household::create(['name' => 'Family', 'description' => null]);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '1000.00',
            'is_active' => true,
        ]);
        $payload = ['name' => 'Attempt'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(401);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'account_updated']);
    }

    /** @test */
    public function cross_household_access_is_forbidden()
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $householdA = Household::create(['name' => 'A', 'description' => null]);
        $householdB = Household::create(['name' => 'B', 'description' => null]);
        HouseholdMember::create(['user_id' => $ownerA->id, 'household_id' => $householdA->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $ownerB->id, 'household_id' => $householdB->id, 'role' => 'household_owner']);
        $accountB = Account::create([
            'household_id' => $householdB->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '1000.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($ownerA);
        $payload = ['name' => 'Hack'];
        $response = $this->patchJson("/api/v1/households/{$householdA->id}/accounts/{$accountB->id}", $payload);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('accounts', ['id' => $accountB->id, 'name' => 'Hack']);
    }

    /** @test */
    public function name_can_be_trimmed_and_updated()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $payload = ['name' => "  NewName \t\n"]; // will be trimmed to NewName
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(200);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'NewName']);
    }

    /** @test */
    public function empty_name_after_trim_is_rejected()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $payload = ['name' => "   \n\t"]; // becomes empty after trim
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Old']);
    }

    /** @test */
    public function type_validation_allows_allowed_values()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        foreach (['bank', 'cash', 'e_wallet'] as $type) {
            $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", ['type' => $type]);
            $response->assertStatus(200);
            $this->assertDatabaseHas('accounts', ['id' => $account->id, 'type' => $type]);
        }
    }

    /** @test */
    public function invalid_type_is_rejected()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", ['type' => 'invalid']);
        $response->assertStatus(422);
    }

    /** @test */
    public function initial_balance_can_be_updated_and_formatted()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", ['initial_balance' => 1234.5]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'initial_balance' => number_format(1234.5, 2, '.', '')]);
    }

    /** @test */
    public function initial_balance_zero_is_allowed()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '100.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", ['initial_balance' => 0]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'initial_balance' => '0.00']);
    }

    /** @test */
    public function negative_initial_balance_is_rejected()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", ['initial_balance' => -5]);
        $response->assertStatus(422);
    }

    /** @test */
    public function non_numeric_initial_balance_is_rejected()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", ['initial_balance' => 'abc']);
        $response->assertStatus(422);
    }

    /** @test */
    public function partial_update_only_changes_provided_fields()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Original',
            'type' => 'bank',
            'initial_balance' => '500.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $payload = ['type' => 'cash'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(200);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'type' => 'cash', 'name' => 'Original', 'initial_balance' => '500.00']);
    }

    /** @test */
    public function empty_patch_is_rejected()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", []);
        $response->assertStatus(422);
    }

    /** @test */
    public function prohibited_fields_cannot_be_updated_and_no_log_created()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $payload = ['household_id' => 999, 'is_active' => false, 'balance' => 100];
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'household_id' => $household->id, 'is_active' => true]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'account_updated']);
    }

    /** @test */
    public function response_contains_only_allowed_fields()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Acc',
            'type' => 'bank',
            'initial_balance' => '0.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $payload = ['name' => 'NewName'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}", $payload);
        $response->assertStatus(200);
        $data = $response->json('account');
        $allowed = ['id', 'name', 'type', 'initial_balance', 'is_active'];
        $this->assertEquals($allowed, array_keys($data));
    }
}
