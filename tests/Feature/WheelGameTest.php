<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WheelGameTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\VipLevelSeeder::class);
        
        $this->user = User::factory()->create();
        $this->user->wallet()->create([
            'real_balance' => 1000.00,
            'bonus_balance' => 0.00,
            'locked_balance' => 0.00,
        ]);
        
        // Assign Bronze VIP level (ID 1)
        $this->user->vip_level_id = 1;
        $this->user->save();
        
        $this->token = auth()->tokenById($this->user->id);
    }

    /** @test */
    public function user_can_get_wheel_config()
    {
        $response = $this->getJson('/api/games/wheel/config');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'segments',
                ],
            ]);

        $segments = $response->json('data.segments');
        $this->assertIsArray($segments);
        $this->assertGreaterThan(0, count($segments));
    }

    /** @test */
    public function user_can_spin_wheel()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/wheel/spin', [
                'bet_amount' => 50.00,
                'risk_level' => 'medium',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'result_segment',
                    'multiplier',
                    'payout',
                    'profit',
                    'balance',
                ],
            ]);

        // Verify result segment is valid
        $segment = $response->json('data.result_segment');
        $this->assertIsInt($segment);
        $this->assertGreaterThanOrEqual(0, $segment);
    }

    /** @test */
    public function wheel_accepts_valid_risk_levels()
    {
        $riskLevels = ['low', 'medium', 'high'];

        foreach ($riskLevels as $risk) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/wheel/spin', [
                    'bet_amount' => 10.00,
                    'risk_level' => $risk,
                ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function wheel_rejects_invalid_risk_level()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/wheel/spin', [
                'bet_amount' => 50.00,
                'risk_level' => 'extreme',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function wheel_deducts_bet_from_wallet()
    {
        $initialBalance = $this->user->wallet->real_balance;

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/wheel/spin', [
                'bet_amount' => 100.00,
                'risk_level' => 'medium',
            ]);

        $this->user->wallet->refresh();
        
        // Balance should change
        $this->assertNotEquals($initialBalance, $this->user->wallet->real_balance);
    }

    /** @test */
    public function wheel_payout_matches_multiplier()
    {
        $betAmount = 50.00;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/wheel/spin', [
                'bet_amount' => $betAmount,
                'risk_level' => 'medium',
            ]);

        $multiplier = $response->json('data.multiplier');
        $payout = $response->json('data.payout');

        $expectedPayout = $betAmount * $multiplier;
        $this->assertEquals($expectedPayout, $payout);
    }

    /** @test */
    public function wheel_records_bet_in_database()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/wheel/spin', [
                'bet_amount' => 50.00,
                'risk_level' => 'medium',
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game_type' => 'wheel',
            'bet_amount' => 50.00,
        ]);
    }

    /** @test */
    public function wheel_config_has_valid_segments()
    {
        $response = $this->getJson('/api/games/wheel/config');
        $segments = $response->json('data.segments');

        foreach ($segments as $segment) {
            $this->assertArrayHasKey('multiplier', $segment);
            $this->assertArrayHasKey('color', $segment);
            $this->assertArrayHasKey('probability', $segment);
            
            // Verify multiplier is positive
            $this->assertGreaterThan(0, $segment['multiplier']);
            
            // Verify probability is between 0 and 1
            $this->assertGreaterThanOrEqual(0, $segment['probability']);
            $this->assertLessThanOrEqual(1, $segment['probability']);
        }
    }

    /** @test */
    public function wheel_probabilities_sum_to_one()
    {
        $response = $this->getJson('/api/games/wheel/config');
        $segments = $response->json('data.segments');

        $totalProbability = array_sum(array_column($segments, 'probability'));
        
        // Allow small floating point difference
        $this->assertEqualsWithDelta(1.0, $totalProbability, 0.001);
    }

    /** @test */
    public function high_risk_has_higher_multipliers()
    {
        $multipliers = [
            'low' => [],
            'medium' => [],
            'high' => [],
        ];

        // Spin multiple times to get variety
        foreach (['low', 'medium', 'high'] as $risk) {
            for ($i = 0; $i < 10; $i++) {
                $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                    ->postJson('/api/games/wheel/spin', [
                        'bet_amount' => 10.00,
                        'risk_level' => $risk,
                    ]);

                $multipliers[$risk][] = $response->json('data.multiplier');
            }
        }

        // Get max multipliers
        $maxLow = max($multipliers['low']);
        $maxHigh = max($multipliers['high']);
        
        // High risk should have potential for higher multipliers
        $this->assertLessThanOrEqual($maxHigh * 1.5, $maxLow * 2);
    }

    /** @test */
    public function wheel_requires_sufficient_balance()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/wheel/spin', [
                'bet_amount' => 2000.00, // Exceeds balance
                'risk_level' => 'medium',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
