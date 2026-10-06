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

class CategoryCreateTest extends TestCase
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
            'role'    => $role,
        ]);
        if (!Auth::check()) {
            Sanctum::actingAs($user);
        }
        return [$household, $user];
    }

    /** @test */
    public function owner_can_create_income_category()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Gaji',
            'type' => 'income',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'category' => ['id', 'name', 'type', 'is_active'],
            ])
            ->assertJsonPath('category.name', 'Gaji')
            ->assertJsonPath('category.type', 'income')
            ->assertJsonPath('category.is_active', true)
            ->assertJsonMissing(['household_id', 'created_at', 'updated_at']);

        $this->assertDatabaseHas('categories', [
            'household_id' => $household->id,
            'name' => 'Gaji',
            'type' => 'income',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function member_can_create_expense_category()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_member');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Belanja Bulanan',
            'type' => 'expense',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('category.name', 'Belanja Bulanan')
            ->assertJsonPath('category.type', 'expense')
            ->assertJsonPath('category.is_active', true);

        $this->assertDatabaseHas('categories', [
            'household_id' => $household->id,
            'name' => 'Belanja Bulanan',
            'type' => 'expense',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function unauthenticated_user_gets_401()
    {
        $household = Household::factory()->create();
        Auth::logout();

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Gaji',
            'type' => 'income',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function non_member_gets_403()
    {
        $household = Household::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Gaji',
            'type' => 'income',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_isolation()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser('household_owner');
        [$householdB, $userB] = $this->createHouseholdWithUser('household_owner');

        // User A tries to create category in Household B
        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $householdB->id]), [
            'name' => 'Investasi',
            'type' => 'income',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function name_is_required()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'type' => 'income',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function name_must_be_string()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => ['invalid_array'],
            'type' => 'income',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function name_max_255()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $longName = str_repeat('a', 256);
        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => $longName,
            'type' => 'income',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function whitespace_only_name_is_rejected()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => '   ',
            'type' => 'income',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function name_is_trimmed()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => '   Bonus Tahunan   ',
            'type' => 'income',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('category.name', 'Bonus Tahunan');

        $this->assertDatabaseHas('categories', [
            'household_id' => $household->id,
            'name' => 'Bonus Tahunan',
        ]);
    }

    /** @test */
    public function type_is_required()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Bonus',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function type_must_be_income_or_expense()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Bonus',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function household_id_comes_from_route()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Dividen',
            'type' => 'income',
        ]);

        $response->assertStatus(201);
        $categoryId = $response->json('category.id');

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'household_id' => $household->id,
        ]);
    }

    /** @test */
    public function request_body_household_id_cannot_override_route()
    {
        [$householdA, $userA] = $this->createHouseholdWithUser('household_owner');
        $householdB = Household::factory()->create();

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $householdA->id]), [
            'name' => 'Sewa',
            'type' => 'income',
            'household_id' => $householdB->id,
        ]);

        $response->assertStatus(201);
        $categoryId = $response->json('category.id');

        // Verify it was created in householdA, NOT householdB
        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'household_id' => $householdA->id,
        ]);
        $this->assertDatabaseMissing('categories', [
            'id' => $categoryId,
            'household_id' => $householdB->id,
        ]);
    }

    /** @test */
    public function new_category_is_always_active()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Hadiah',
            'type' => 'income',
            'is_active' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('category.is_active', true);

        $this->assertDatabaseHas('categories', [
            'name' => 'Hadiah',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function duplicate_active_name_and_type_returns_422()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        Category::create([
            'household_id' => $household->id,
            'name' => 'Makan',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Makan',
            'type' => 'expense',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function duplicate_archived_name_and_type_is_allowed()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        Category::create([
            'household_id' => $household->id,
            'name' => 'Makan',
            'type' => 'expense',
            'is_active' => false,
        ]);

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Makan',
            'type' => 'expense',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('category.name', 'Makan')
            ->assertJsonPath('category.is_active', true);
    }

    /** @test */
    public function same_name_with_different_type_is_allowed()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        Category::create([
            'household_id' => $household->id,
            'name' => 'Cashback',
            'type' => 'income',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Cashback',
            'type' => 'expense',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('category.name', 'Cashback')
            ->assertJsonPath('category.type', 'expense');
    }

    /** @test */
    public function successful_create_generates_exactly_one_activity_log()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        $this->assertDatabaseCount('activity_logs', 0);

        $response = $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Listrik',
            'type' => 'expense',
        ]);

        $response->assertStatus(201);
        $categoryId = $response->json('category.id');

        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'action' => 'category_created',
            'entity_type' => 'category',
            'entity_id' => $categoryId,
        ]);
    }

    /** @test */
    public function failures_do_not_generate_activity_log()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');

        // 1. Validation failure
        $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'type' => 'income',
        ]);
        $this->assertDatabaseCount('activity_logs', 0);

        // 2. Non-member failure
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);
        $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Internet',
            'type' => 'expense',
        ]);
        $this->assertDatabaseCount('activity_logs', 0);

        // 3. Duplicate failure
        Sanctum::actingAs($user);
        Category::create([
            'household_id' => $household->id,
            'name' => 'Air',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $this->postJson(route('api.v1.households.categories.store', ['household' => $household->id]), [
            'name' => 'Air',
            'type' => 'expense',
        ]);
        $this->assertDatabaseCount('activity_logs', 0);
    }
}
