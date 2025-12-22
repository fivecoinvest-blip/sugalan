<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deposit>
 */
class DepositFactory extends Factory
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
            'gcash_account_id' => 1,
            'amount' => $this->faker->randomFloat(2, 100, 50000),
            'reference_number' => 'REF' . $this->faker->unique()->numerify('##########'),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected', 'cancelled']),
            'proof_url' => null,
        ];
    }
}
