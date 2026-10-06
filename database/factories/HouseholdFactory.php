<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Household::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->company,
            'description' => $this->faker->optional()->sentence,
        ];
    }
}
