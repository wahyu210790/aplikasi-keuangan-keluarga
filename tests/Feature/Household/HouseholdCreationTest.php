<?php

namespace Tests\Feature\Household;

use App\Models\User;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdCreationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_create_household()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Keluarga Saya',
            'description' => 'Keuangan keluarga',
        ];

        $response = $this->postJson('/api/v1/households', $payload);
        $response->assertStatus(201)
                 ->assertJsonFragment(['message' => 'Household berhasil dibuat.'])
                 ->assertJsonPath('household.name', $payload['name'])
                 ->assertJsonPath('household.description', $payload['description'])
                 ->assertJsonPath('membership.role', 'household_owner');

        $this->assertDatabaseHas('households', $payload);
    }

    /** @test */
    public function user_is_automatically_household_owner()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = ['name' => 'My Household'];
        $this->postJson('/api/v1/households', $payload)->assertStatus(201);

        $household = Household::first();
        $this->assertDatabaseHas('household_members', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_household()
    {
        $payload = ['name' => 'Test'];
        $this->postJson('/api/v1/households', $payload)->assertStatus(401);
    }

    /** @test */
    public function validation_fails_when_name_is_empty()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = ['name' => '', 'description' => 'desc'];
        $this->postJson('/api/v1/households', $payload)->assertStatus(422);
    }

    /** @test */
    public function user_id_cannot_be_overridden_by_client()
    {
        $otherUser = User::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = [
            'name' => 'Test',
            'user_id' => $otherUser->id,
        ];
        $this->postJson('/api/v1/households', $payload)->assertStatus(201);
        $household = Household::first();
        $this->assertDatabaseHas('household_members', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        $this->assertDatabaseMissing('household_members', [
            'user_id' => $otherUser->id,
        ]);
    }

    /** @test */
    public function activity_log_is_created_when_household_is_created()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = ['name' => 'LogTest'];
        $this->postJson('/api/v1/households', $payload)->assertStatus(201);
        $household = Household::first();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'action' => 'household_created',
            'entity_type' => 'household',
            'entity_id' => $household->id,
        ]);
    }
}
