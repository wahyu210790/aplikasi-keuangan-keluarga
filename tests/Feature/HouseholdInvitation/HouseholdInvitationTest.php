<?php

namespace Tests\Feature\HouseholdInvitation;

use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = Household::create(['name' => 'Invite Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $this->owner->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);
    }

    public function test_owner_can_create_invitation(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/households/{$this->household->id}/invitations", [
            'email' => 'newmember@example.com',
            'role' => 'household_member',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('invitation.email', 'newmember@example.com');

        $this->assertDatabaseHas('household_invitations', [
            'household_id' => $this->household->id,
            'email' => 'newmember@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_user_can_accept_invitation_code_and_join_household(): void
    {
        $invitation = HouseholdInvitation::create([
            'household_id' => $this->household->id,
            'email' => 'newmember@example.com',
            'code' => 'INVITE123',
            'role' => 'household_member',
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $newUser = User::factory()->create(['email' => 'newmember@example.com']);
        Sanctum::actingAs($newUser);

        $response = $this->postJson("/api/v1/invitations/accept", [
            'code' => 'INVITE123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Berhasil bergabung dengan household!');

        $this->assertDatabaseHas('household_members', [
            'household_id' => $this->household->id,
            'user_id' => $newUser->id,
            'role' => 'household_member',
        ]);

        $invitation->refresh();
        $this->assertEquals('accepted', $invitation->status);
    }

    public function test_cross_household_isolation(): void
    {
        $otherUser = User::factory()->create();
        $otherHousehold = Household::create(['name' => 'Other Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $otherUser->id,
            'household_id' => $otherHousehold->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/invitations");
        $response->assertStatus(403);
    }
}
