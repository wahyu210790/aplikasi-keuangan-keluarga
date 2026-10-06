<?php

namespace Tests\Feature\Report;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create([
            'name' => 'Keluarga Tes Savings Report',
        ]);

        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);
    }

    /** @test */
    public function user_can_get_savings_report()
    {
        Saving::create([
            'household_id' => $this->household->id,
            'name' => 'Beli Laptop',
            'target_amount' => '15000000.00',
            'current_amount' => '5000000.00',
            'is_completed' => false,
        ]);

        Saving::create([
            'household_id' => $this->household->id,
            'name' => 'Dana Darurat',
            'target_amount' => '10000000.00',
            'current_amount' => '10000000.00',
            'is_completed' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/households/{$this->household->id}/reports/savings");

        $response->assertStatus(200)
            ->assertJsonPath('summary.total_goals_count', 2)
            ->assertJsonPath('summary.completed_goals_count', 1)
            ->assertJsonPath('summary.total_target_amount', '25000000.00')
            ->assertJsonPath('summary.total_current_amount', '15000000.00')
            ->assertJsonPath('summary.total_remaining_amount', '10000000.00')
            ->assertJsonPath('summary.overall_progress_percentage', 60);
    }
}
