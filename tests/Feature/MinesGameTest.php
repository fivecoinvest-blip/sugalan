<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bet;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MinesGameTest extends TestCase
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
    public function user_can_start_mines_game()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'bet_id',
                    'mines_count',
                    'revealed_tiles',
                    'current_multiplier',
                ],
            ]);

        // Verify wallet deduction
        $this->user->wallet->refresh();
        $this->assertEquals(950.00, $this->user->wallet->real_balance);
    }

    /** @test */
    public function mines_count_must_be_valid()
    {
        // Too few mines
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        // Too many mines
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 25,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function user_can_reveal_safe_tile()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 3,
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Reveal tile
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/reveal', [
                'bet_id' => $betId,
                'tile_index' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_mine',
                    'revealed_tiles',
                    'current_multiplier',
                    'game_over',
                ],
            ]);
    }

    /** @test */
    public function revealing_mine_ends_game()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 20, // High mine count increases chance
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Keep revealing until we hit a mine or reveal all safe tiles
        $hitMine = false;
        for ($i = 0; $i < 25 && !$hitMine; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/mines/reveal', [
                    'bet_id' => $betId,
                    'tile_index' => $i,
                ]);

            if ($response->json('data.is_mine')) {
                $hitMine = true;
                $this->assertTrue($response->json('data.game_over'));
            }
        }

        // With 20 mines, we should hit one eventually
        $this->assertTrue($hitMine || $response->json('data.game_over'));
    }

    /** @test */
    public function user_can_cashout_mines_game()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 5,
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Reveal a few safe tiles
        for ($i = 0; $i < 3; $i++) {
            $revealResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/mines/reveal', [
                    'bet_id' => $betId,
                    'tile_index' => $i,
                ]);

            if ($revealResponse->json('data.is_mine')) {
                $this->markTestSkipped('Hit mine during reveal');
            }
        }

        // Cashout
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/cashout', [
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

        // Verify payout was credited
        $payout = $response->json('data.payout');
        $this->user->wallet->refresh();
        $this->assertGreaterThan(950.00, $this->user->wallet->real_balance);
    }

    /** @test */
    public function cannot_reveal_same_tile_twice()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 5,
            ]);

        $betId = $startResponse->json('data.bet_id');

        // Reveal tile
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/reveal', [
                'bet_id' => $betId,
                'tile_index' => 5,
            ]);

        // Try to reveal same tile again
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/reveal', [
                'bet_id' => $betId,
                'tile_index' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function multiplier_increases_with_safe_reveals()
    {
        // Start game
        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 3,
            ]);

        $betId = $startResponse->json('data.bet_id');
        $initialMultiplier = $startResponse->json('data.current_multiplier');

        // Reveal a safe tile
        $revealResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/reveal', [
                'bet_id' => $betId,
                'tile_index' => 0,
            ]);

        if (!$revealResponse->json('data.is_mine')) {
            $newMultiplier = $revealResponse->json('data.current_multiplier');
            $this->assertGreaterThan($initialMultiplier, $newMultiplier);
        }
    }

    /** @test */
    public function mines_game_records_bet_in_database()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/mines/start', [
                'bet_amount' => 50.00,
                'mines_count' => 5,
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game_type' => 'mines',
            'bet_amount' => 50.00,
        ]);
    }
}
