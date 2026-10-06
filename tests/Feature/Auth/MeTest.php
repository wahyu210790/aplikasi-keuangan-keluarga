<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function me_endpoint_returns_user_data_with_valid_token()
    {
        $user = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user' => ['id', 'name', 'email', 'global_role'],
                 ])
                 ->assertJsonMissing(['password', 'remember_token']);
    }

    /** @test */
    public function me_endpoint_returns_unauthorized_without_token()
    {
        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(401);
    }

    /** @test */
    public function me_endpoint_returns_unauthorized_with_invalid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalidtoken',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/me');
        $response->assertStatus(401);
    }

    /** @test */
    public function user_without_household_gets_empty_households_array()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(200)
                 ->assertJsonPath('households', []);
    }

    /** @test */
    public function user_with_one_household_owner_sees_household_in_response()
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        // attach as owner
        $user->households()->attach($household->id, ['role' => 'household_owner']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'id' => $household->id,
                     'name' => $household->name,
                     'role' => 'household_owner',
                 ]);
    }

    /** @test */
    public function user_with_one_household_member_sees_household_in_response()
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        $user->households()->attach($household->id, ['role' => 'household_member']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'id' => $household->id,
                     'name' => $household->name,
                     'role' => 'household_member',
                 ]);
    }

    /** @test */
    public function user_with_multiple_households_sees_all_in_response()
    {
        $user = User::factory()->create();
        $house1 = Household::factory()->create();
        $house2 = Household::factory()->create();
        $user->households()->attach($house1->id, ['role' => 'household_owner']);
        $user->households()->attach($house2->id, ['role' => 'household_member']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'id' => $house1->id,
                     'name' => $house1->name,
                     'role' => 'household_owner',
                 ])
                 ->assertJsonFragment([
                     'id' => $house2->id,
                     'name' => $house2->name,
                     'role' => 'household_member',
                 ]);
    }
}
?>
