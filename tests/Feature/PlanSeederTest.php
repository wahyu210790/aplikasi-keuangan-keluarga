<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that PlanSeeder creates the three base plans and is idempotent.
     */
    public function test_plan_seeder_creates_and_is_idempotent(): void
    {
        // First seed
        $this->artisan('db:seed')->assertExitCode(0);

        // Verify three plans exist with correct attributes
        $this->assertDatabaseCount('plans', 3);
        $this->assertDatabaseHas('plans', [
            'slug' => 'free',
            'name' => 'Free',
            'price' => 0,
            'max_members' => 2,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'basic',
            'name' => 'Basic',
            'price' => 49000,
            'max_members' => 5,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'pro',
            'name' => 'Pro',
            'price' => 99000,
            'max_members' => 10,
            'is_active' => true,
        ]);

        // Run seeder again to test idempotency
        $this->artisan('db:seed')->assertExitCode(0);

        // Still only three records, each slug still unique
        $this->assertDatabaseCount('plans', 3);
        $this->assertEquals(1, Plan::where('slug', 'free')->count());
        $this->assertEquals(1, Plan::where('slug', 'basic')->count());
        $this->assertEquals(1, Plan::where('slug', 'pro')->count());
    }
}
