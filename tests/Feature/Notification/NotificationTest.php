<?php

namespace Tests\Feature\Notification;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::create(['name' => 'Notification Household', 'description' => null]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'household_owner',
        ]);
    }

    public function test_user_can_receive_and_list_notifications(): void
    {
        NotificationService::send(
            $this->user->id,
            $this->household->id,
            'budget_alert',
            'Peringatan Anggaran',
            'Anggaran makanan sudah melebihi 80%'
        );

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Peringatan Anggaran')
            ->assertJsonPath('data.0.is_read', false);
    }

    public function test_user_can_get_unread_count_and_mark_as_read(): void
    {
        $notification = NotificationService::send(
            $this->user->id,
            $this->household->id,
            'system',
            'Selamat Datang',
            'Selamat bergabung di SaaS Keuangan Keluarga'
        );

        Sanctum::actingAs($this->user);

        $countRes = $this->getJson('/api/v1/notifications/unread-count');
        $countRes->assertStatus(200)->assertJsonPath('unread_count', 1);

        $markRes = $this->patchJson("/api/v1/notifications/{$notification->id}/read");
        $markRes->assertStatus(200)->assertJsonPath('notification.is_read', true);

        $countRes2 = $this->getJson('/api/v1/notifications/unread-count');
        $countRes2->assertStatus(200)->assertJsonPath('unread_count', 0);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        NotificationService::send($this->user->id, $this->household->id, 'sys', 'N1', 'M1');
        NotificationService::send($this->user->id, $this->household->id, 'sys', 'N2', 'M2');

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/notifications/mark-all-read');
        $response->assertStatus(200);

        $countRes = $this->getJson('/api/v1/notifications/unread-count');
        $countRes->assertStatus(200)->assertJsonPath('unread_count', 0);
    }

    public function test_user_cannot_read_other_users_notification(): void
    {
        $otherUser = User::factory()->create();

        $notification = NotificationService::send(
            $this->user->id,
            $this->household->id,
            'system',
            'Rahasia',
            'Pesan privat'
        );

        Sanctum::actingAs($otherUser);

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read");
        $response->assertStatus(403);
    }
}
