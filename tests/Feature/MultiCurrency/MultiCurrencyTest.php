<?php

namespace Tests\Feature\MultiCurrency;

use App\Models\User;
use App\Services\CurrencyConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_user_can_list_active_currencies(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/currencies');

        $response->assertStatus(200)
            ->assertJsonPath('currencies.0.code', 'IDR')
            ->assertJsonPath('currencies.1.code', 'USD');
    }

    public function test_user_can_convert_currency_via_api(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/currencies/convert', [
            'amount' => 100,
            'from_currency' => 'USD',
            'to_currency' => 'IDR',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('from_currency', 'USD')
            ->assertJsonPath('to_currency', 'IDR')
            ->assertJsonPath('converted_amount', 1550000);
    }

    public function test_service_handles_same_currency_conversion(): void
    {
        $res = CurrencyConversionService::convert(500, 'USD', 'USD');

        $this->assertEquals(500, $res['converted_amount']);
        $this->assertEquals(1.0, $res['rate']);
    }

    public function test_service_handles_reverse_rate_conversion(): void
    {
        $res = CurrencyConversionService::convert(1550000, 'IDR', 'USD');

        $this->assertGreaterThan(0, $res['converted_amount']);
        $this->assertEquals('USD', $res['to_currency']);
    }
}
