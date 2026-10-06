<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdMemberTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_member_with_owner_role()
    {
        $user = User::factory()->create();
        $household = Household::create(['name' => 'Family A', 'description' => 'Main household']);

        $member = HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        $this->assertDatabaseHas('household_members', [
            'id' => $member->id,
            'role' => 'household_owner',
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_membership()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $user = User::factory()->create();
        $household = Household::create(['name' => 'Family B', 'description' => null]);

        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);
        // duplicate should throw unique constraint violation
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);
    }
}
