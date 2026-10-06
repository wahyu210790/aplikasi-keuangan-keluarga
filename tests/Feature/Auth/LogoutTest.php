<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_logout()
    {
        // Create a user with known password
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        // Create two tokens for the user
        $tokenA = $user->createToken('auth-token-A');
        $tokenB = $user->createToken('auth-token-B');

        $tokenIdA = $tokenA->accessToken->id;
        $tokenIdB = $tokenB->accessToken->id;

        // Perform logout with token A
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenA->plainTextToken,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Logout berhasil.',
                 ]);

        // Token A should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenIdA,
        ]);

        // Token B should still exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenIdB,
        ]);
    }

    /** @test */
    public function logout_without_token_returns_unauthorized()
    {
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    /** @test */
    public function logout_with_invalid_token_returns_unauthorized()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalidtoken',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }
}
