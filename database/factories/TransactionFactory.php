<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'transaction' => $this->faker->uuid(),
            'amount' => $this->faker->randomFloat(2, 1, 10000),
            'date' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'account_type_id' => AccountType::factory(),
            'account_id' => Account::factory(),
        ];
    }
}
