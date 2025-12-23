<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Withdrawal>
 */
class WithdrawalFactory extends Factory
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
            'amount' => $this->faker->randomFloat(2, 100, 50000),
            'gcash_number' => '0917' . $this->faker->numerify('#######'),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'rejected', 'cancelled']),
            'admin_notes' => $this->faker->optional()->sentence(),
        ];
    }
}
