<?php

namespace Tests\Feature;

use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\SlotSession;
use App\Models\SlotTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SlotEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SlotIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SlotProvider $provider;
    private SlotGame $game;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with wallet
        $this->user = User::factory()->create();
        Wallet::create([
            'user_id' => $this->user->id,
            'real_balance' => 10000.00,
            'bonus_balance' => 0.00,
        ]);

        // Create test provider
        $this->provider = SlotProvider::create([
            'name' => 'Test Provider',
            'code' => 'test',
            'agency_uid' => 'test123',
            'aes_key' => '0123456789abcdef0123456789abcdef',
            'player_prefix' => 'test',
            'api_url' => 'https://test.example.com',
            'is_active' => true,
            'config' => [
                'environment' => 'test',
                'seamless_wallet' => true,
                'session_timeout' => 30,
            ],
        ]);

        // Create test game
        $this->game = SlotGame::create([
            'provider_id' => $this->provider->id,
            'game_id' => 'test_game_1',
            'name' => 'Test Slot Game',
            'category' => 'slots',
            'min_bet' => 1.00,
            'max_bet' => 1000.00,
            'is_active' => true,
        ]);

        // Generate JWT token
        $this->token = JWTAuth::fromUser($this->user);
    }

    /** @test */
    public function it_can_list_providers()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/slots/providers');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'is_active',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_list_games()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/slots/games');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_can_get_categories()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/slots/games/categories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['slots'],
            ]);
    }

    /** @test */
    public function it_can_search_games()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/slots/games/search?q=Test');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function encryption_service_works_correctly()
    {
        $service = new SlotEncryptionService();
        $key = '0123456789abcdef0123456789abcdef';
        
        $data = [
            'test' => 'value',
            'number' => 123,
            'array' => [1, 2, 3],
        ];

        // Encrypt
        $encrypted = $service->encrypt($data, $key);
        $this->assertIsString($encrypted);
        $this->assertNotEmpty($encrypted);

        // Decrypt
        $decrypted = $service->decrypt($encrypted, $key);
        $this->assertEquals($data, $decrypted);
    }

    /** @test */
    public function signature_generation_and_verification_works()
    {
        $service = new SlotEncryptionService();
        $key = '0123456789abcdef0123456789abcdef';
        $data = 'test data';
        $timestamp = time();

        // Generate signature
        $signature = $service->generateSignature($data, $key, $timestamp);
        $this->assertIsString($signature);
        $this->assertEquals(64, strlen($signature)); // SHA256 = 64 hex chars

        // Verify signature
        $this->assertTrue($service->verifySignature($data, $signature, $key, $timestamp));

        // Wrong signature should fail
        $this->assertFalse($service->verifySignature($data, 'wrong_signature', $key, $timestamp));
    }

    /** @test */
    public function it_can_create_session()
    {
        // Mock HTTP client to avoid actual API call
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'launchUrl' => 'https://test.example.com/game/launch?token=test'
                ]
            ], 200)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/slots/games/{$this->game->id}/launch", [
            'demo_mode' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'session_id',
                    'game_url',
                    'expires_at',
                ],
            ]);

        // Verify session was created
        $this->assertDatabaseHas('slot_sessions', [
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_can_process_bet_transaction()
    {
        // Create session
        $session = SlotSession::create([
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
            'provider_id' => $this->provider->id,
            'session_token' => 'test_token_123',
            'game_url' => 'https://test.example.com/game',
            'initial_balance' => 10000.00,
            'final_balance' => 10000.00,
            'status' => 'active',
            'expires_at' => now()->addMinutes(30),
        ]);

        $service = new SlotEncryptionService();
        
        $requestData = [
            'sessionToken' => 'test_token_123',
            'roundId' => 'round_123',
            'transactionId' => 'bet_123',
            'betAmount' => 100.00,
        ];

        $encryptedData = $service->encrypt($requestData, $this->provider->aes_key);
        $timestamp = time();
        $signature = $service->generateSignature($encryptedData, $this->provider->aes_key, $timestamp);

        $response = $this->postJson("/api/slots/callback/{$this->provider->code}/bet", [
            'data' => $encryptedData,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify transaction was created
        $this->assertDatabaseHas('slot_transactions', [
            'session_id' => $session->id,
            'round_id' => 'round_123',
            'external_txn_id' => 'bet_123',
            'type' => 'bet',
            'amount' => 100.00,
        ]);

        // Verify wallet balance was deducted
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(9900.00, $wallet->real_balance);
    }

    /** @test */
    public function it_can_process_win_transaction()
    {
        // Create session
        $session = SlotSession::create([
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
            'provider_id' => $this->provider->id,
            'session_token' => 'test_token_123',
            'game_url' => 'https://test.example.com/game',
            'initial_balance' => 10000.00,
            'final_balance' => 10000.00,
            'status' => 'active',
            'expires_at' => now()->addMinutes(30),
        ]);

        $service = new SlotEncryptionService();
        
        $requestData = [
            'sessionToken' => 'test_token_123',
            'roundId' => 'round_123',
            'transactionId' => 'win_123',
            'winAmount' => 500.00,
        ];

        $encryptedData = $service->encrypt($requestData, $this->provider->aes_key);
        $timestamp = time();
        $signature = $service->generateSignature($encryptedData, $this->provider->aes_key, $timestamp);

        $response = $this->postJson("/api/slots/callback/{$this->provider->code}/win", [
            'data' => $encryptedData,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify transaction was created
        $this->assertDatabaseHas('slot_transactions', [
            'session_id' => $session->id,
            'round_id' => 'round_123',
            'external_txn_id' => 'win_123',
            'type' => 'win',
            'amount' => 500.00,
        ]);

        // Verify wallet balance was credited
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(10500.00, $wallet->real_balance);
    }

    /** @test */
    public function it_prevents_duplicate_transactions()
    {
        // Create session
        $session = SlotSession::create([
            'user_id' => $this->user->id,
            'game_id' => $this->game->id,
            'provider_id' => $this->provider->id,
            'session_token' => 'test_token_123',
            'game_url' => 'https://test.example.com/game',
            'initial_balance' => 10000.00,
            'final_balance' => 10000.00,
            'status' => 'active',
            'expires_at' => now()->addMinutes(30),
        ]);

        $service = new SlotEncryptionService();
        
        $requestData = [
            'sessionToken' => 'test_token_123',
            'roundId' => 'round_123',
            'transactionId' => 'bet_duplicate',
            'betAmount' => 100.00,
        ];

        $encryptedData = $service->encrypt($requestData, $this->provider->aes_key);
        $timestamp = time();
        $signature = $service->generateSignature($encryptedData, $this->provider->aes_key, $timestamp);

        // First request
        $response1 = $this->postJson("/api/slots/callback/{$this->provider->code}/bet", [
            'data' => $encryptedData,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ]);

        $response1->assertStatus(200);

        // Second request with same transaction ID
        $response2 = $this->postJson("/api/slots/callback/{$this->provider->code}/bet", [
            'data' => $encryptedData,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ]);

        $response2->assertStatus(200);

        // Should only have 1 transaction
        $this->assertEquals(1, SlotTransaction::where('external_txn_id', 'bet_duplicate')->count());

        // Balance should only be deducted once
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(9900.00, $wallet->real_balance);
    }

    /** @test */
    public function it_rejects_invalid_signature()
    {
        $service = new SlotEncryptionService();
        
        $requestData = [
            'sessionToken' => 'test_token_123',
            'roundId' => 'round_123',
            'transactionId' => 'bet_123',
            'betAmount' => 100.00,
        ];

        $encryptedData = $service->encrypt($requestData, $this->provider->aes_key);

        $response = $this->postJson("/api/slots/callback/{$this->provider->code}/bet", [
            'data' => $encryptedData,
            'signature' => 'invalid_signature',
            'timestamp' => time(),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid signature',
            ]);
    }
}
