<?php

namespace Tests\Feature\SubscriptionQuota;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\QuotaEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionQuotaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Quota Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);

        $this->plan = Plan::create([
            'name' => 'Starter Pack',
            'slug' => 'starter',
            'price' => 50000.00,
            'duration_days' => 30,
            'max_members' => 2,
            'max_accounts' => 2,
            'max_transactions_per_month' => 5,
            'is_active' => true,
        ]);
    }

    public function test_user_can_list_plans_and_get_subscription_info(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/plans");
        $response->assertStatus(200)->assertJsonPath('plans.0.slug', 'starter');

        $response2 = $this->getJson("/api/v1/households/{$this->household->id}/subscription");
        $response2->assertStatus(200)->assertJsonStructure(['usage']);
    }

    public function test_household_can_subscribe_to_plan(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/households/{$this->household->id}/subscription", [
            'plan_id' => $this->plan->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('subscription.plan_id', $this->plan->id);

        $this->assertDatabaseHas('subscriptions', [
            'household_id' => $this->household->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    public function test_quota_service_calculates_limits_correctly(): void
    {
        Subscription::create([
            'household_id' => $this->household->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue(QuotaEnforcementService::canAddMember($this->household));
        $this->assertTrue(QuotaEnforcementService::canAddAccount($this->household));
        $this->assertTrue(QuotaEnforcementService::canAddTransaction($this->household));
    }

    public function test_cross_household_isolation(): void
    {
        $otherUser = User::factory()->create();
        $otherHousehold = Household::create(['name' => 'Other Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $otherUser->id,
            'household_id' => $otherHousehold->id,
            'role' => 'household_owner',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/subscription");
        $response->assertStatus(403);
    }
}
