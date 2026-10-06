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

class AccountCreateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_create_account_successfully()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($owner);
        $payload = [
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 5000000,
        ];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'account' => ['id', 'name', 'type', 'initial_balance', 'is_active']
                 ]);
        $this->assertDatabaseHas('accounts', [
            'household_id' => $household->id,
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => number_format(5000000, 2, '.', ''),
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'action' => 'account_created',
            'entity_type' => 'account',
        ]);
    }

    /** @test */
    public function member_can_create_account_successfully()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Sanctum::actingAs($member);
        $payload = ['name' => 'Cash', 'type' => 'cash', 'initial_balance' => 0];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('accounts', [
            'household_id' => $household->id,
            'name' => 'Cash',
            'type' => 'cash',
            'initial_balance' => number_format(0, 2, '.', ''),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_account()
    {
        $household = Household::create(['name' => 'Family', 'description' => null]);
        $payload = ['name' => 'BCA', 'type' => 'bank', 'initial_balance' => 100];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(401);
        $this->assertDatabaseMissing('accounts', ['name' => 'BCA']);
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

        Sanctum::actingAs($ownerA);
        $payload = ['name' => 'BCA', 'type' => 'bank', 'initial_balance' => 10];
        $response = $this->postJson("/api/v1/households/{$householdB->id}/accounts", $payload);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('accounts', ['name' => 'BCA']);
    }

    /** @test */
    public function validation_errors_when_required_fields_missing()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        Sanctum::actingAs($owner);
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", []);
        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors' => ['name', 'type', 'initial_balance']]);
    }

    /** @test */
    public function name_must_not_exceed_max_length_and_not_be_empty_after_trim()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        Sanctum::actingAs($owner);
        // Exceed max length
        $longName = str_repeat('a', 256);
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => $longName,
            'type' => 'bank',
            'initial_balance' => 0,
        ]);
        $response->assertStatus(422);
        // Empty after trim
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => "   \t\n",
            'type' => 'bank',
            'initial_balance' => 0,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function type_must_be_one_of_allowed_values()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        Sanctum::actingAs($owner);
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => 'Foo',
            'type' => 'invalid_type',
            'initial_balance' => 10,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function initial_balance_must_be_numeric_and_non_negative()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        Sanctum::actingAs($owner);
        // negative
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => 'Neg',
            'type' => 'cash',
            'initial_balance' => -5,
        ]);
        $response->assertStatus(422);
        // non‑numeric
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", [
            'name' => 'NonNum',
            'type' => 'cash',
            'initial_balance' => 'abc',
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function prohibited_fields_cause_validation_error_and_no_activity_log_created()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        Sanctum::actingAs($owner);
        $payload = [
            'name' => 'BCA',
            'type' => 'bank',
            'initial_balance' => 100,
            'household_id' => 999,
            'is_active' => false,
        ];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(422);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'account_created']);
    }

    /** @test */
    public function response_contains_only_allowed_fields()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        Sanctum::actingAs($owner);
        $payload = ['name' => 'BCA', 'type' => 'bank', 'initial_balance' => 1234.5];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(201);
        $data = $response->json('account');
        $allowed = ['id', 'name', 'type', 'initial_balance', 'is_active'];
        $this->assertEquals($allowed, array_keys($data));
    }
}
