<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCustomerCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'global_role' => 'super_admin',
        ]);
    }

    /** @test */
    public function super_admin_can_create_new_customer_and_household_atomically()
    {
        Sanctum::actingAs($this->superAdmin);

        $payload = [
            'name' => 'Wahyu Santoso',
            'email' => 'wahyu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'household_name' => 'Keluarga Wahyu',
        ];

        $response = $this->postJson('/api/v1/admin/customers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.email', 'wahyu@example.com')
            ->assertJsonPath('data.user.name', 'Wahyu Santoso')
            ->assertJsonPath('data.user.global_role', 'user')
            ->assertJsonPath('data.household.name', 'Keluarga Wahyu')
            ->assertJsonPath('data.membership.role', 'household_owner');

        // Verify User created
        $customer = User::where('email', 'wahyu@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertTrue(Hash::check('password123', $customer->password));
        $this->assertNull($customer->global_role);

        // Verify Household created
        $household = Household::where('name', 'Keluarga Wahyu')->first();
        $this->assertNotNull($household);

        // Verify Household Member created for Customer as household_owner
        $this->assertDatabaseHas('household_members', [
            'user_id' => $customer->id,
            'household_id' => $household->id,
            'role' => 'household_owner',
        ]);

        // Verify Super Admin is NOT added to household
        $this->assertDatabaseMissing('household_members', [
            'user_id' => $this->superAdmin->id,
            'household_id' => $household->id,
        ]);

        // Verify Activity Log
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->superAdmin->id,
            'household_id' => $household->id,
            'action' => 'customer_created',
        ]);
    }

    /** @test */
    public function created_customer_can_login_and_access_own_household()
    {
        Sanctum::actingAs($this->superAdmin);

        $payload = [
            'name' => 'Wahyu Santoso',
            'email' => 'wahyu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'household_name' => 'Keluarga Wahyu',
        ];

        $this->postJson('/api/v1/admin/customers', $payload)->assertStatus(201);

        $customer = User::where('email', 'wahyu@example.com')->first();
        $household = Household::where('name', 'Keluarga Wahyu')->first();

        // Customer Login
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'wahyu@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        // Customer Access Own Household
        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/households/{$household->id}")
            ->assertStatus(200)
            ->assertJsonPath('household.name', 'Keluarga Wahyu');

        $this->getJson("/api/v1/households/{$household->id}/accounts")
            ->assertStatus(200);
    }

    /** @test */
    public function created_customer_cannot_access_super_admin_panel()
    {
        Sanctum::actingAs($this->superAdmin);

        $payload = [
            'name' => 'Wahyu Santoso',
            'email' => 'wahyu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'household_name' => 'Keluarga Wahyu',
        ];

        $this->postJson('/api/v1/admin/customers', $payload)->assertStatus(201);

        $customer = User::where('email', 'wahyu@example.com')->first();
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/admin/stats')->assertStatus(403);
        $this->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->postJson('/api/v1/admin/customers', $payload)->assertStatus(403);
    }

    /** @test */
    public function super_admin_cannot_access_household_finance_endpoints()
    {
        Sanctum::actingAs($this->superAdmin);

        $payload = [
            'name' => 'Wahyu Santoso',
            'email' => 'wahyu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'household_name' => 'Keluarga Wahyu',
        ];

        $this->postJson('/api/v1/admin/customers', $payload)->assertStatus(201);

        $household = Household::where('name', 'Keluarga Wahyu')->first();

        // Super Admin access household finance endpoints -> forbidden (403)
        $this->getJson("/api/v1/households/{$household->id}/accounts")->assertStatus(403);
        $this->getJson("/api/v1/households/{$household->id}/transactions")->assertStatus(403);
    }

    /** @test */
    public function duplicate_email_is_rejected_and_rolls_back_transaction()
    {
        User::factory()->create(['email' => 'wahyu@example.com']);

        Sanctum::actingAs($this->superAdmin);

        $payload = [
            'name' => 'Wahyu Santoso',
            'email' => 'wahyu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'household_name' => 'Keluarga Wahyu Duplicate',
        ];

        $response = $this->postJson('/api/v1/admin/customers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Household should not be created
        $this->assertDatabaseMissing('households', [
            'name' => 'Keluarga Wahyu Duplicate',
        ]);
    }

    /** @test */
    public function customer_cannot_access_another_customers_household()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create Customer 1
        $this->postJson('/api/v1/admin/customers', [
            'name' => 'Customer 1',
            'email' => 'cust1@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'household_name' => 'Household 1',
        ])->assertStatus(201);

        // Create Customer 2
        $this->postJson('/api/v1/admin/customers', [
            'name' => 'Customer 2',
            'email' => 'cust2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'household_name' => 'Household 2',
        ])->assertStatus(201);

        $cust1 = User::where('email', 'cust1@example.com')->first();
        $household2 = Household::where('name', 'Household 2')->first();

        // Customer 1 tries to access Household 2 -> 403
        Sanctum::actingAs($cust1);
        $this->getJson("/api/v1/households/{$household2->id}")->assertStatus(403);
        $this->getJson("/api/v1/households/{$household2->id}/accounts")->assertStatus(403);
    }
}
