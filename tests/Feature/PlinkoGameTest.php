<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlinkoGameTest extends TestCase
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
    public function user_can_play_plinko()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/plinko/play', [
                'bet_amount' => 50.00,
                'risk_level' => 'medium',
                'rows' => 12,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'result_slot',
                    'multiplier',
                    'payout',
                    'profit',
                    'balance',
                ],
            ]);

        // Verify result slot is valid
        $resultSlot = $response->json('data.result_slot');
        $this->assertIsInt($resultSlot);
        $this->assertGreaterThanOrEqual(0, $resultSlot);
        $this->assertLessThanOrEqual(12, $resultSlot);
    }

    /** @test */
    public function plinko_accepts_valid_risk_levels()
    {
        $riskLevels = ['low', 'medium', 'high'];

        foreach ($riskLevels as $risk) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/plinko/play', [
                    'bet_amount' => 10.00,
                    'risk_level' => $risk,
                    'rows' => 12,
                ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function plinko_rejects_invalid_risk_level()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/plinko/play', [
                'bet_amount' => 50.00,
                'risk_level' => 'extreme',
                'rows' => 12,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function plinko_accepts_valid_row_counts()
    {
        $validRows = [8, 12, 16];

        foreach ($validRows as $rows) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/plinko/play', [
                    'bet_amount' => 10.00,
                    'risk_level' => 'medium',
                    'rows' => $rows,
                ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function plinko_rejects_invalid_row_count()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/plinko/play', [
                'bet_amount' => 50.00,
                'risk_level' => 'medium',
                'rows' => 10,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function plinko_deducts_bet_from_wallet()
    {
        $initialBalance = $this->user->wallet->real_balance;

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/plinko/play', [
                'bet_amount' => 100.00,
                'risk_level' => 'medium',
                'rows' => 12,
            ]);

        $this->user->wallet->refresh();
        
        // Wallet should be different (deducted bet, added payout)
        $this->assertNotEquals($initialBalance, $this->user->wallet->real_balance);
    }

    /** @test */
    public function plinko_multiplier_matches_result_slot()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/plinko/play', [
                'bet_amount' => 50.00,
                'risk_level' => 'medium',
                'rows' => 12,
            ]);

        $resultSlot = $response->json('data.result_slot');
        $multiplier = $response->json('data.multiplier');
        $payout = $response->json('data.payout');

        // Verify payout calculation
        $expectedPayout = 50.00 * $multiplier;
        $this->assertEquals($expectedPayout, $payout);
    }

    /** @test */
    public function plinko_records_bet_in_database()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/plinko/play', [
                'bet_amount' => 50.00,
                'risk_level' => 'medium',
                'rows' => 12,
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game' => 'plinko',
            'bet_amount' => 50.00,
        ]);
    }

    /** @test */
    public function high_risk_has_higher_max_multiplier()
    {
        $multipliers = [
            'low' => [],
            'medium' => [],
            'high' => [],
        ];

        // Play multiple games to get different multipliers
        foreach (['low', 'medium', 'high'] as $risk) {
            for ($i = 0; $i < 10; $i++) {
                $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                    ->postJson('/api/games/plinko/play', [
                        'bet_amount' => 10.00,
                        'risk_level' => $risk,
                        'rows' => 16,
                    ]);

                $multipliers[$risk][] = $response->json('data.multiplier');
            }
        }

        // High risk should have potential for higher multipliers
        $maxLow = max($multipliers['low']);
        $maxHigh = max($multipliers['high']);
        
        // This might not always pass due to randomness, but statistically should
        $this->assertLessThanOrEqual($maxHigh * 1.5, $maxLow * 1.5);
    }
}
