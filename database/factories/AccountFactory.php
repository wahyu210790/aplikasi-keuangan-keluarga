<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition()
    {
        $household = Household::factory()->create();
        return [
            'household_id' => $household->id,
            'name' => $this->faker->word,
            'type' => $this->faker->randomElement(['bank', 'cash', 'e_wallet']),
            'initial_balance' => $this->faker->randomFloat(2, 0, 10000),
            'is_active' => true,
        ];
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => true,
            ];
        });
    }
}
