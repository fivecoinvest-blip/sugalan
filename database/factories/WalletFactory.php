<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'real_balance' => $this->faker->randomFloat(2, 0, 10000),
            'bonus_balance' => $this->faker->randomFloat(2, 0, 1000),
            'locked_balance' => 0,
        ];
    }
}
