<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'balance' => $this->faker->randomFloat(2, 0, 10000),
            'due' => null,
            'avoid_interest_date' => null,
            'account_type_id' => AccountType::factory(),
            'monthly_due_date_day' => null,
        ];
    }
}
