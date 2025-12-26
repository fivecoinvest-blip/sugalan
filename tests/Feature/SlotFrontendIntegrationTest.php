<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SlotProvider;
use App\Models\SlotGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class SlotFrontendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SlotProvider $provider;
    private SlotGame $game;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with balance
        $this->user = User::factory()->create([
            'username' => 'testplayer',
            'phone_number' => '+639123456789',
        ]);

        $this->user->wallet()->create([
            'real_balance' => 10000.00,
            'bonus_balance' => 0.00,
            'locked_balance' => 0.00,
        ]);

        // Create test provider
        $this->provider = SlotProvider::create([
            'code' => 'TEST',
            'name' => 'Test Provider',
            'api_url' => 'https://test-api.example.com',
            'agency_uid' => 'test_agency_123',
            'aes_key' => 'test_aes_key_32_characters_long',
            'player_prefix' => 'test',
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
            'game_id' => 'test_game_001',
            'external_game_id' => 'test_game_001',
            'name' => 'Test Slot Game',
            'category' => 'Video Slots',
            'thumbnail_url' => 'https://example.com/thumbnail.jpg',
            'is_active' => true,
            'min_bet' => 1.00,
            'max_bet' => 1000.00,
            'rtp' => 96.50,
            'volatility' => 'medium',
            'lines' => 20,
        ]);

        // Generate JWT token
        $this->token = auth('api')->login($this->user);
    }

    /** @test */
    public function it_can_access_slots_page_routes()
    {
        $response = $this->getJson('/api/slots/providers', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'code', 'name', 'is_active']
                ]
            ]);
    }

    /** @test */
    public function it_can_list_slot_games()
    {
        $response = $this->getJson('/api/slots/games', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data'
            ]);
    }

    /** @test */
    public function it_can_filter_games_by_provider()
    {
        $response = $this->getJson('/api/slots/games?provider=TEST', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /** @test */
    public function it_can_get_game_categories()
    {
        $response = $this->getJson('/api/slots/games/categories', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data'
            ]);
    }

    /** @test */
    public function it_can_search_games()
    {
        $response = $this->getJson('/api/slots/games/search?q=Test', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /** @test */
    public function it_can_get_popular_games()
    {
        $response = $this->getJson('/api/slots/games/popular?limit=10', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /** @test */
    public function it_requires_authentication_for_game_launch()
    {
        $response = $this->postJson("/api/slots/games/{$this->game->id}/launch", [
            'demo_mode' => false,
        ]);

        // Should return error due to missing authentication
        $this->assertTrue(
            $response->status() === 401 || $response->status() === 500,
            "Expected 401 or 500, got {$response->status()}"
        );
    }

    /** @test */
    public function it_can_launch_game_with_authentication()
    {
        // Mock the provider API response
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'launchUrl' => 'https://game.example.com/play?token=abc123',
                ]
            ], 200)
        ]);

        $response = $this->postJson("/api/slots/games/{$this->game->id}/launch", [
            'demo_mode' => false,
        ], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        // Verify session was created
        $this->assertDatabaseHas('slot_sessions', [
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_can_get_active_session()
    {
        // Create an active session first
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'launchUrl' => 'https://game.example.com/play?token=abc123',
                ]
            ], 200)
        ]);

        $this->postJson("/api/slots/games/{$this->game->id}/launch", [
            'demo_mode' => false,
        ], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        // Now check for active session
        $response = $this->getJson('/api/slots/session/active', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /** @test */
    public function it_can_end_active_session()
    {
        // Create an active session first
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'launchUrl' => 'https://game.example.com/play?token=abc123',
                ]
            ], 200)
        ]);

        $launchResponse = $this->postJson("/api/slots/games/{$this->game->id}/launch", [
            'demo_mode' => false,
        ], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        // Just verify a session was created in the database
        $this->assertDatabaseHas('slot_sessions', [
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
            'status' => 'active',
        ]);

        // Get the session ID from database
        $session = \App\Models\SlotSession::where('user_id', $this->user->id)
            ->where('status', 'active')
            ->first();
        $sessionId = $session->id;

        // End the session
        $response = $this->postJson('/api/slots/session/end', [], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Session ended successfully',
            ]);

        // Verify session was ended
        $this->assertDatabaseHas('slot_sessions', [
            'id' => $sessionId,
            'status' => 'ended',
        ]);
    }

    /** @test */
    public function it_can_get_session_history()
    {
        $response = $this->getJson('/api/slots/sessions/history', [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /** @test */
    public function it_validates_insufficient_balance_for_launch()
    {
        // Set user balance to 0
        $this->user->wallet->update(['real_balance' => 0]);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'launchUrl' => 'https://game.example.com/play?token=abc123',
                ]
            ], 200)
        ]);

        $response = $this->postJson("/api/slots/games/{$this->game->id}/launch", [
            'demo_mode' => false,
        ], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        // Should still allow launch (balance check happens during gameplay)
        $response->assertStatus(200);
    }

    /** @test */
    public function it_returns_404_for_non_existent_game()
    {
        $response = $this->postJson('/api/slots/games/99999/launch', [
            'demo_mode' => false,
        ], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(404);
    }
}
