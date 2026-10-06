<?php

namespace Tests\Feature\Household;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Household;
use App\Models\HouseholdMember;
use Laravel\Sanctum\Sanctum;

class HouseholdTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_requests_are_rejected()
    {
        $household = Household::factory()->create();
        $response = $this->json('GET', "/api/v1/households/{$household->id}/members");
        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_cannot_access_household_members()
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $response = $this->json('GET', "/api/v1/households/{$household->id}/members");
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Anda tidak memiliki akses ke household ini.']);
    }

    /** @test */
    public function member_can_access_its_own_household()
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);

        Sanctum::actingAs($user);
        $response = $this->json('GET', "/api/v1/households/{$household->id}/members");
        $response->assertStatus(200);
    }

    /** @test */
    public function owner_can_access_its_household()
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($owner);
        $response = $this->json('GET', "/api/v1/households/{$household->id}/members");
        $response->assertStatus(200);
    }

    /** @test */
    public function user_cannot_access_other_households()
    {
        $user = User::factory()->create();
        $houseA = Household::factory()->create();
        $houseB = Household::factory()->create();
        // user is member of house A only
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $houseA->id,
            'role' => 'household_member',
        ]);

        Sanctum::actingAs($user);
        $response = $this->json('GET', "/api/v1/households/{$houseB->id}/members");
        $response->assertStatus(403);
    }

    /** @test */
    public function user_with_multiple_households_can_access_each()
    {
        $user = User::factory()->create();
        $houseA = Household::factory()->create();
        $houseB = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $houseA->id,
            'role' => 'household_member',
        ]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $houseB->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($user);
        $responseA = $this->json('GET', "/api/v1/households/{$houseA->id}/members");
        $responseA->assertStatus(200);
        $responseB = $this->json('GET', "/api/v1/households/{$houseB->id}/members");
        $responseB->assertStatus(200);
    }
}
