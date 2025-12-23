<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GcashAccount>
 */
class GcashAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_name' => $this->faker->name(),
            'account_number' => '0917' . $this->faker->numerify('#######'),
            'is_active' => true,
            'daily_limit' => $this->faker->randomFloat(2, 50000, 500000),
            'monthly_limit' => $this->faker->randomFloat(2, 100000, 1000000),
            'daily_received' => 0,
            'monthly_received' => 0,
            'display_order' => $this->faker->numberBetween(1, 10),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
