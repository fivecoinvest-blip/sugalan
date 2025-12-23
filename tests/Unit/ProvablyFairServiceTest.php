<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProvablyFairService;
use App\Models\Seed;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProvablyFairServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProvablyFairService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProvablyFairService();
        
        // Create test user for seed-related tests
        $this->user = User::factory()->create();
        Wallet::factory()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function it_generates_deterministic_result_hash()
    {
        $serverSeed = 'test-server-seed-123';
        $clientSeed = 'test-client-seed';
        $nonce = 0;

        $hash1 = $this->service->generateResult($serverSeed, $clientSeed, $nonce);
        $hash2 = $this->service->generateResult($serverSeed, $clientSeed, $nonce);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA256 produces 64 hex chars
    }

    /** @test */
    public function it_generates_different_hashes_for_different_nonces()
    {
        $serverSeed = 'test-server-seed-123';
        $clientSeed = 'test-client-seed';

        $hash1 = $this->service->generateResult($serverSeed, $clientSeed, 0);
        $hash2 = $this->service->generateResult($serverSeed, $clientSeed, 1);

        $this->assertNotEquals($hash1, $hash2);
    }

    /** @test */
    public function it_converts_hash_to_dice_result_in_valid_range()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        $result = $this->service->hashToDiceResult($hash);

        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThanOrEqual(100, $result);
    }

    /** @test */
    public function it_converts_hash_to_mines_positions()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        $positions = $this->service->hashToMinesPositions($hash, 5, 3); // 5x5 grid = 25 cells

        $this->assertCount(3, $positions);
        $this->assertCount(3, array_unique($positions)); // No duplicates
        
        foreach ($positions as $position) {
            $this->assertGreaterThanOrEqual(0, $position);
            $this->assertLessThan(25, $position); // 5x5 = 25 cells
        }
    }

    /** @test */
    public function it_converts_hash_to_keno_numbers()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        $numbers = $this->service->hashToKenoNumbers($hash, 20, 40);

        $this->assertCount(20, $numbers);
        $this->assertCount(20, array_unique($numbers)); // No duplicates
        
        foreach ($numbers as $number) {
            $this->assertGreaterThanOrEqual(1, $number);
            $this->assertLessThanOrEqual(40, $number);
        }
    }

    /** @test */
    public function it_converts_hash_to_crash_multiplier()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        $multiplier = $this->service->hashToCrashMultiplier($hash);

        $this->assertIsFloat($multiplier);
        $this->assertGreaterThanOrEqual(1.0, $multiplier);
        $this->assertLessThanOrEqual(1000000, $multiplier);
    }

    /** @test */
    public function it_converts_hash_to_card()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        $card = $this->service->hashToCard($hash);

        $this->assertArrayHasKey('value', $card);
        $this->assertArrayHasKey('rank', $card);
        $this->assertArrayHasKey('suit', $card);
        
        $this->assertGreaterThanOrEqual(1, $card['value']);
        $this->assertLessThanOrEqual(13, $card['value']);
        
        $this->assertContains($card['suit'], ['♠', '♥', '♦', '♣']);
    }

    /** @test */
    public function it_converts_hash_to_plinko_path()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        $path = $this->service->hashToPlinkoPath($hash, 16);

        $this->assertCount(16, $path);
        
        foreach ($path as $direction) {
            $this->assertContains($direction, [0, 1]);
        }
    }

    /** @test */
    public function it_gets_plinko_multipliers_for_risk_levels()
    {
        $lowMultipliers = $this->service->getPlinkoMultipliers('low', 16);
        $mediumMultipliers = $this->service->getPlinkoMultipliers('medium', 16);
        $highMultipliers = $this->service->getPlinkoMultipliers('high', 16);

        $this->assertNotEmpty($lowMultipliers);
        $this->assertNotEmpty($mediumMultipliers);
        $this->assertNotEmpty($highMultipliers);
        
        // High risk should have higher max multipliers
        $this->assertGreaterThan(max($lowMultipliers), max($highMultipliers));
    }

    /** @test */
    public function it_gets_wheel_config_for_risk_levels()
    {
        $lowConfig = $this->service->getWheelConfig('low');
        $mediumConfig = $this->service->getWheelConfig('medium');
        $highConfig = $this->service->getWheelConfig('high');

        $this->assertNotEmpty($lowConfig);
        $this->assertNotEmpty($mediumConfig);
        $this->assertNotEmpty($highConfig);
        
        foreach ($lowConfig as $segment) {
            $this->assertArrayHasKey('multiplier', $segment);
            $this->assertArrayHasKey('weight', $segment);
            $this->assertArrayHasKey('color', $segment);
        }
    }

    /** @test */
    public function it_selects_wheel_segment_based_on_float()
    {
        $config = $this->service->getWheelConfig('medium');
        
        // Test with 0.0 (should select first segment)
        $segment1 = $this->service->selectWheelSegment($config, 0.0);
        $this->assertArrayHasKey('multiplier', $segment1);
        $this->assertArrayHasKey('index', $segment1);
        
        // Test with 1.0 (should select last segment)
        $segment2 = $this->service->selectWheelSegment($config, 1.0);
        $this->assertArrayHasKey('multiplier', $segment2);
    }

    /** @test */
    public function it_verifies_result_correctly()
    {
        $serverSeed = 'test-server-seed';
        $clientSeed = 'test-client-seed';
        $nonce = 42;

        $expectedHash = $this->service->generateResult($serverSeed, $clientSeed, $nonce);
        
        $isValid = $this->service->verifyResult($serverSeed, $clientSeed, $nonce, $expectedHash);
        
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_fails_verification_with_wrong_hash()
    {
        $serverSeed = 'test-server-seed';
        $clientSeed = 'test-client-seed';
        $nonce = 42;

        $wrongHash = 'wrong-hash-123';
        
        $isValid = $this->service->verifyResult($serverSeed, $clientSeed, $nonce, $wrongHash);
        
        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_creates_new_seed_for_user()
    {
        $seed = $this->service->createNewSeed($this->user->id);

        $this->assertInstanceOf(Seed::class, $seed);
        $this->assertEquals($this->user->id, $seed->user_id);
        $this->assertEquals(64, strlen($seed->server_seed));
        $this->assertNotEmpty($seed->client_seed);
        $this->assertEquals(0, $seed->nonce);
        $this->assertTrue($seed->is_active);
    }

    /** @test */
    public function it_gets_or_creates_active_seed()
    {
        // First call should create new seed
        $seed1 = $this->service->getActiveSeed($this->user->id);
        $this->assertInstanceOf(Seed::class, $seed1);
        
        // Second call should return same seed
        $seed2 = $this->service->getActiveSeed($this->user->id);
        $this->assertEquals($seed1->id, $seed2->id);
    }

    /** @test */
    public function hash_to_number_returns_float_between_0_and_1()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        $number = $this->service->hashToNumber($hash);

        $this->assertIsFloat($number);
        $this->assertGreaterThanOrEqual(0, $number);
        $this->assertLessThanOrEqual(1, $number);
    }

    /** @test */
    public function hash_to_float_is_alias_of_hash_to_number()
    {
        $hash = $this->service->generateResult('server', 'client', 0);
        
        $number = $this->service->hashToNumber($hash);
        $float = $this->service->hashToFloat($hash);

        $this->assertEquals($number, $float);
    }
}
