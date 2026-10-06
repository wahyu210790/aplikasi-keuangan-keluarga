<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\SanctumServiceProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up a user for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure Sanctum service provider is loaded (normally via config/app.php)
        $this->app->register(SanctumServiceProvider::class);

        // Create a user
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
            'name' => 'Test User',
            'global_role' => null,
        ]);
    }

    /** @test */
    public function login_with_valid_credentials_returns_token_and_user_data()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'token',
                     'user' => ['id', 'name', 'email', 'global_role'],
                 ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            // tokenable_id will be the user id; we can assert exists via count
        ]);
    }

    /** @test */
    public function login_with_invalid_password_returns_error()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(422)
                 ->assertJson(['message' => 'Email atau password salah.']);
    }

    /** @test */
    public function login_with_nonexistent_email_returns_error()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any',
        ]);
        $response->assertStatus(422)
                 ->assertJson(['message' => 'Email atau password salah.']);
    }

    /** @test */
    public function login_validation_errors_for_missing_fields()
    {
        $response = $this->postJson('/api/v1/login', []);
        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }

    /** @test */
    public function login_validation_error_for_invalid_email_format()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);
        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }
}
