<?php

namespace Tests\Feature\UserProfileSecurity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);
    }

    public function test_user_can_get_profile(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Budi Santoso')
            ->assertJsonPath('user.email', 'budi@example.com');
    }

    public function test_user_can_update_profile_name_and_email(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Budi Setiawan',
            'email' => 'budi.new@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Budi Setiawan');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Budi Setiawan',
            'email' => 'budi.new@example.com',
        ]);
    }

    public function test_user_can_change_password(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/profile/change-password', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'newpassword456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password berhasil diperbarui.');

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword456', $this->user->password));
    }

    public function test_user_can_revoke_tokens(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/profile/tokens/revoke');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Seluruh sesi perangkat berhasil dicabut.');
    }
}
