<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KenoGameTest extends TestCase
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
    public function user_can_play_keno()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [5, 12, 18, 25, 33, 40],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'drawn_numbers',
                    'matches',
                    'multiplier',
                    'payout',
                    'profit',
                    'balance',
                ],
            ]);

        // Verify drawn numbers
        $drawnNumbers = $response->json('data.drawn_numbers');
        $this->assertIsArray($drawnNumbers);
        $this->assertCount(10, $drawnNumbers);
    }

    /** @test */
    public function keno_requires_valid_number_count()
    {
        // Too few numbers
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [5],
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        // Too many numbers
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => range(1, 15),
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function keno_requires_unique_numbers()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [5, 5, 12, 18, 25, 33],
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function keno_numbers_must_be_in_valid_range()
    {
        // Number too low
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [0, 5, 12, 18, 25, 33],
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        // Number too high
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [5, 12, 18, 25, 33, 41],
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function keno_calculates_matches_correctly()
    {
        $selectedNumbers = [5, 12, 18, 25, 33, 40];
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => $selectedNumbers,
            ]);

        $drawnNumbers = $response->json('data.drawn_numbers');
        $matches = $response->json('data.matches');

        // Count actual matches
        $actualMatches = count(array_intersect($selectedNumbers, $drawnNumbers));
        
        $this->assertEquals($actualMatches, $matches);
    }

    /** @test */
    public function keno_payout_increases_with_more_matches()
    {
        $payouts = [];

        // Play multiple games to get different match counts
        for ($i = 0; $i < 20; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/keno/play', [
                    'bet_amount' => 10.00,
                    'selected_numbers' => [5, 12, 18, 25, 33, 40],
                ]);

            $matches = $response->json('data.matches');
            $multiplier = $response->json('data.multiplier');
            
            if (!isset($payouts[$matches])) {
                $payouts[$matches] = $multiplier;
            }
        }

        // Verify that more matches = higher multiplier (if we got different match counts)
        if (count($payouts) > 1) {
            ksort($payouts);
            $previousMultiplier = 0;
            foreach ($payouts as $matches => $multiplier) {
                $this->assertGreaterThanOrEqual($previousMultiplier, $multiplier);
                $previousMultiplier = $multiplier;
            }
        } else {
            $this->markTestIncomplete('Need more variety in match counts');
        }
    }

    /** @test */
    public function keno_deducts_bet_from_wallet()
    {
        $initialBalance = $this->user->wallet->real_balance;

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 100.00,
                'selected_numbers' => [5, 12, 18, 25, 33, 40],
            ]);

        $this->user->wallet->refresh();
        
        // Balance should change (bet deducted, payout added)
        $this->assertNotEquals($initialBalance, $this->user->wallet->real_balance);
    }

    /** @test */
    public function keno_accepts_different_spot_counts()
    {
        $spotCounts = [
            2 => [5, 12],
            4 => [5, 12, 18, 25],
            6 => [5, 12, 18, 25, 33, 40],
            8 => [5, 12, 18, 25, 33, 40, 7, 15],
            10 => [5, 12, 18, 25, 33, 40, 7, 15, 22, 38],
        ];

        foreach ($spotCounts as $count => $numbers) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->postJson('/api/games/keno/play', [
                    'bet_amount' => 10.00,
                    'selected_numbers' => $numbers,
                ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function keno_records_bet_in_database()
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [5, 12, 18, 25, 33, 40],
            ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'game' => 'keno',
            'bet_amount' => 50.00,
        ]);
    }

    /** @test */
    public function keno_drawn_numbers_are_unique()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [5, 12, 18, 25, 33, 40],
            ]);

        $drawnNumbers = $response->json('data.drawn_numbers');
        
        // Check for duplicates
        $uniqueDrawn = array_unique($drawnNumbers);
        $this->assertCount(count($drawnNumbers), $uniqueDrawn);
    }

    /** @test */
    public function keno_drawn_numbers_are_in_valid_range()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/games/keno/play', [
                'bet_amount' => 50.00,
                'selected_numbers' => [5, 12, 18, 25, 33, 40],
            ]);

        $drawnNumbers = $response->json('data.drawn_numbers');
        
        foreach ($drawnNumbers as $number) {
            $this->assertGreaterThanOrEqual(1, $number);
            $this->assertLessThanOrEqual(40, $number);
        }
    }
}
