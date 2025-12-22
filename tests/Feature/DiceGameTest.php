<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bet;
use App\Models\Seed;
use App\Services\Games\DiceGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DiceGameTest extends TestCase
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
    public function user_can_play_dice_game()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'result',
                    'multiplier',
                    'payout',
                    'profit',
                    'balance',
                ],
            ]);
    }

    /** @test */
    public function dice_result_is_within_valid_range()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $result = $response->json('result');
        
        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThanOrEqual(100, $result);
    }

    /** @test */
    public function dice_game_deducts_bet_from_wallet()
    {
        $initialBalance = $this->user->wallet->real_balance;

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 100.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $this->user->wallet->refresh();
        
        // Balance should be less after bet (minus bet, plus potential payout)
        $this->assertNotEquals($initialBalance, $this->user->wallet->real_balance);
    }

    /** @test */
    public function dice_game_creates_bet_record()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game_type' => 'dice',
            'bet_amount' => 10.00,
        ]);
    }

    /** @test */
    public function dice_game_uses_provably_fair_seeds()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $bet = Bet::where('user_id', $this->user->id)->latest()->first();
        
        $this->assertNotNull($bet->server_seed_hash);
        $this->assertNotNull($bet->client_seed);
        $this->assertIsInt($bet->nonce);
    }

    /** @test */
    public function dice_game_requires_authentication()
    {
        $response = $this->postJson('/api/games/dice/play', [
            'bet_amount' => 10.00,
            'target' => 50.00,
            'prediction' => 'over',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function dice_game_validates_bet_amount()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 0,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bet_amount']);
    }

    /** @test */
    public function dice_game_validates_target_range()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 150.00, // Invalid: > 100
                'prediction' => 'over',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target']);
    }

    /** @test */
    public function dice_game_validates_prediction()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prediction']);
    }

    /** @test */
    public function user_cannot_bet_more_than_balance()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 2000.00, // More than 1000 balance
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $response->assertStatus(500)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['success', 'message']);
    }

    /** @test */
    public function winning_bet_credits_wallet()
    {
        // Play multiple times to get a win
        $won = false;
        $maxAttempts = 50;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/dice/play', [
                    'bet_amount' => 10.00,
                    'target' => 50.00,
                    'prediction' => 'over',
                ]);
            
            if ($response->json('data.profit') > 0) {
                $won = true;
                $bet = Bet::where('user_id', $this->user->id)->latest()->first();
                
                $this->assertGreaterThan($bet->bet_amount, $bet->payout);
                $this->assertTrue($response->json('data.is_win'));
                break;
            }
        }
        
        // With 50% win chance, we should get at least one win in 50 attempts
        $this->assertTrue($won, 'No winning bet in ' . $maxAttempts . ' attempts');
    }

    /** @test */
    public function dice_game_calculates_correct_win_chance()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $winChance = $response->json('data.win_chance');
        
        // Over 50 should have 50% win chance
        $this->assertEquals(50.0, $winChance);
    }

    /** @test */
    public function dice_game_increments_nonce()
    {
        // Play first game
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $firstBet = Bet::where('user_id', $this->user->id)->latest()->first();
        
        // Play second game
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/dice/play', [
                'bet_amount' => 10.00,
                'target' => 50.00,
                'prediction' => 'over',
            ]);

        $secondBet = Bet::where('user_id', $this->user->id)->latest()->first();
        
        $this->assertEquals($firstBet->nonce + 1, $secondBet->nonce);
    }
}
