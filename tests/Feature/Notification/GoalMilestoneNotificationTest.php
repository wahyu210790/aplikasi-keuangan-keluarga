<?php

namespace Tests\Feature\Notification;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Notification;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalMilestoneNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Keluarga Tes Milestone']);

        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);
    }

    /** @test */
    public function creating_saving_at_milestone_sends_notification()
    {
        // 50% milestone on creation (5.000.000 / 10.000.000 = 50%)
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/households/{$this->household->id}/savings", [
                'name' => 'Dana Darurat',
                'target_amount' => 10000000,
                'current_amount' => 5000000,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'type' => 'goal_milestone',
        ]);
    }

    /** @test */
    public function updating_saving_progress_triggers_milestone_and_prevents_duplicates()
    {
        $saving = Saving::create([
            'household_id' => $this->household->id,
            'name' => 'Beli Motor',
            'target_amount' => '20000000.00',
            'current_amount' => '2000000.00', // 10%
        ]);

        // Update to 10.000.000 (50%) -> should trigger 25% and 50% milestone notifications
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/households/{$this->household->id}/savings/{$saving->id}", [
                'current_amount' => 10000000,
            ])
            ->assertStatus(200);

        $count50 = Notification::where('user_id', $this->user->id)
            ->where('type', 'goal_milestone')
            ->count();

        $this->assertEquals(2, $count50); // 25% and 50%

        // Updating again to same amount does not create duplicate notifications
        $this->patchJson("/api/v1/households/{$this->household->id}/savings/{$saving->id}", [
            'current_amount' => 10000000,
        ]);

        $countAfter = Notification::where('user_id', $this->user->id)
            ->where('type', 'goal_milestone')
            ->count();

        $this->assertEquals(2, $countAfter);
    }
}
