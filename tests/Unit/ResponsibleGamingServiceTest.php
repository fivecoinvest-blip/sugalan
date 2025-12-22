<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\ResponsibleGaming;
use App\Services\ResponsibleGamingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResponsibleGamingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ResponsibleGamingService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(ResponsibleGamingService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_creates_deposit_limit()
    {
        $result = $this->service->setDepositLimit($this->user->id, 'daily', 5000);

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertEquals(5000, $settings->deposit_limit_daily);
    }

    /** @test */
    public function it_creates_wager_limit()
    {
        $result = $this->service->setWagerLimit($this->user->id, 'weekly', 10000);

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertEquals(10000, $settings->wager_limit_weekly);
    }

    /** @test */
    public function it_creates_loss_limit()
    {
        $result = $this->service->setLossLimit($this->user->id, 'monthly', 20000);

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertEquals(20000, $settings->loss_limit_monthly);
    }

    /** @test */
    public function it_checks_deposit_limit_enforcement()
    {
        $this->service->setDepositLimit($this->user->id, 'daily', 5000);
        
        // Should allow deposit within limit
        $canDeposit = $this->service->checkDepositLimit($this->user->id, 3000);
        $this->assertTrue($canDeposit);
        
        // Should block deposit exceeding limit
        $canDeposit = $this->service->checkDepositLimit($this->user->id, 6000);
        $this->assertFalse($canDeposit);
    }

    /** @test */
    public function it_checks_wager_limit_enforcement()
    {
        $this->service->setWagerLimit($this->user->id, 'daily', 10000);
        
        $canWager = $this->service->checkWagerLimit($this->user->id, 8000);
        $this->assertTrue($canWager);
        
        $canWager = $this->service->checkWagerLimit($this->user->id, 12000);
        $this->assertFalse($canWager);
    }

    /** @test */
    public function it_checks_loss_limit_enforcement()
    {
        $this->service->setLossLimit($this->user->id, 'daily', 2000);
        
        $canPlay = $this->service->checkLossLimit($this->user->id, 1500);
        $this->assertTrue($canPlay);
        
        $canPlay = $this->service->checkLossLimit($this->user->id, 2500);
        $this->assertFalse($canPlay);
    }

    /** @test */
    public function it_creates_self_exclusion()
    {
        $result = $this->service->setSelfExclusion($this->user->id, 24);

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertEquals('active', $settings->self_exclusion_status);
        $this->assertNotNull($settings->self_exclusion_until);
    }

    /** @test */
    public function it_checks_self_exclusion_status()
    {
        $this->service->setSelfExclusion($this->user->id, 24);
        
        $isExcluded = $this->service->isUserSelfExcluded($this->user->id);
        $this->assertTrue($isExcluded);
    }

    /** @test */
    public function it_allows_play_after_exclusion_expires()
    {
        ResponsibleGaming::create([
            'user_id' => $this->user->id,
            'self_exclusion_status' => 'active',
            'self_exclusion_until' => Carbon::now()->subHour(), // Past
        ]);
        
        $isExcluded = $this->service->isUserSelfExcluded($this->user->id);
        $this->assertFalse($isExcluded);
    }

    /** @test */
    public function it_creates_permanent_self_exclusion()
    {
        $result = $this->service->setSelfExclusion($this->user->id, 0); // 0 = permanent

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertEquals('permanent', $settings->self_exclusion_status);
    }

    /** @test */
    public function it_sets_session_limit()
    {
        $result = $this->service->setSessionLimit($this->user->id, 60);

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertEquals(60, $settings->session_limit_minutes);
    }

    /** @test */
    public function it_starts_gaming_session()
    {
        $result = $this->service->startSession($this->user->id);

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertNotNull($settings->current_session_start);
    }

    /** @test */
    public function it_checks_session_timeout()
    {
        $this->service->setSessionLimit($this->user->id, 60);
        
        ResponsibleGaming::where('user_id', $this->user->id)->update([
            'current_session_start' => Carbon::now()->subMinutes(65),
        ]);
        
        $hasExpired = $this->service->hasSessionExpired($this->user->id);
        $this->assertTrue($hasExpired);
    }

    /** @test */
    public function it_sets_reality_check_interval()
    {
        $result = $this->service->setRealityCheck($this->user->id, 30);

        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertEquals(30, $settings->reality_check_interval);
    }

    /** @test */
    public function it_checks_if_user_can_play()
    {
        // User with no restrictions should be able to play
        $canPlay = $this->service->canUserPlay($this->user->id);
        $this->assertTrue($canPlay);
        
        // User with active self-exclusion cannot play
        $this->service->setSelfExclusion($this->user->id, 24);
        $canPlay = $this->service->canUserPlay($this->user->id);
        $this->assertFalse($canPlay);
    }

    /** @test */
    public function it_gets_user_statistics()
    {
        $this->service->setDepositLimit($this->user->id, 'daily', 5000);
        $this->service->setWagerLimit($this->user->id, 'daily', 10000);
        
        $stats = $this->service->getStatistics($this->user->id);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('deposit_limits', $stats);
        $this->assertArrayHasKey('wager_limits', $stats);
    }

    /** @test */
    public function it_removes_limit()
    {
        $this->service->setDepositLimit($this->user->id, 'daily', 5000);
        
        $result = $this->service->removeLimit($this->user->id, 'deposit', 'daily');
        $this->assertTrue($result);
        
        $settings = ResponsibleGaming::where('user_id', $this->user->id)->first();
        $this->assertNull($settings->deposit_limit_daily);
    }

    /** @test */
    public function it_validates_limit_amounts()
    {
        // Minimum limit
        $result = $this->service->setDepositLimit($this->user->id, 'daily', 50);
        $this->assertFalse($result); // Below minimum
        
        // Valid limit
        $result = $this->service->setDepositLimit($this->user->id, 'daily', 1000);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_enforces_cooldown_period_for_limit_increases()
    {
        $this->service->setDepositLimit($this->user->id, 'daily', 5000);
        
        // Immediate increase should be blocked
        $result = $this->service->setDepositLimit($this->user->id, 'daily', 10000);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_allows_immediate_limit_decreases()
    {
        $this->service->setDepositLimit($this->user->id, 'daily', 5000);
        
        // Decrease should work immediately
        $result = $this->service->setDepositLimit($this->user->id, 'daily', 3000);
        $this->assertTrue($result);
    }
}
