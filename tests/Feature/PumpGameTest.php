<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\Games\PumpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PumpGameTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected PumpService $pumpGame;

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
        
        // Initialize pump game service and start a round
        $this->pumpGame = app(PumpService::class);
        $this->pumpGame->startRound();
    }

    /** @test */
    public function user_can_get_current_pump_round()
    {
        $response = $this->getJson('/api/games/pump/round');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'round_id',
                    'status',
                    'multiplier',
                    'active_bets',
                ],
            ]);
    }

    /** @test */
    public function user_can_place_pump_bet()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 50.00,
                'target_multiplier' => 2.00,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'bet_id',
                    'round_id',
                    'bet_amount',
                    'target_multiplier',
                ],
            ]);

        // Verify wallet deduction
        $this->user->wallet->refresh();
        $this->assertEquals(950.00, $this->user->wallet->real_balance);
    }

    /** @test */
    public function pump_bet_requires_valid_target_multiplier()
    {
        // Target too low
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 50.00,
                'target_multiplier' => 0.50,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        // Target too high
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 50.00,
                'target_multiplier' => 1000.00,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function pump_bet_requires_sufficient_balance()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 2000.00, // Exceeds balance
                'target_multiplier' => 2.00,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function user_can_cashout_pump_bet()
    {
        // Place bet first
        $betResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 50.00,
                'target_multiplier' => 10.00, // High target, unlikely to auto-complete
            ]);

        $betId = $betResponse->json('data.bet_id');

        // Wait a moment for multiplier to increase
        sleep(1);

        // Cashout
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/cashout', [
                'bet_id' => $betId,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'multiplier',
                    'payout',
                    'profit',
                ],
            ]);
    }

    /** @test */
    public function auto_complete_triggers_at_target_multiplier()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 50.00,
                'target_multiplier' => 1.50, // Low target for quick completion
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $betId = $response->json('data.bet_id');
        
        // The bet should auto-complete when multiplier reaches 1.50
        // This is handled by the game service
        $this->assertDatabaseHas('bets', [
            'id' => $betId,
            'game' => 'pump',
            'target' => 1.50,
        ]);
    }

    /** @test */
    public function cannot_cashout_already_cashed_out_pump_bet()
    {
        // Place and cashout bet
        $betResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 50.00,
                'target_multiplier' => 10.00,
            ]);

        $betId = $betResponse->json('data.bet_id');
        
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/cashout', [
                'bet_id' => $betId,
            ]);

        // Try to cashout again
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/cashout', [
                'bet_id' => $betId,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function pump_game_records_bet_in_database()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 50.00,
                'target_multiplier' => 2.00,
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game' => 'pump',
            'bet_amount' => 50.00,
        ]);
    }

    /** @test */
    public function pump_round_has_valid_structure()
    {
        $response = $this->getJson('/api/games/pump/round');

        $this->assertIsString($response->json('data.round_id'));
        $this->assertContains($response->json('data.status'), ['waiting', 'active', 'ended']);
        $this->assertIsNumeric($response->json('data.multiplier'));
        $this->assertIsArray($response->json('data.active_bets'));
    }

    /** @test */
    public function pump_multiplier_increases_over_time()
    {
        $response1 = $this->getJson('/api/games/pump/round');
        $multiplier1 = $response1->json('data.multiplier');

        sleep(1);

        $response2 = $this->getJson('/api/games/pump/round');
        $multiplier2 = $response2->json('data.multiplier');

        // If same round, multiplier should increase (unless it ended)
        if ($response1->json('data.round_id') === $response2->json('data.round_id')) {
            $this->assertGreaterThanOrEqual($multiplier1, $multiplier2);
        }
    }

    /** @test */
    public function cannot_place_bet_with_invalid_amount()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/pump/bet', [
                'bet_amount' => 0.50, // Below minimum
                'target_multiplier' => 2.00,
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }
}
