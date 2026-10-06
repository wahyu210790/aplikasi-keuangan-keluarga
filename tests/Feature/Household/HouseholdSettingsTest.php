<?php

namespace Tests\Feature\Household;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_household_settings(): void
    {
        $owner = User::factory()->create();
        $settings = ['timezone' => 'UTC', 'currency' => 'IDR'];
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => $settings,
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/settings");
        $response->assertStatus(200);
        $response->assertJsonPath('settings', $settings);
    }

    public function test_member_can_view_household_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $settings = ['locale' => 'id'];
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => $settings,
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
        $response = $this->getJson("/api/v1/households/{$household->id}/settings");
        $response->assertStatus(200);
        $response->assertJsonPath('settings', $settings);
    }

    public function test_settings_can_be_null(): void
    {
        $owner = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            // settings left null implicitly
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v1/households/{$household->id}/settings");
        $response->assertStatus(200);
        $response->assertJsonPath('settings', null);
    }

    public function test_non_member_cannot_view_household_settings(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($other);
        $response = $this->getJson("/api/v1/households/{$household->id}/settings");
        $response->assertStatus(403);
    }
    public function test_owner_can_update_household_settings(): void
    {
        $owner = User::factory()->create();
        $initialSettings = ['timezone' => 'Asia/Jakarta', 'currency' => 'IDR'];
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => $initialSettings,
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $newSettings = ['timezone' => 'Asia/Makassar', 'currency' => 'USD'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", ['settings' => $newSettings]);
        $response->assertStatus(200);
        $response->assertJsonPath('settings', $newSettings);
        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'settings' => json_encode($newSettings),
        ]);
        // Ensure name and description unchanged
        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
        ]);
    }

    public function test_member_cannot_update_household_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $initialSettings = ['timezone' => 'Asia/Jakarta', 'currency' => 'IDR'];
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => $initialSettings,
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
        $newSettings = ['timezone' => 'Europe/London', 'currency' => 'GBP'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", ['settings' => $newSettings]);
        $response->assertStatus(403);
        // Settings should remain unchanged
        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'settings' => json_encode($initialSettings),
        ]);
    }

    public function test_non_member_cannot_update_household_settings(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $initialSettings = ['timezone' => 'Asia/Jakarta', 'currency' => 'IDR'];
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => $initialSettings,
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($other);
        $newSettings = ['timezone' => 'Europe/Paris', 'currency' => 'EUR'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", ['settings' => $newSettings]);
        $response->assertStatus(403);
        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'settings' => json_encode($initialSettings),
        ]);
    }
    public function test_empty_settings_payload_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => [],
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", []);
        $response->assertStatus(422);
        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'settings' => json_encode([]),
        ]);
    }

    public function test_missing_settings_field_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => [],
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", ['foo' => 'bar']);
        $response->assertStatus(422);
    }

    public function test_settings_must_be_array(): void
    {
        $owner = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => [],
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", ['settings' => 'invalid']);
        $response->assertStatus(422);
    }

    public function test_unauthorized_household_fields_are_ignored(): void
    {
        $owner = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => ['timezone' => 'Asia/Jakarta', 'currency' => 'IDR'],
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $payload = [
            'settings' => ['timezone' => 'Asia/Makassar', 'currency' => 'USD'],
            'name' => 'HACKED',
            'description' => 'HACKED',
            'user_id' => 999,
            'household_id' => 999,
            'role' => 'household_owner',
        ];
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('settings', $payload['settings']);
        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => json_encode($payload['settings']),
        ]);
        }

public function test_owner_settings_update_creates_activity_log(): void
    {
        $owner = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => [],
        ]);
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        Sanctum::actingAs($owner);
        $newSettings = ['timezone' => 'Asia/Makassar', 'currency' => 'USD'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", ['settings' => $newSettings]);
        $response->assertStatus(200);
        $response->assertJsonPath('settings', $newSettings);
        // Verify ActivityLog created
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'action' => 'household_settings_updated',
            'entity_type' => 'household',
            'entity_id' => $household->id,
        ]);
        // Ensure only one such log exists
        $this->assertEquals(1, \App\Models\ActivityLog::where('action', 'household_settings_updated')
            ->where('household_id', $household->id)->count());
    }

    public function test_member_settings_update_does_not_create_activity_log(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => [],
        ]);
        // Owner
        HouseholdMember::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);
        // Member with member role
        HouseholdMember::create([
            'user_id' => $member->id,
            'household_id' => $household->id,
            'role' => 'household_member',
        ]);
        Sanctum::actingAs($member);
        $newSettings = ['timezone' => 'Asia/Makassar', 'currency' => 'USD'];
        $response = $this->patchJson("/api/v1/households/{$household->id}/settings", ['settings' => $newSettings]);
        $response->assertStatus(403);
        // Verify no ActivityLog created for this action
        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $member->id,
            'household_id' => $household->id,
            'action' => 'household_settings_updated',
        ]);
    }


}

