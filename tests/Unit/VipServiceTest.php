<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\VipService;
use App\Models\User;
use App\Models\VipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VipServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VipService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VipService::class);
        
        // Seed VIP levels
        $this->seed(\Database\Seeders\VipLevelsSeeder::class);
        
        $bronzeLevel = VipLevel::where('name', 'Bronze')->first();
        
        $this->user = User::factory()->create([
            'vip_level_id' => $bronzeLevel->id,
            'total_wagered' => 5000.00,
            'total_deposited' => 2000.00,
        ]);
    }

    /** @test */
    public function it_checks_for_vip_upgrade()
    {
        // User has wagered 5000, should still be Bronze (needs 10k for Silver)
        $upgraded = $this->service->checkForUpgrade($this->user);
        
        $this->assertFalse($upgraded);
        $this->assertEquals('Bronze', $this->user->fresh()->vipLevel->name);
    }

    /** @test */
    public function it_upgrades_user_to_next_vip_level()
    {
        // Set wagered amount to qualify for Silver
        $this->user->update(['total_wagered' => 15000.00]);
        
        $upgraded = $this->service->checkForUpgrade($this->user);
        
        $this->assertTrue($upgraded);
        $this->assertEquals('Silver', $this->user->fresh()->vipLevel->name);
    }

    /** @test */
    public function it_skips_levels_if_user_qualifies_for_higher_tier()
    {
        // Set wagered amount to qualify for Gold
        $this->user->update(['total_wagered' => 60000.00]);
        
        $upgraded = $this->service->checkForUpgrade($this->user);
        
        $this->assertTrue($upgraded);
        $this->assertEquals('Gold', $this->user->fresh()->vipLevel->name);
    }

    /** @test */
    public function it_calculates_vip_benefits()
    {
        $silverLevel = VipLevel::where('name', 'Silver')->first();
        $this->user->update(['vip_level_id' => $silverLevel->id]);
        
        $benefits = $this->service->calculateBenefits($this->user);
        
        $this->assertArrayHasKey('bonus_multiplier', $benefits);
        $this->assertArrayHasKey('wagering_reduction', $benefits);
        $this->assertArrayHasKey('cashback_percentage', $benefits);
        $this->assertArrayHasKey('withdrawal_limit', $benefits);
        $this->assertArrayHasKey('withdrawal_time', $benefits);
        
        $this->assertGreaterThan(1.0, $benefits['bonus_multiplier']);
    }

    /** @test */
    public function it_gets_progress_to_next_level()
    {
        $progress = $this->service->getProgressToNextLevel($this->user);
        
        $this->assertArrayHasKey('current_level', $progress);
        $this->assertArrayHasKey('next_level', $progress);
        $this->assertArrayHasKey('current_wagered', $progress);
        $this->assertArrayHasKey('required_wagered', $progress);
        $this->assertArrayHasKey('progress_percentage', $progress);
        
        $this->assertEquals('Bronze', $progress['current_level']);
        $this->assertEquals('Silver', $progress['next_level']);
        $this->assertEquals(5000.00, $progress['current_wagered']);
        $this->assertGreaterThanOrEqual(0, $progress['progress_percentage']);
        $this->assertLessThanOrEqual(100, $progress['progress_percentage']);
    }

    /** @test */
    public function diamond_users_have_no_next_level()
    {
        $diamondLevel = VipLevel::where('name', 'Diamond')->first();
        $this->user->update([
            'vip_level_id' => $diamondLevel->id,
            'total_wagered' => 500000.00
        ]);
        
        $progress = $this->service->getProgressToNextLevel($this->user);
        
        $this->assertEquals('Diamond', $progress['current_level']);
        $this->assertNull($progress['next_level']);
        $this->assertEquals(100, $progress['progress_percentage']);
    }

    /** @test */
    public function it_calculates_cashback_amount()
    {
        $silverLevel = VipLevel::where('name', 'Silver')->first();
        $this->user->update(['vip_level_id' => $silverLevel->id]);
        
        $lossAmount = 1000.00;
        $cashback = $this->service->calculateCashback($this->user, $lossAmount);
        
        $this->assertGreaterThan(0, $cashback);
        $this->assertLessThanOrEqual($lossAmount * 0.10, $cashback); // Max 10% cashback
    }

    /** @test */
    public function higher_vip_levels_get_more_cashback()
    {
        $bronzeLevel = VipLevel::where('name', 'Bronze')->first();
        $platinumLevel = VipLevel::where('name', 'Platinum')->first();
        
        $bronzeUser = User::factory()->create(['vip_level_id' => $bronzeLevel->id]);
        $platinumUser = User::factory()->create(['vip_level_id' => $platinumLevel->id]);
        
        $lossAmount = 1000.00;
        $bronzeCashback = $this->service->calculateCashback($bronzeUser, $lossAmount);
        $platinumCashback = $this->service->calculateCashback($platinumUser, $lossAmount);
        
        $this->assertGreaterThan($bronzeCashback, $platinumCashback);
    }

    /** @test */
    public function it_checks_for_downgrade()
    {
        $silverLevel = VipLevel::where('name', 'Silver')->first();
        $this->user->update([
            'vip_level_id' => $silverLevel->id,
            'total_wagered' => 15000.00
        ]);
        
        // User has been inactive (no recent bets in last 90 days)
        $shouldDowngrade = $this->service->checkForDowngrade($this->user, 90);
        
        // Since user has no bets, they should be downgraded
        $this->assertTrue($shouldDowngrade !== false);
    }

    /** @test */
    public function it_never_downgrades_below_bronze()
    {
        $bronzeLevel = VipLevel::where('name', 'Bronze')->first();
        $this->user->update([
            'vip_level_id' => $bronzeLevel->id,
            'total_wagered' => 5000.00
        ]);
        
        $result = $this->service->checkForDowngrade($this->user, 90);
        
        $this->assertFalse($result);
        $this->assertEquals('Bronze', $this->user->fresh()->vipLevel->name);
    }

    /** @test */
    public function it_applies_vip_wagering_multiplier()
    {
        $goldLevel = VipLevel::where('name', 'Gold')->first();
        $this->user->update(['vip_level_id' => $goldLevel->id]);
        
        $baseWagering = 30.0; // 30x
        $adjustedWagering = $this->service->applyWageringMultiplier($this->user, $baseWagering);
        
        // Gold level should reduce wagering requirements
        $this->assertLessThan($baseWagering, $adjustedWagering);
    }

    /** @test */
    public function it_gets_all_vip_levels()
    {
        $levels = $this->service->getAllLevels();
        
        $this->assertCount(5, $levels);
        $this->assertEquals('Bronze', $levels[0]->name);
        $this->assertEquals('Diamond', $levels[4]->name);
    }

    /** @test */
    public function vip_levels_are_ordered_by_level()
    {
        $levels = $this->service->getAllLevels();
        
        $previousLevel = 0;
        foreach ($levels as $vipLevel) {
            $this->assertGreaterThan($previousLevel, $vipLevel->level);
            $previousLevel = $vipLevel->level;
        }
    }
}
