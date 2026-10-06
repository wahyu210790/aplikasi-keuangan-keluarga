<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_user_and_hash_the_password()
    {
        // Create a user via factory
        $user = User::factory()->create();

        // Assert record exists in database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);

        // Password should be stored as a hash, not plain text
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
