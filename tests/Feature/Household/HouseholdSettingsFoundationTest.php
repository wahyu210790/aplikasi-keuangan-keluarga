<?php

namespace Tests\Feature\Household;

use App\Models\Household;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HouseholdSettingsFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_household_settings_can_be_stored_as_array(): void
    {
        $household = Household::create([
            'name' => 'Keluarga Test',
            'description' => 'Deskripsi Test',
            'settings' => [
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ],
        ]);

        $this->assertNotNull($household);
        $this->assertIsArray($household->settings);
        $this->assertSame('Asia/Jakarta', $household->settings['timezone']);
        $this->assertSame('IDR', $household->settings['currency']);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'settings' => json_encode([
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ]),
        ]);
    }

    /** @test */
    public function test_household_settings_can_be_null(): void
    {
        $household = Household::create([
            'name' => 'Keluarga Tanpa Settings',
            'description' => 'Deskripsi Tanpa Settings',
        ]);

        $this->assertNull($household->settings);
        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'settings' => null,
        ]);
    }

    /** @test */
    public function test_household_settings_are_cast_to_array(): void
    {
        $household = Household::create([
            'name' => 'Keluarga Cast',
            'description' => 'Deskripsi Cast',
            'settings' => [
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ],
        ]);

        $reloaded = Household::find($household->id);
        $this->assertIsArray($reloaded->settings);
        $this->assertSame('Asia/Jakarta', $reloaded->settings['timezone']);
        $this->assertSame('IDR', $reloaded->settings['currency']);
    }
}
