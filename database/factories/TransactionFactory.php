<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Household;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition()
    {
        // Create a household for the transaction
        $household = Household::factory()->create();
        // Create accounts belonging to this household
        $account = Account::factory()->active()->create(['household_id' => $household->id]);
        $toAccount = Account::factory()->active()->create(['household_id' => $household->id]);

        $type = $this->faker->randomElement(['income', 'expense', 'transfer']);
        $toAccountId = $type === 'transfer' ? $toAccount->id : null;

        return [
            'household_id' => $household->id,
            'type' => $type,
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'description' => $this->faker->optional()->sentence,
            'transaction_date' => $this->faker->date(),
            'account_id' => $account->id,
            'to_account_id' => $toAccountId,
        ];
    }
}
