<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function plan_can_be_created_and_persisted()
    {
        $plan = Plan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Basic SaaS package',
            'price' => 50000.00,
            'duration_days' => 30,
            'max_members' => null,
            'max_accounts' => null,
            'max_transactions_per_month' => null,
            // is_active default true, omitted
        ]);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'slug' => 'basic',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function slug_must_be_unique()
    {
        Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 150000.00,
            'duration_days' => 30,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Plan::create([
            'name' => 'Pro Duplicate',
            'slug' => 'pro', // duplicate slug
            'price' => 150000.00,
            'duration_days' => 30,
        ]);
    }

    /** @test */
    public function default_is_active_is_true()
    {
        $plan = Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0.00,
            'duration_days' => 0,
        ]);
        $this->assertTrue($plan->is_active);
    }

    /** @test */
    public function nullable_limits_can_be_set_to_null()
    {
        $plan = Plan::create([
            'name' => 'Custom',
            'slug' => 'custom',
            'price' => 75000.00,
            'duration_days' => 60,
            'max_members' => null,
            'max_accounts' => null,
            'max_transactions_per_month' => null,
        ]);
        $this->assertNull($plan->max_members);
        $this->assertNull($plan->max_accounts);
        $this->assertNull($plan->max_transactions_per_month);
    }

    /** @test */
    public function price_is_stored_as_decimal()
    {
        $plan = Plan::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 199999.99,
            'duration_days' => 365,
        ]);
        $this->assertEquals('199999.99', $plan->price); // stored as string decimal
    }
}
