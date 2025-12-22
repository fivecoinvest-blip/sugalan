<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HiLoGameTest extends TestCase
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
    public function user_can_start_hilo_game()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'bet_id',
                    'current_card',
                    'current_multiplier',
                    'cards_played',
                ],
            ]);

        // Verify current card is valid
        $card = $response->json('data.current_card');
        $this->assertIsArray($card);
        $this->assertArrayHasKey('rank', $card);
        $this->assertArrayHasKey('suit', $card);
        $this->assertArrayHasKey('value', $card);

        // Verify wallet deduction
        $this->user->wallet->refresh();
        $this->assertEquals(950.00, $this->user->wallet->real_balance);
    }

    /** @test */
    public function user_can_predict_higher()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Predict higher
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/predict', [
                'bet_id' => $betId,
                'prediction' => 'higher',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'correct',
                    'next_card',
                    'current_multiplier',
                    'game_over',
                ],
            ]);
    }

    /** @test */
    public function user_can_predict_lower()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Predict lower
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/predict', [
                'bet_id' => $betId,
                'prediction' => 'lower',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'correct',
                    'next_card',
                    'current_multiplier',
                    'game_over',
                ],
            ]);
    }

    /** @test */
    public function prediction_must_be_valid()
    {
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $betId = $startResponse->json('data.bet_id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/predict', [
                'bet_id' => $betId,
                'prediction' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function multiplier_increases_with_correct_predictions()
    {
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $betId = $startResponse->json('data.bet_id');
        $initialMultiplier = $startResponse->json('data.current_multiplier');

        // Make predictions until we get one correct
        $attempts = 0;
        while ($attempts < 10) {
            $predictResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/hilo/predict', [
                    'bet_id' => $betId,
                    'prediction' => 'higher',
                ]);

            if ($predictResponse->json('data.correct')) {
                $newMultiplier = $predictResponse->json('data.current_multiplier');
                $this->assertGreaterThan($initialMultiplier, $newMultiplier);
                break;
            }

            if ($predictResponse->json('data.game_over')) {
                $this->markTestSkipped('Game ended before correct prediction');
            }

            $attempts++;
        }
    }

    /** @test */
    public function user_can_cashout_hilo()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Cashout immediately
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/cashout', [
                'bet_id' => $betId,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payout',
                    'profit',
                    'multiplier',
                ],
            ]);
    }

    /** @test */
    public function wrong_prediction_ends_game()
    {
        // This test might need multiple attempts due to randomness
        $gameEnded = false;
        
        for ($attempt = 0; $attempt < 5 && !$gameEnded; $attempt++) {
            $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/hilo/start', [
                    'bet_amount' => 10.00,
                ]);

            $betId = $startResponse->json('data.bet_id');
            $currentCard = $startResponse->json('data.current_card');

            // Make 10 predictions, statistically one should be wrong
            for ($i = 0; $i < 10; $i++) {
                $predictResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                    ->postJson('/api/games/hilo/predict', [
                        'bet_id' => $betId,
                        'prediction' => 'higher',
                    ]);

                if (!$predictResponse->json('data.correct')) {
                    $this->assertTrue($predictResponse->json('data.game_over'));
                    $gameEnded = true;
                    break;
                }

                if ($predictResponse->json('data.game_over')) {
                    $gameEnded = true;
                    break;
                }
            }
        }

        $this->assertTrue($gameEnded, 'Expected at least one game to end');
    }

    /** @test */
    public function hilo_records_bet_in_database()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game_type' => 'hilo',
            'bet_amount' => 50.00,
        ]);
    }

    /** @test */
    public function cannot_predict_on_ended_game()
    {
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/start', [
                'bet_amount' => 50.00,
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Cashout to end game
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/cashout', [
                'bet_id' => $betId,
            ]);

        // Try to predict after cashout
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/hilo/predict', [
                'bet_id' => $betId,
                'prediction' => 'higher',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
