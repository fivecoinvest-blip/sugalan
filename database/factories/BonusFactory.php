<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bonus>
 */
class BonusFactory extends Factory
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
            'type' => $this->faker->randomElement(['signup', 'reload', 'promotional', 'referral', 'cashback']),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'wagering_requirement' => $this->faker->randomFloat(2, 300, 30000),
            'wagering_progress' => $this->faker->randomFloat(2, 0, 30000),
            'status' => $this->faker->randomElement(['active', 'completed', 'expired', 'cancelled']),
            'expires_at' => $this->faker->dateTimeBetween('now', '+30 days'),
        ];
    }
}
