<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountCreateAdditionalTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_can_create_e_wallet_account()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $payload = ['name' => 'MyEwallet', 'type' => 'e_wallet', 'initial_balance' => 123.45];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('accounts', [
            'household_id' => $household->id,
            'name' => 'MyEwallet',
            'type' => 'e_wallet',
            'initial_balance' => number_format(123.45, 2, '.', ''),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function name_is_trimmed_before_saving()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $payload = ['name' => "  BCA   \t\n", 'type' => 'bank', 'initial_balance' => 0];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('accounts', [
            'household_id' => $household->id,
            'name' => 'BCA', // trimmed
        ]);
    }

    /** @test */
    public function empty_name_after_trim_is_rejected()
    {
        $owner = User::factory()->create();
        $household = Household::create(['name' => 'Family', 'description' => null]);
        HouseholdMember::create(['user_id' => $owner->id, 'household_id' => $household->id, 'role' => 'household_owner']);

        Sanctum::actingAs($owner);
        $payload = ['name' => "   \n\t", 'type' => 'cash', 'initial_balance' => 0];
        $response = $this->postJson("/api/v1/households/{$household->id}/accounts", $payload);
        $response->assertStatus(422);
        $this->assertDatabaseMissing('accounts', ['household_id' => $household->id, 'type' => 'cash']);
    }
}
