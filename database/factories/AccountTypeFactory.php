<?php

namespace Database\Factories;

use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountType>
 */
class AccountTypeFactory extends Factory
{
    protected $model = AccountType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
        ];
    }
}
