<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_verifies_dice_game_result()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'dice',
            'server_seed' => 'test-server-seed-123456789',
            'server_seed_hash' => hash('sha256', 'test-server-seed-123456789'),
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
            'game_data' => [
                'target' => 50.00,
                'prediction' => 'over',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ])
            ->assertJsonStructure([
                'verified',
                'hash',
                'result' => [
                    'game',
                    'result',
                    'prediction',
                    'target',
                    'is_win',
                    'win_chance',
                    'multiplier',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_invalid_server_seed_hash()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'dice',
            'server_seed' => 'test-server-seed',
            'server_seed_hash' => 'wrong-hash',
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'verified' => false,
            ])
            ->assertJsonFragment([
                'error' => 'Server seed hash does not match. Expected: ' . hash('sha256', 'test-server-seed'),
            ]);
    }

    /** @test */
    public function it_verifies_mines_game_result()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'mines',
            'server_seed' => 'test-server-seed',
            'server_seed_hash' => hash('sha256', 'test-server-seed'),
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
            'game_data' => [
                'mine_count' => 3,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ])
            ->assertJsonStructure([
                'result' => [
                    'game',
                    'mine_count',
                    'grid_size',
                    'mine_positions',
                ],
            ]);

        $minePositions = $response->json('result.mine_positions');
        $this->assertCount(3, $minePositions);
    }

    /** @test */
    public function it_verifies_keno_game_result()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'keno',
            'server_seed' => 'test-server-seed',
            'server_seed_hash' => hash('sha256', 'test-server-seed'),
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
            'game_data' => [
                'selected_numbers' => '5,12,23,34,40',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ])
            ->assertJsonStructure([
                'result' => [
                    'game',
                    'drawn_numbers',
                    'selected_numbers',
                    'hits',
                    'matching_numbers',
                ],
            ]);

        $drawnNumbers = $response->json('result.drawn_numbers');
        $this->assertCount(20, $drawnNumbers);
    }

    /** @test */
    public function it_verifies_plinko_game_result()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'plinko',
            'server_seed' => 'test-server-seed',
            'server_seed_hash' => hash('sha256', 'test-server-seed'),
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
            'game_data' => [
                'rows' => 16,
                'risk' => 'medium',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ])
            ->assertJsonStructure([
                'result' => [
                    'game',
                    'rows',
                    'risk',
                    'path',
                    'final_position',
                    'multiplier',
                ],
            ]);

        $path = $response->json('result.path');
        $this->assertCount(16, $path);
    }

    /** @test */
    public function it_verifies_crash_game_result()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'crash',
            'server_seed' => 'test-server-seed',
            'server_seed_hash' => hash('sha256', 'test-server-seed'),
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ])
            ->assertJsonStructure([
                'result' => [
                    'game',
                    'crash_multiplier',
                ],
            ]);

        $crashMultiplier = $response->json('result.crash_multiplier');
        $this->assertGreaterThanOrEqual(1.0, $crashMultiplier);
    }

    /** @test */
    public function it_verifies_hilo_game_result()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'hilo',
            'server_seed' => 'test-server-seed',
            'server_seed_hash' => hash('sha256', 'test-server-seed'),
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ])
            ->assertJsonStructure([
                'result' => [
                    'game',
                    'card_rank',
                    'card_suit',
                    'card_value',
                ],
            ]);

        $cardValue = $response->json('result.card_value');
        $this->assertGreaterThanOrEqual(1, $cardValue);
        $this->assertLessThanOrEqual(13, $cardValue);
    }

    /** @test */
    public function it_verifies_wheel_game_result()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'wheel',
            'server_seed' => 'test-server-seed',
            'server_seed_hash' => hash('sha256', 'test-server-seed'),
            'client_seed' => 'test-client-seed',
            'nonce' => 0,
            'game_data' => [
                'risk' => 'medium',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'verified' => true,
            ])
            ->assertJsonStructure([
                'result' => [
                    'game',
                    'risk',
                    'float_value',
                    'segment_index',
                    'multiplier',
                    'color',
                ],
            ]);
    }

    /** @test */
    public function verification_endpoint_is_public()
    {
        // No authentication required
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'dice',
            'server_seed' => 'test',
            'server_seed_hash' => hash('sha256', 'test'),
            'client_seed' => 'test',
            'nonce' => 0,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'dice',
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['server_seed', 'server_seed_hash', 'client_seed', 'nonce']);
    }

    /** @test */
    public function it_validates_game_type()
    {
        $response = $this->postJson('/api/games/verify', [
            'game_type' => 'invalid-game',
            'server_seed' => 'test',
            'server_seed_hash' => hash('sha256', 'test'),
            'client_seed' => 'test',
            'nonce' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['game_type']);
    }

    /** @test */
    public function it_returns_verification_instructions()
    {
        $response = $this->getJson('/api/games/verify/instructions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'title',
                'overview',
                'steps',
                'tools',
                'support',
            ]);

        $steps = $response->json('steps');
        $this->assertCount(4, $steps);
    }

    /** @test */
    public function verification_result_is_deterministic()
    {
        $requestData = [
            'game_type' => 'dice',
            'server_seed' => 'test-seed-123',
            'server_seed_hash' => hash('sha256', 'test-seed-123'),
            'client_seed' => 'client-seed-456',
            'nonce' => 42,
            'game_data' => [
                'target' => 50.00,
                'prediction' => 'over',
            ],
        ];

        $response1 = $this->postJson('/api/games/verify', $requestData);
        $response2 = $this->postJson('/api/games/verify', $requestData);

        $result1 = $response1->json('result.result');
        $result2 = $response2->json('result.result');

        $this->assertEquals($result1, $result2);
    }
}
