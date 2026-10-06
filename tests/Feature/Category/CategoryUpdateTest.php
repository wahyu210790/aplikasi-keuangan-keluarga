<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class CategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a household with a user and optionally authenticate.
     */
    protected function createHouseholdWithUser(string $role = 'household_owner')
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        $household->members()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
        if (!Auth::check()) {
            Sanctum::actingAs($user);
        }
        return [$household, $user];
    }

    /** @test */
    public function owner_can_update_name()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'New Name']);
        $response->assertStatus(200)
            ->assertJsonPath('category.name', 'New Name')
            ->assertJsonPath('category.type', 'income');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    /** @test */
    public function member_can_update_type()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_member');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Salary',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['type' => 'expense']);
        $response->assertStatus(200)
            ->assertJsonPath('category.type', 'expense');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'type' => 'expense']);
    }

    /** @test */
    public function unauthenticated_user_gets_401()
    {
        $household = Household::factory()->create();
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'A',
            'type' => 'income',
            'is_active' => true,
        ]);
        // Ensure no user is authenticated
        Auth::logout();
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'B']);
        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_gets_403()
    {
        $household = Household::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'A',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'B']);
        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_isolation()
    {
        [$householdA, $ownerA] = $this->createHouseholdWithUser('household_owner');
        [$householdB, $ownerB] = $this->createHouseholdWithUser('household_owner');
        $categoryB = Category::create([
            'household_id' => $householdB->id,
            'name' => 'B',
            'type' => 'income',
            'is_active' => true,
        ]);
        // Owner A tries to update category in B
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $householdB->id, 'category' => $categoryB->id]), ['name' => 'C']);
        $response->assertStatus(403);
    }

    /** @test */
    public function name_trim_and_not_empty()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => '   New Name   ']);
        $response->assertStatus(200)
            ->assertJsonPath('category.name', 'New Name');
    }

    /** @test */
    public function blank_name_returns_422()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => '   ']);
        $response->assertStatus(422);
    }

    /** @test */
    public function name_max_255()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'income',
            'is_active' => true,
        ]);
        $long = str_repeat('a', 256);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => $long]);
        $response->assertStatus(422);
    }

    /** @test */
    public function invalid_type_returns_422()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'A',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['type' => 'invalid']);
        $response->assertStatus(422);
    }

    /** @test */
    public function empty_patch_returns_422()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'A',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), []);
        $response->assertStatus(422);
    }

    /** @test */
    public function prohibited_fields_cannot_be_changed()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'A',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), [
            'id' => $category->id + 1,
            'household_id' => $household->id + 1,
            'is_active' => false,
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function duplicate_active_category_returns_422()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        Category::create([
            'household_id' => $household->id,
            'name' => 'Existing',
            'type' => 'income',
            'is_active' => true,
        ]);
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Original',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'Existing']);
        $response->assertStatus(422);
    }

    /** @test */
    public function updating_to_same_values_is_allowed()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Same',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'Same', 'type' => 'income']);
        $response->assertStatus(200);
    }

    /** @test */
    public function archived_duplicate_does_not_block()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        Category::create([
            'household_id' => $household->id,
            'name' => 'Archived',
            'type' => 'income',
            'is_active' => false,
        ]);
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Active',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'Archived']);
        $response->assertStatus(200);
    }

    /** @test */
    public function response_fields_are_limited()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'A',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'B']);
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'category' => ['id', 'name', 'type', 'is_active']])
            ->assertJsonMissing(['household_id', 'created_at', 'updated_at']);
    }

    /** @test */
    public function activity_log_created_on_successful_change()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'income',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'New']);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'category_updated',
            'entity_type' => 'category',
            'entity_id' => $category->id,
        ]);
    }

    /** @test */
    public function no_activity_log_when_no_actual_change()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Same',
            'type' => 'income',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'Same']);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    /** @test */
    public function archived_category_remains_archived_after_update()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'A',
            'type' => 'income',
            'is_active' => false,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.update', ['household' => $household->id, 'category' => $category->id]), ['name' => 'B']);
        $response->assertStatus(200)
            ->assertJsonPath('category.is_active', false);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
    }
}
