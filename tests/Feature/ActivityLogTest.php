<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_an_activity_log_with_user_and_household()
    {
        $user = User::factory()->create();
        $household = Household::create([
            'name' => 'Test Household',
            'description' => 'Description',
        ]);

        $log = ActivityLog::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'action' => 'login',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'description' => 'User logged in',
            'metadata' => ['ip' => '127.0.0.1'],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'action' => 'login',
        ]);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals($household->id, $log->household_id);
        $this->assertIsArray($log->metadata);
        $this->assertEquals(['ip' => '127.0.0.1'], $log->metadata);
    }

    /** @test */
    public function user_id_is_nullable_and_is_nullified_on_user_deletion()
    {
        $user = User::factory()->create();
        $log = ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
        ]);

        $user->delete();

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
        $this->assertNull(ActivityLog::find($log->id)->user_id);
    }

    /** @test */
    public function household_id_is_nullable_and_is_nullified_on_household_deletion()
    {
        $household = Household::create([
            'name' => 'Tmp Household',
            'description' => 'Desc',
        ]);
        $log = ActivityLog::create([
            'household_id' => $household->id,
            'action' => 'test_action',
        ]);

        $household->delete();

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
        $this->assertNull(ActivityLog::find($log->id)->household_id);
    }

    /** @test */
    public function metadata_can_be_null()
    {
        $log = ActivityLog::create([
            'action' => 'no_metadata',
            'metadata' => null,
        ]);

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
        $this->assertNull($log->metadata);
    }
}
