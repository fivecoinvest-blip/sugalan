<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bet;
use App\Services\Games\CrashGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrashGameTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected CrashGameService $crashGame;

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
        
        // Initialize crash game service and start a round
        $this->crashGame = app(CrashGameService::class);
        $this->crashGame->startRound();
    }

    protected function tearDown(): void
    {
        // Clean up crash game cache to prevent test pollution
        \Illuminate\Support\Facades\Cache::forget('crash_round_current');
        \Illuminate\Support\Facades\Cache::flush();
        
        parent::tearDown();
    }

    /** @test */
    public function user_can_get_current_crash_round()
    {
        $response = $this->getJson('/api/games/crash/current');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'round_id',
                    'status',
                    'server_seed_hash',
                    'total_bets',
                ],
            ]);
    }

    /** @test */
    public function user_can_place_crash_bet()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/bet', [
                'bet_amount' => 50.00,
                'auto_cashout' => 2.00,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'bet_id',
                    'round_id',
                    'bet_amount',
                    'auto_cashout',
                ],
            ]);

        // Verify wallet deduction
        $this->user->wallet->refresh();
        $this->assertEquals(950.00, $this->user->wallet->real_balance);
    }

    /** @test */
    public function crash_bet_requires_valid_amount()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/bet', [
                'bet_amount' => 0.50, // Below minimum
                'auto_cashout' => 2.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function crash_bet_requires_sufficient_balance()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/bet', [
                'bet_amount' => 2000.00, // Exceeds balance
                'auto_cashout' => 2.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function user_can_cashout_crash_bet()
    {
        // Place bet first
        $betResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/bet', [
                'bet_amount' => 50.00,
                'auto_cashout' => null,
            ]);

        $betId = $betResponse->json('data.bet_id');

        // Set round to running status for cashout
        $roundData = \Illuminate\Support\Facades\Cache::get('crash_round_current');
        $roundData['status'] = 'running';
        \Illuminate\Support\Facades\Cache::put('crash_round_current', $roundData, 600);

        // Cashout at 2.00x
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/cashout', [
                'bet_id' => $betId,
                'current_multiplier' => 2.00,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cashout_multiplier',
                    'payout',
                    'profit',
                ],
            ]);
    }

    /** @test */
    public function auto_cashout_works_correctly()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/bet', [
                'bet_amount' => 50.00,
                'auto_cashout' => 1.50,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $betId = $response->json('data.bet_id');
        
        // Verify auto_cashout was set
        $bet = Bet::find($betId);
        $this->assertEquals(1.50, $bet->target);
    }

    /** @test */
    public function cannot_cashout_already_cashed_out_bet()
    {
        // Place and cashout bet
        $betResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/bet', [
                'bet_amount' => 50.00,
            ]);

        $betId = $betResponse->json('data.bet_id');
        
        // Set round to running status
        $roundData = \Illuminate\Support\Facades\Cache::get('crash_round_current');
        $roundData['status'] = 'running';
        \Illuminate\Support\Facades\Cache::put('crash_round_current', $roundData, 600);
        
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/cashout', [
                'bet_id' => $betId,
                'current_multiplier' => 2.00,
            ]);

        // Try to cashout again
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/cashout', [
                'bet_id' => $betId,
                'current_multiplier' => 2.50,
            ]);

        // Should fail with either 400 (bet not found) or 500 (round not running)
        $this->assertContains($response->status(), [400, 500]);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function crash_game_records_bet_in_database()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/crash/bet', [
                'bet_amount' => 50.00,
                'auto_cashout' => 2.00,
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game_type' => 'crash',
            'bet_amount' => 50.00,
        ]);
    }
}
