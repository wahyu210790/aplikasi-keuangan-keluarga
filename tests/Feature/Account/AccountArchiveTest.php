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

class AccountArchiveTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_archive_active_account()
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
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive");
        $response->assertStatus(200)
                 ->assertJsonPath('account.is_active', false);

        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'action' => 'account_archived',
            'entity_type' => 'account',
            'entity_id' => $account->id,
        ]);
    }

    /** @test */
    public function member_can_archive_active_account()
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
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive");
        $response->assertStatus(200);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'is_active' => false]);
    }

    /** @test */
    public function unauthenticated_user_cannot_archive()
    {
        $household = Household::create(['name' => 'Family', 'description' => null]);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '1000.00',
            'is_active' => true,
        ]);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive");
        $response->assertStatus(401);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'account_archived']);
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
        $response = $this->patchJson("/api/v1/households/{$householdA->id}/accounts/{$accountB->id}/archive");
        $response->assertStatus(403);
        $this->assertDatabaseHas('accounts', ['id' => $accountB->id, 'is_active' => true]);
    }

    /** @test */
    public function archive_is_idempotent_and_does_not_duplicate_log()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '500.00',
            'is_active' => false,
        ]);
        Sanctum::actingAs($owner);
        // first call (already archived)
        $response1 = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive");
        $response1->assertStatus(200)
                  ->assertJsonPath('account.is_active', false);
        // second call
        $response2 = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive");
        $response2->assertStatus(200);
        // only one log entry should exist
        $this->assertEquals(0, ActivityLog::where('action', 'account_archived')->where('entity_id', $account->id)->count());
    }

    /** @test */
    public function archive_does_not_change_other_fields_and_keeps_row()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '1234.56',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive");
        $response->assertStatus(200);
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '1234.56',
            'is_active' => false,
        ]);
    }

    /** @test */
    public function request_body_fields_are_prohibited()
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
        $payload = ['name' => 'Hacked', 'is_active' => true];
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive", $payload);
        $response->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'BCA', 'is_active' => true]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'account_archived']);
    }

    /** @test */
    public function response_contains_only_allowed_fields()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => '500.00',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/accounts/{$account->id}/archive");
        $response->assertStatus(200);
        $data = $response->json('account');
        $expected = ['id', 'user_id', 'user_name', 'name', 'type', 'initial_balance', 'is_active'];
        $this->assertEquals($expected, array_keys($data));
    }
}
