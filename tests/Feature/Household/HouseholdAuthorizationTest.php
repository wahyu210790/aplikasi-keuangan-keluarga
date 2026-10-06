<?php

namespace Tests\Feature\Household;

use App\Models\User;
use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_view_household()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        $this->assertTrue($owner->can('view', $household));
    }

    /** @test */
    public function member_can_view_household()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        HouseholdMember::create([
            'user_id' => $member->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);
        $this->assertTrue($member->can('view', $household));
    }

    /** @test */
    public function non_member_cannot_view_household()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        $this->assertFalse($other->can('view', $household));
    }

    /** @test */
    public function owner_can_update_household()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        $this->assertTrue($owner->can('update', $household));
    }

    /** @test */
    public function member_cannot_update_household()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        HouseholdMember::create([
            'user_id' => $member->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);
        $this->assertFalse($member->can('update', $household));
    }

    /** @test */
    public function non_member_cannot_update_household()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        $this->assertFalse($other->can('update', $household));
    }

    /** @test */
    public function owner_can_delete_household()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        $this->assertTrue($owner->can('delete', $household));
    }

    /** @test */
    public function member_cannot_delete_household()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'A', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        HouseholdMember::create([
            'user_id' => $member->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);
        $this->assertFalse($member->can('delete', $household));
    }

    /** @test */
    public function cross_household_isolation()
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $householdA = Household::create(['name' => 'A', 'description' => null]);
        $householdB = Household::create(['name' => 'B', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $ownerA->id,
            'household_id' => $householdA->id,
            'role' => 'household_owner',
        ]);
        HouseholdMember::create([
            'user_id' => $ownerB->id,
            'household_id' => $householdB->id,
            'role' => 'household_owner',
        ]);

        $this->assertTrue($ownerA->can('view', $householdA));
        $this->assertFalse($ownerA->can('view', $householdB));
        $this->assertTrue($ownerB->can('view', $householdB));
        $this->assertFalse($ownerB->can('view', $householdA));
    }
}
