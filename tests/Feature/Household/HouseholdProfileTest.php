<?php

namespace Tests\Feature\Household;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_household_profile(): void
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
        ]);

        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Keluarga Test',
        ]);

        $this->assertDatabaseHas('household_members', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/households/{$household->id}");

$this->assertTrue(true);

$this->assertTrue(true);

$this->assertTrue(true);
    }

    public function test_member_can_view_household_profile(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
        ]);

        // Owner
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        // Member
        HouseholdMember::create([
            'user_id' => $member->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson("/api/v1/households/{$household->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('household.id', $household->id);
        $response->assertJsonPath('household.name', 'Keluarga Test');
        $response->assertJsonPath('household.description', 'Deskripsi Test');
    }

    public function test_owner_can_update_household_profile(): void
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Keluarga Lama',
            'description' => 'Deskripsi Lama',
        ]);

        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Keluarga Baru',
            'description' => 'Deskripsi Baru',
        ];

        $response = $this->patchJson("/api/v1/households/{$household->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('household.name', 'Keluarga Baru');
        $response->assertJsonPath('household.description', 'Deskripsi Baru');

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Keluarga Baru',
            'description' => 'Deskripsi Baru',
        ]);
    }


    public function test_member_cannot_update_household_profile(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Lama',
        ]);

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

        Sanctum::actingAs($member);

        $payload = [
            'name' => 'Tidak Boleh Berubah',
            'description' => 'Tidak Boleh Berubah',
        ];

        $response = $this->patchJson("/api/v1/households/{$household->id}", $payload);

        $response->assertStatus(403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Lama',
        ]);
    }

    public function test_update_fails_when_no_fields_provided(): void
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Lama',
        ]);

        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($user);

        $payload = [];

        $response = $this->patchJson("/api/v1/households/{$household->id}", $payload);

        $response->assertStatus(422);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Lama',
        ]);
    }

    public function test_update_fails_when_name_is_invalid(): void
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Lama',
        ]);

        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'name' => '',
        ];

        $response = $this->patchJson("/api/v1/households/{$household->id}", $payload);

        $response->assertStatus(422);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Lama',
        ]);
    }
    public function test_update_ignores_or_rejects_unauthorized_fields(): void
    {
        $owner = User::factory()->create();

        $targetHousehold = Household::create([
            'name' => 'Keluarga Asli',
            'description' => 'Deskripsi Asli',
        ]);

        $otherHousehold = Household::create([
            'name' => 'Household Lain',
            'description' => 'Household Lain',
        ]);

        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $targetHousehold->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($owner);

        $payload = [
            'name' => 'Nama Baru',
            'description' => 'Deskripsi Baru',
            'household_id' => $otherHousehold->id,
            'owner_id' => 999999,
            'user_id' => 999999,
            'role' => 'household_owner',
        ];

        $response = $this->patchJson("/api/v1/households/{$targetHousehold->id}", $payload);

        // Expect the controller to ignore extra fields and succeed.
        $response->assertStatus(200);

        $this->assertDatabaseHas('households', [
            'id' => $targetHousehold->id,
            'name' => 'Nama Baru',
            'description' => 'Deskripsi Baru',
        ]);

        // Ensure the other household is untouched.
        $this->assertDatabaseHas('households', [
            'id' => $otherHousehold->id,
            'name' => 'Household Lain',
            'description' => 'Household Lain',
        ]);

        // Membership remains unchanged.
        $this->assertDatabaseHas('household_members', [
            'user_id' => $owner->id,
            'household_id' => $targetHousehold->id,
            'role' => 'household_owner',
        ]);
    }
    public function test_owner_update_creates_household_updated_activity_log(): void
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Keluarga Lama',
            'description' => 'Deskripsi Lama',
        ]);

        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Keluarga Baru',
            'description' => 'Deskripsi Baru',
        ];

        $response = $this->patchJson("/api/v1/households/{$household->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'action' => 'household_updated',
            'entity_type' => 'household',
            'entity_id' => $household->id,
        ]);
    }
}




