<?php

namespace Tests\Feature\ActivityLogViewer;

use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Log Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);
    }

    public function test_user_can_list_activity_logs_with_pagination(): void
    {
        Sanctum::actingAs($this->user);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'action' => 'create',
            'entity_type' => 'transaction',
            'entity_id' => 1,
            'description' => 'Menambahkan transaksi baru',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/activity-logs");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.description', 'Menambahkan transaksi baru')
            ->assertJsonPath('data.0.action', 'create');
    }

    public function test_user_can_filter_activity_logs_by_action_and_entity(): void
    {
        Sanctum::actingAs($this->user);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'action' => 'delete',
            'entity_type' => 'budget',
            'entity_id' => 2,
            'description' => 'Menghapus anggaran',
            'created_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'action' => 'create',
            'entity_type' => 'saving',
            'entity_id' => 3,
            'description' => 'Menambah target tabungan',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/activity-logs?action=delete&entity_type=budget");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity_type', 'budget');
    }

    public function test_user_can_get_activity_log_summary(): void
    {
        Sanctum::actingAs($this->user);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'action' => 'create',
            'entity_type' => 'transaction',
            'created_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'action' => 'update',
            'entity_type' => 'account',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/activity-logs/summary");

        $response->assertStatus(200)
            ->assertJsonPath('total_logs', 2)
            ->assertJsonPath('action_counts.create', 1)
            ->assertJsonPath('action_counts.update', 1);
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

        ActivityLog::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'action' => 'create',
            'entity_type' => 'transaction',
            'description' => 'Log Rahasia',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/households/{$this->household->id}/activity-logs");
        $response->assertStatus(403);
    }
}
