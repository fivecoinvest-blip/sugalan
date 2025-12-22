<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'user_id' => \App\Models\User::factory(),
            'type' => $this->faker->randomElement(['deposit', 'withdrawal', 'bet', 'win']),
            'amount' => $this->faker->randomFloat(2, 10, 10000),
            'balance_before' => $this->faker->randomFloat(2, 0, 10000),
            'balance_after' => $this->faker->randomFloat(2, 0, 10000),
            'description' => $this->faker->sentence(),
            'metadata' => [],
        ];
    }
}
