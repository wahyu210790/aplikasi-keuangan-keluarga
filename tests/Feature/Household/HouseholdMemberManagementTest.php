<?php

namespace Tests\Feature\Household;

use App\Models\User;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_view_members()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/members");
        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $household->id, 'name' => $household->name])
                 ->assertJsonFragment(['user_id' => $owner->id, 'role' => 'household_owner']);
    }

    /** @test */
    public function non_member_cannot_view_members()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/households/{$household->id}/members")->assertStatus(403);
    }

    /** @test */
    public function owner_can_add_member()
    {
        $owner = User::factory()->create();
        $newUser = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $payload = ['email' => $newUser->email, 'role' => 'household_member'];
        $response = $this->postJson("/api/v1/households/{$household->id}/members", $payload);
        $response->assertStatus(201)
                 ->assertJsonFragment(['message' => 'Member added'])
                 ->assertJsonPath('member.role', 'household_member');

        $this->assertDatabaseHas('household_members', [
            'user_id' => $newUser->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'household_member_added',
            'user_id' => $owner->id,
            'household_id' => $household->id,
        ]);
    }

    /** @test */
    public function member_cannot_add_another_member()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $newUser = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Sanctum::actingAs($member);
        $payload = ['email' => $newUser->email, 'role' => 'household_member'];
        $this->postJson("/api/v1/households/{$household->id}/members", $payload)->assertStatus(403);
    }

    /** @test */
    public function cannot_add_duplicate_member()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $target = User::factory()->create();
        HouseholdMember::create(['user_id' => $target->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Sanctum::actingAs($owner);
        $payload = ['email' => $target->email, 'role' => 'household_member'];
        $this->postJson("/api/v1/households/{$household->id}/members", $payload)->assertStatus(422);
    }

    /** @test */
    public function owner_cannot_change_owner_role_via_patch()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        $ownerMember = HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/households/{$household->id}/members/{$ownerMember->id}", ['role' => 'household_member'])
             ->assertStatus(422);
    }

    /** @test */
    public function owner_can_update_member_role()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $memberRec = HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/members/{$memberRec->id}", ['role' => 'household_member']);
        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Member role updated']);
        $this->assertDatabaseHas('household_members', ['id' => $memberRec->id, 'role' => 'household_member']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'household_member_updated', 'user_id' => $owner->id]);
    }

    /** @test */
    public function owner_cannot_delete_self()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        $ownerMember = HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/households/{$household->id}/members/{$ownerMember->id}")->assertStatus(422);
        $this->assertDatabaseHas('household_members', ['id' => $ownerMember->id]);
    }

    /** @test */
    public function owner_can_remove_member()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);
        $memberRec = HouseholdMember::create(['user_id' => $member->id, 'household_id' => $household->id, 'role' => 'household_member']);

        Sanctum::actingAs($owner);
        $response = $this->deleteJson("/api/v1/households/{$household->id}/members/{$memberRec->id}");
        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Member removed']);
        $this->assertDatabaseMissing('household_members', ['id' => $memberRec->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'household_member_removed', 'user_id' => $owner->id]);
    }

    /** @test */
    public function cross_household_isolation()
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $householdA = Household::create(['name' => 'A', 'description' => null]);
        $householdB = Household::create(['name' => 'B', 'description' => null]);
        HouseholdMember::create(['user_id' => $ownerA->id, 'household_id' => $householdA->id, 'role' => 'household_owner']);
        HouseholdMember::create(['user_id' => $ownerB->id, 'household_id' => $householdB->id, 'role' => 'household_owner']);

        Sanctum::actingAs($ownerA);
        // attempt to access B
        $this->getJson("/api/v1/households/{$householdB->id}/members")->assertStatus(403);
        $this->postJson("/api/v1/households/{$householdB->id}/members", ['email' => $ownerA->email, 'role' => 'household_member'])->assertStatus(403);
        $memberB = HouseholdMember::where('household_id', $householdB->id)->first();
        $this->patchJson("/api/v1/households/{$householdB->id}/members/{$memberB->id}", ['role' => 'household_member'])->assertStatus(403);
        $this->deleteJson("/api/v1/households/{$householdB->id}/members/{$memberB->id}")->assertStatus(403);
    }
}
