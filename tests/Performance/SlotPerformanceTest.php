<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\SlotProvider;
use App\Models\SlotGame;
use App\Models\SlotSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SlotPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private SlotProvider $provider;
    private SlotGame $game;
    private array $users = [];
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create test provider
        $this->provider = SlotProvider::create([
            'code' => 'PERF_TEST',
            'name' => 'Performance Test Provider',
            'api_url' => 'https://test-api.example.com',
            'agency_uid' => 'test_agency_perf',
            'aes_key' => 'test_aes_key_32_characters_perf',
            'player_prefix' => 'perf',
            'is_active' => true,
            'supports_seamless_wallet' => true,
            'supports_transfer_wallet' => false,
            'supports_demo_mode' => true,
            'session_timeout_minutes' => 30,
            'currency' => 'PHP',
        ]);

        // Create test game
        $this->game = SlotGame::create([
            'provider_id' => $this->provider->id,
            'provider_code' => $this->provider->code,
            'provider_name' => $this->provider->name,
            'game_id' => 'perf_game_001',
            'external_game_id' => 'perf_game_001',
            'name' => 'Performance Test Game',
            'category' => 'Video Slots',
            'thumbnail_url' => 'https://example.com/thumbnail.jpg',
            'is_active' => true,
            'min_bet' => 1.00,
            'max_bet' => 1000.00,
            'rtp' => 96.50,
            'volatility' => 'medium',
            'lines' => 20,
        ]);

        // Mock HTTP responses
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'launchUrl' => 'https://game.example.com/play?token=test123',
                ]
            ], 200)
        ]);
    }

    /** @test */
    public function test_api_response_time_for_provider_list()
    {
        $user = $this->createUserWithBalance();
        $token = auth('api')->login($user);

        $iterations = 20; // Reduced to avoid rate limiting
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            $response = $this->getJson('/api/slots/providers', [
                'Authorization' => 'Bearer ' . $token
            ]);
            
            $end = microtime(true);
            $times[] = ($end - $start) * 1000; // Convert to milliseconds
            
            if ($response->status() === 429) {
                // Skip rate limited requests
                array_pop($times);
                usleep(50000); // 50ms delay
                continue;
            }
            
            $response->assertStatus(200);
            usleep(10000); // 10ms delay between requests
        }

        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);

        echo "\n=== Provider List Performance ===\n";
        echo "Iterations: " . count($times) . "\n";
        echo "Average: " . number_format($avgTime, 2) . "ms\n";
        echo "Min: " . number_format($minTime, 2) . "ms\n";
        echo "Max: " . number_format($maxTime, 2) . "ms\n";
        echo "Target: <100ms\n";

        $this->assertLessThan(100, $avgTime, "Average response time should be under 100ms");
    }

    /** @test */
    public function test_api_response_time_for_game_list()
    {
        // Create 50 games for more realistic test
        for ($i = 1; $i <= 50; $i++) {
            SlotGame::create([
                'provider_id' => $this->provider->id,
                'provider_code' => $this->provider->code,
                'provider_name' => $this->provider->name,
                'game_id' => "game_{$i}",
                'external_game_id' => "ext_game_{$i}",
                'name' => "Test Game {$i}",
                'category' => 'Video Slots',
                'is_active' => true,
                'min_bet' => 1.00,
                'max_bet' => 1000.00,
                'rtp' => 96.00 + ($i * 0.01),
            ]);
        }

        $user = $this->createUserWithBalance();
        $token = auth('api')->login($user);

        $iterations = 20; // Reduced to avoid rate limiting
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            $response = $this->getJson('/api/slots/games', [
                'Authorization' => 'Bearer ' . $token
            ]);
            
            $end = microtime(true);
            $times[] = ($end - $start) * 1000;
            
            if ($response->status() === 429) {
                array_pop($times);
                usleep(50000);
                continue;
            }
            
            $response->assertStatus(200);
            usleep(10000);
        }

        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);

        echo "\n=== Game List Performance (51 games) ===\n";
        echo "Iterations: " . count($times) . "\n";
        echo "Average: " . number_format($avgTime, 2) . "ms\n";
        echo "Min: " . number_format($minTime, 2) . "ms\n";
        echo "Max: " . number_format($maxTime, 2) . "ms\n";
        echo "Target: <200ms\n";

        $this->assertLessThan(200, $avgTime, "Average response time should be under 200ms");
    }

    /** @test */
    public function test_concurrent_game_launches()
    {
        $userCount = 10;
        $times = [];

        // Create users
        for ($i = 0; $i < $userCount; $i++) {
            $user = $this->createUserWithBalance(5000);
            $this->users[] = $user;
            $this->tokens[] = auth('api')->login($user);
        }

        echo "\n=== Concurrent Game Launch Test ===\n";
        echo "Concurrent Users: {$userCount}\n";

        $start = microtime(true);

        // Simulate concurrent launches
        foreach ($this->users as $index => $user) {
            $launchStart = microtime(true);
            
            $response = $this->postJson("/api/slots/games/{$this->game->id}/launch", [
                'demo_mode' => false,
            ], [
                'Authorization' => 'Bearer ' . $this->tokens[$index]
            ]);
            
            $launchEnd = microtime(true);
            $times[] = ($launchEnd - $launchStart) * 1000;
            
            $response->assertStatus(200);
        }

        $end = microtime(true);
        $totalTime = ($end - $start) * 1000;

        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);

        echo "Total Time: " . number_format($totalTime, 2) . "ms\n";
        echo "Average per Launch: " . number_format($avgTime, 2) . "ms\n";
        echo "Min: " . number_format($minTime, 2) . "ms\n";
        echo "Max: " . number_format($maxTime, 2) . "ms\n";
        echo "Target: <500ms per launch\n";

        // Verify all sessions were created
        $sessionCount = SlotSession::where('game_id', $this->game->id)->count();
        $this->assertEquals($userCount, $sessionCount, "All sessions should be created");

        $this->assertLessThan(500, $avgTime, "Average launch time should be under 500ms");
    }

    /** @test */
    public function test_database_query_performance_for_active_sessions()
    {
        // Create 100 users with active sessions
        for ($i = 0; $i < 100; $i++) {
            $user = $this->createUserWithBalance();
            
            SlotSession::create([
                'user_id' => $user->id,
                'game_id' => $this->game->id,
                'provider_id' => $this->provider->id,
                'game_name' => $this->game->name,
                'provider_name' => $this->provider->name,
                'session_token' => bin2hex(random_bytes(64)),
                'launch_url' => 'https://example.com/game',
                'status' => 'active',
                'initial_balance' => 1000.00,
                'final_balance' => 1000.00,
                'total_bets' => 0,
                'total_wins' => 0,
                'rounds_played' => 0,
                'expires_at' => now()->addMinutes(30),
                'demo_mode' => false,
            ]);
        }

        $iterations = 50;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            // Query active sessions (typical operation)
            $sessions = SlotSession::where('status', 'active')
                ->where('expires_at', '>', now())
                ->with(['user', 'game', 'provider'])
                ->get();
            
            $end = microtime(true);
            $times[] = ($end - $start) * 1000;
        }

        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);

        echo "\n=== Active Sessions Query Performance (100 sessions) ===\n";
        echo "Iterations: {$iterations}\n";
        echo "Sessions Found: " . $sessions->count() . "\n";
        echo "Average: " . number_format($avgTime, 2) . "ms\n";
        echo "Min: " . number_format($minTime, 2) . "ms\n";
        echo "Max: " . number_format($maxTime, 2) . "ms\n";
        echo "Target: <50ms\n";

        $this->assertLessThan(50, $avgTime, "Query should be under 50ms");
    }

    /** @test */
    public function test_cache_effectiveness()
    {
        $user = $this->createUserWithBalance();
        $token = auth('api')->login($user);

        // Clear cache
        Cache::flush();

        // First request (no cache)
        $start1 = microtime(true);
        $response1 = $this->getJson('/api/slots/providers', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $time1 = (microtime(true) - $start1) * 1000;

        // Second request (with cache)
        $start2 = microtime(true);
        $response2 = $this->getJson('/api/slots/providers', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $time2 = (microtime(true) - $start2) * 1000;

        // Third request (with cache)
        $start3 = microtime(true);
        $response3 = $this->getJson('/api/slots/providers', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $time3 = (microtime(true) - $start3) * 1000;

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $response3->assertStatus(200);

        $avgCachedTime = ($time2 + $time3) / 2;
        $improvement = (($time1 - $avgCachedTime) / $time1) * 100;

        echo "\n=== Cache Effectiveness Test ===\n";
        echo "First Request (No Cache): " . number_format($time1, 2) . "ms\n";
        echo "Second Request (Cached): " . number_format($time2, 2) . "ms\n";
        echo "Third Request (Cached): " . number_format($time3, 2) . "ms\n";
        echo "Average Cached: " . number_format($avgCachedTime, 2) . "ms\n";
        echo "Improvement: " . number_format($improvement, 1) . "%\n";
        echo "Target: >30% improvement\n";

        $this->assertGreaterThan(30, $improvement, "Cache should improve performance by at least 30%");
    }

    /** @test */
    public function test_search_performance_with_large_dataset()
    {
        // Create 500 games
        for ($i = 1; $i <= 500; $i++) {
            SlotGame::create([
                'provider_id' => $this->provider->id,
                'provider_code' => $this->provider->code,
                'provider_name' => $this->provider->name,
                'game_id' => "search_game_{$i}",
                'external_game_id' => "ext_search_game_{$i}",
                'name' => "Search Test Game {$i}",
                'category' => ['Video Slots', 'Classic Slots', 'Jackpot'][rand(0, 2)],
                'is_active' => true,
                'min_bet' => 1.00,
                'max_bet' => 1000.00,
                'rtp' => 95.00 + rand(0, 300) / 100,
            ]);
        }

        $user = $this->createUserWithBalance();
        $token = auth('api')->login($user);

        $searchQueries = ['Test', 'Game', 'Slot', '100', '200'];
        $times = [];

        foreach ($searchQueries as $query) {
            $start = microtime(true);
            
            $response = $this->getJson("/api/slots/games/search?q={$query}", [
                'Authorization' => 'Bearer ' . $token
            ]);
            
            $end = microtime(true);
            $times[] = ($end - $start) * 1000;
            
            $response->assertStatus(200);
        }

        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);

        echo "\n=== Search Performance (500 games) ===\n";
        echo "Search Queries: " . count($searchQueries) . "\n";
        echo "Average: " . number_format($avgTime, 2) . "ms\n";
        echo "Min: " . number_format($minTime, 2) . "ms\n";
        echo "Max: " . number_format($maxTime, 2) . "ms\n";
        echo "Target: <300ms\n";

        $this->assertLessThan(300, $avgTime, "Search should be under 300ms");
    }

    /** @test */
    public function test_wallet_transaction_performance()
    {
        $user = $this->createUserWithBalance(10000);
        
        $iterations = 100;
        $times = [];

        // Simulate bet transactions
        for ($i = 0; $i < $iterations; $i++) {
            $session = SlotSession::create([
                'user_id' => $user->id,
                'game_id' => $this->game->id,
                'provider_id' => $this->provider->id,
                'game_name' => $this->game->name,
                'provider_name' => $this->provider->name,
                'session_token' => bin2hex(random_bytes(64)),
                'launch_url' => 'https://example.com/game',
                'status' => 'active',
                'initial_balance' => $user->wallet->real_balance,
                'final_balance' => $user->wallet->real_balance,
                'expires_at' => now()->addMinutes(30),
                'demo_mode' => false,
            ]);

            $start = microtime(true);
            
            // Simulate a bet transaction
            $betAmount = 10.00;
            $externalTxnId = "perf_bet_{$i}_" . time();
            
            try {
                DB::transaction(function () use ($user, $session, $betAmount, $externalTxnId) {
                    $wallet = $user->wallet()->lockForUpdate()->first();
                    
                    if ($wallet->real_balance >= $betAmount) {
                        $balanceBefore = $wallet->real_balance;
                        $wallet->real_balance -= $betAmount;
                        $wallet->save();
                        
                        // Create transaction record
                        $user->transactions()->create([
                            'type' => 'bet',
                            'amount' => $betAmount,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $wallet->real_balance,
                            'description' => 'Slot game bet',
                        ]);
                    }
                });
            } catch (\Exception $e) {
                // Handle transaction failure
            }
            
            $end = microtime(true);
            $times[] = ($end - $start) * 1000;
        }

        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);

        echo "\n=== Wallet Transaction Performance ===\n";
        echo "Transactions: {$iterations}\n";
        echo "Average: " . number_format($avgTime, 2) . "ms\n";
        echo "Min: " . number_format($minTime, 2) . "ms\n";
        echo "Max: " . number_format($maxTime, 2) . "ms\n";
        echo "Target: <100ms\n";

        $this->assertLessThan(100, $avgTime, "Transaction time should be under 100ms");
    }

    /** @test */
    public function test_session_cleanup_performance()
    {
        // Create 1000 expired sessions
        for ($i = 0; $i < 1000; $i++) {
            $user = $this->createUserWithBalance();
            
            SlotSession::create([
                'user_id' => $user->id,
                'game_id' => $this->game->id,
                'provider_id' => $this->provider->id,
                'game_name' => $this->game->name,
                'provider_name' => $this->provider->name,
                'session_token' => bin2hex(random_bytes(64)),
                'launch_url' => 'https://example.com/game',
                'status' => 'active',
                'initial_balance' => 1000.00,
                'final_balance' => 1000.00,
                'expires_at' => now()->subMinutes(rand(1, 60)),
                'demo_mode' => false,
            ]);
        }

        $start = microtime(true);
        
        // Expire old sessions
        $expiredCount = SlotSession::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update([
                'status' => 'expired',
                'ended_at' => now(),
            ]);
        
        $end = microtime(true);
        $time = ($end - $start) * 1000;

        echo "\n=== Session Cleanup Performance ===\n";
        echo "Sessions Expired: {$expiredCount}\n";
        echo "Time: " . number_format($time, 2) . "ms\n";
        echo "Target: <1000ms\n";

        $this->assertEquals(1000, $expiredCount);
        $this->assertLessThan(1000, $time, "Cleanup should be under 1 second");
    }

    /** @test */
    public function test_memory_usage_during_game_listing()
    {
        // Create 1000 games
        for ($i = 1; $i <= 1000; $i++) {
            SlotGame::create([
                'provider_id' => $this->provider->id,
                'provider_code' => $this->provider->code,
                'provider_name' => $this->provider->name,
                'game_id' => "memory_game_{$i}",
                'external_game_id' => "ext_memory_game_{$i}",
                'name' => "Memory Test Game {$i}",
                'category' => 'Video Slots',
                'is_active' => true,
                'min_bet' => 1.00,
                'max_bet' => 1000.00,
                'rtp' => 96.00,
            ]);
        }

        $user = $this->createUserWithBalance();
        $token = auth('api')->login($user);

        $memoryBefore = memory_get_usage(true);
        
        $response = $this->getJson('/api/slots/games', [
            'Authorization' => 'Bearer ' . $token
        ]);
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        $response->assertStatus(200);

        echo "\n=== Memory Usage Test (1000 games) ===\n";
        echo "Memory Before: " . number_format($memoryBefore / 1024 / 1024, 2) . " MB\n";
        echo "Memory After: " . number_format($memoryAfter / 1024 / 1024, 2) . " MB\n";
        echo "Memory Used: " . number_format($memoryUsed, 2) . " MB\n";
        echo "Target: <50 MB\n";

        $this->assertLessThan(50, $memoryUsed, "Memory usage should be under 50MB");
    }

    private function createUserWithBalance(float $balance = 1000.00): User
    {
        $user = User::factory()->create();
        $user->wallet()->create([
            'real_balance' => $balance,
            'bonus_balance' => 0,
            'locked_balance' => 0,
        ]);
        return $user;
    }
}
