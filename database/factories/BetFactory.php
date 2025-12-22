<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bet>
 */
class BetFactory extends Factory
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
            'game_type' => $this->faker->randomElement(['dice', 'hilo', 'mines', 'plinko', 'keno', 'wheel', 'crash', 'pump']),
            'bet_amount' => $this->faker->randomFloat(2, 10, 1000),
            'payout_amount' => $this->faker->randomFloat(2, 0, 5000),
            'multiplier' => $this->faker->randomFloat(2, 0, 10),
            'profit' => $this->faker->randomFloat(2, -1000, 4000),
            'status' => $this->faker->randomElement(['pending', 'win', 'loss']),
            'client_seed' => $this->faker->sha256(),
            'server_seed_hash' => $this->faker->sha256(),
            'nonce' => $this->faker->numberBetween(1, 1000),
            'game_data' => [],
            'result' => [],
        ];
    }
}
