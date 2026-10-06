<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class CategoryArchiveTest extends TestCase
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
        // Authenticate only if no user is currently authenticated
        if (!Auth::check()) {
            Sanctum::actingAs($user);
        }
        return [$household, $user];
    }

    /** @test */
    public function unauthenticated_user_gets_401()
    {
        $household = Household::factory()->create();
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Salary',
            'type' => 'income',
            'is_active' => true,
        ]);
        Auth::logout();
        $response = $this->patchJson(route('api.v1.households.categories.archive', ['household' => $household->id, 'category' => $category->id]));
        $response->assertStatus(401);
    }

    /** @test */
    public function owner_can_archive_active_category_and_creates_log()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Gaji',
            'type' => 'income',
            'is_active' => true,
        ]);
        $logCountBefore = ActivityLog::count();
        $response = $this->patchJson(route('api.v1.households.categories.archive', ['household' => $household->id, 'category' => $category->id]));
        $response->assertStatus(200)->assertJsonFragment(['is_active' => false]);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertEquals($logCountBefore + 1, ActivityLog::count());
        $log = ActivityLog::latest()->first();
        $this->assertEquals('category_archived', $log->action);
        $this->assertEquals($owner->id, $log->user_id);
        $this->assertEquals($household->id, $log->household_id);
        $this->assertEquals($category->id, $log->entity_id);
    }

    /** @test */
    public function member_can_archive_active_category()
    {
        [$household, $member] = $this->createHouseholdWithUser('household_member');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Belanja',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.archive', ['household' => $household->id, 'category' => $category->id]));
        $response->assertStatus(200);
        $this->assertFalse($category->fresh()->is_active);
    }

    /** @test */
    public function non_member_cannot_archive()
    {
        $household = Household::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Test',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson(route('api.v1.households.categories.archive', ['household' => $household->id, 'category' => $category->id]));
        $response->assertStatus(403);
    }

    /** @test */
    public function cross_household_category_is_forbidden()
    {
        // Owner of Household A
        [$householdA, $ownerA] = $this->createHouseholdWithUser('household_owner');
        // Household B with its own owner
        [$householdB, $ownerB] = $this->createHouseholdWithUser('household_owner');
        $categoryB = Category::create([
            'household_id' => $householdB->id,
            'name' => 'Other',
            'type' => 'expense',
            'is_active' => true,
        ]);
        // Owner A tries to archive category from Household B
        $response = $this->patchJson(route('api.v1.households.categories.archive', ['household' => $householdB->id, 'category' => $categoryB->id]));
        $response->assertStatus(403);
    }

    /** @test */
    public function archived_category_is_idempotent_and_no_extra_log()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Old',
            'type' => 'income',
            'is_active' => false,
        ]);
        $logCountBefore = ActivityLog::count();
        $response = $this->patchJson(route('api.v1.households.categories.archive', ['household' => $household->id, 'category' => $category->id]));
        $response->assertStatus(200)->assertJsonFragment(['is_active' => false]);
        $this->assertEquals($logCountBefore, ActivityLog::count());
    }

    /** @test */
    public function response_contains_only_allowed_fields()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Test',
            'type' => 'income',
            'is_active' => true,
        ]);
        $response = $this->patchJson("/api/v1/households/{$household->id}/categories/{$category->id}/archive");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'category' => ['id', 'name', 'type', 'is_active'],
            ])
            ->assertJsonMissing(['household_id', 'created_at', 'updated_at']);
    }

    /** @test */
    public function no_transactions_or_accounts_are_modified_during_archive()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        // Create dummy transaction and account linked to this household
        $account = Account::create([
            'household_id' => $household->id,
            'name' => 'Cash',
            'type' => 'cash',
            'is_active' => true,
        ]);
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'Salary',
            'type' => 'income',
            'is_active' => true,
        ]);
        $transCountBefore = Transaction::count();
        $acctCountBefore = Account::count();
        $this->patchJson(route('api.v1.households.categories.archive', ['household' => $household->id, 'category' => $category->id]));
        $this->assertEquals($transCountBefore, Transaction::count());
        $this->assertEquals($acctCountBefore, Account::count());
    }

    /** @test */
    public function category_is_not_hard_deleted()
    {
        [$household, $owner] = $this->createHouseholdWithUser('household_owner');
        $category = Category::create([
            'household_id' => $household->id,
            'name' => 'ToDelete',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $this->patchJson(route('api.v1.households.categories.archive', ['household' => $household->id, 'category' => $category->id]));
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
