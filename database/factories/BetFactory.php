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
        $betAmount = $this->faker->randomFloat(2, 10, 1000);
        $multiplier = $this->faker->randomFloat(2, 0, 10);
        $payout = $betAmount * $multiplier;
        $profit = $payout - $betAmount;
        
        return [
            'uuid' => $this->faker->uuid(),
            'user_id' => \App\Models\User::factory(),
            'game_type' => $this->faker->randomElement(['dice', 'hilo', 'mines', 'plinko', 'keno', 'wheel', 'crash', 'pump']),
            'game_id' => 'game_' . uniqid('', true),
            'bet_amount' => $betAmount,
            'balance_type' => $this->faker->randomElement(['real', 'bonus']),
            'multiplier' => $multiplier,
            'payout' => $payout,
            'profit' => $profit,
            'result' => $this->faker->randomElement(['win', 'loss', 'push']),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
            'game_result' => ['value' => $this->faker->randomFloat(2, 0, 100)],
            'server_seed_hash' => $this->faker->sha256(),
            'client_seed' => $this->faker->sha256(),
            'nonce' => $this->faker->numberBetween(1, 1000),
        ];
    }
}
