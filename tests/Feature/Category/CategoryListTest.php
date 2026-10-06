<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

use Illuminate\Support\Facades\Auth;

class CategoryListTest extends TestCase
{
    use RefreshDatabase;



    protected function createHouseholdWithUser(string $role = 'household_owner')
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        $household->members()->create([
            'user_id' => $user->id,
            'role'    => $role,
        ]);
        // Authenticate only if no user is currently authenticated
        if (!Auth::check()) {
            Sanctum::actingAs($user);
        }
        return [$household, $user];
    }

    /** @test */
    public function test_owner_can_list_categories()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        Category::create([
            'household_id' => $household->id,
            'name' => 'Gaji',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(200)
                 ->assertJsonStructure(['categories' => [['id', 'name', 'type', 'is_active']]])
                 ->assertJsonCount(1, 'categories');
    }

    /** @test */
    public function test_member_can_list_categories()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_member');
        Category::create([
            'household_id' => $household->id,
            'name' => 'Belanja',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(200)->assertJsonCount(1, 'categories');
    }

    /** @test */
    public function test_unauthenticated_user_gets_401()
    {
        $household = Household::factory()->create();
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(401);
    }

    /** @test */
    public function test_non_member_gets_403()
    {
        $household = Household::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_isolation()
    {
        // Household A with owner
        [$householdA, $ownerA] = $this->createHouseholdWithUser('household_owner');
        // Household B with its own owner
        [$householdB, $ownerB] = $this->createHouseholdWithUser('household_owner');
        // Owner A tries to access B
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $householdB->id]));
        $response->assertStatus(403);
    }

    /** @test */
    public function empty_category_list_returns_empty_array()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(200)
                 ->assertExactJson(['categories' => []]);
    }

    /** @test */
    public function inactive_category_is_included_in_list()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        Category::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'expense',
            'is_active' => false,
        ]);
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'categories')
                 ->assertJsonFragment(['is_active' => false]);
    }

    /** @test */
    public function response_contains_only_allowed_fields()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        Category::create([
            'household_id' => $household->id,
            'name' => 'Test',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(200)
                 ->assertJsonStructure(['categories' => [['id', 'name', 'type', 'is_active']]])
                 ->assertJsonMissing(['household_id', 'created_at', 'updated_at']);
    }

    /** @test */
    public function categories_are_ordered_by_id_ascending()
    {
        [$household, $user] = $this->createHouseholdWithUser('household_owner');
        $cat1 = Category::create([
            'household_id' => $household->id,
            'name' => 'First',
            'type' => 'income',
            'is_active' => true,
        ]);
        $cat2 = Category::create([
            'household_id' => $household->id,
            'name' => 'Second',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $response = $this->getJson(route('api.v1.households.categories.index', ['household' => $household->id]));
        $response->assertStatus(200)
                 ->assertJsonPath('categories.0.id', $cat1->id)
                 ->assertJsonPath('categories.1.id', $cat2->id);
    }
}
