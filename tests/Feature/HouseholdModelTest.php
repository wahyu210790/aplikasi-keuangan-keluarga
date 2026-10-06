<?php

namespace Tests\Feature;

use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_household()
    {
        $household = Household::create([
            'name' => 'Family A',
            'description' => 'Primary household',
        ]);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Family A',
        ]);
    }
}
