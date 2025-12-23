<?php

namespace App\Services;

use App\Models\User;
use App\Models\VipLevel;
use Illuminate\Support\Facades\DB;

class VipService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Check and upgrade user's VIP tier if eligible
     * Based on total wagered amount
     */
    public function checkForUpgrade(User $user): bool
    {
        // Refresh user data to ensure we have latest values
        $user->refresh();
        
        // Get current VIP level
        $currentLevel = $user->vipLevel;
        
        // Find the highest level user qualifies for
        $newLevel = VipLevel::where('min_wagered_amount', '<=', $user->total_wagered)
            ->where('level', '>', $currentLevel->level)
            ->orderBy('level', 'desc')
            ->first();

        // No higher level qualifies
        if (!$newLevel) {
            return false;
        }

        $this->upgradeVipLevel($user, $newLevel);
        return true;
    }

    /**
     * Check and downgrade user's VIP tier if they no longer meet requirements
     * Based on wagering activity in the last 90 days
     * 
     * @param User $user
     * @param int $inactiveDays Number of days to check for inactivity (default: 90)
     * @return bool Returns true if downgraded, false otherwise
     */
    public function checkForDowngrade(User $user, int $inactiveDays = 90): bool
    {
        // Get current VIP level
        $currentLevel = $user->vipLevel;
        
        // Don't downgrade Bronze (lowest tier)
        if ($currentLevel->level === 1) {
            return false;
        }

        // Calculate wagering in the last N days
        $recentWagered = $user->bets()
            ->where('created_at', '>=', now()->subDays($inactiveDays))
            ->sum('bet_amount');

        // Get minimum required wagering for current level (10% of requirement per 90 days)
        $requiredActivity = $currentLevel->min_wagered_amount * 0.10;

        // User is inactive, find appropriate lower tier
        if ($recentWagered < $requiredActivity) {
            // Find the highest tier they qualify for based on total wagered
            $newLevel = VipLevel::where('min_wagered_amount', '<=', $user->total_wagered)
                ->where('level', '<', $currentLevel->level)
                ->orderBy('level', 'desc')
                ->first();

            // Ensure we don't go below Bronze
            if (!$newLevel) {
                $newLevel = VipLevel::where('level', 1)->first();
            }

            if ($newLevel && $newLevel->level < $currentLevel->level) {
                $this->downgradeVipLevel($user, $newLevel, $recentWagered, $requiredActivity);
                return true;
            }
        }

        return false;
    }

    /**
     * Upgrade user to new VIP level
     */
    protected function upgradeVipLevel(User $user, VipLevel $newLevel): VipLevel
    {
        return DB::transaction(function () use ($user, $newLevel) {
            $oldLevel = $user->vipLevel;

            // Update user's VIP level
            $user->vip_level_id = $newLevel->id;
            $user->save();

            // Send notification
            $this->notificationService->notifyVipUpgrade($user, $oldLevel, $newLevel);

            // Log action
            $this->logAction('vip_upgraded', $user, [
                'old_level' => $oldLevel->name,
                'new_level' => $newLevel->name,
                'total_wagered' => $user->total_wagered,
            ]);

            return $newLevel;
        });
    }

    /**
     * Downgrade user to lower VIP level
     */
    protected function downgradeVipLevel(
        User $user, 
        VipLevel $newLevel, 
        float $recentWagered, 
        float $requiredActivity
    ): VipLevel {
        return DB::transaction(function () use ($user, $newLevel, $recentWagered, $requiredActivity) {
            $oldLevel = $user->vipLevel;

            // Update user's VIP level
            $user->vip_level_id = $newLevel->id;
            $user->save();

            // Send notification
            $this->notificationService->notifyVipDowngrade($user, $oldLevel, $newLevel, $recentWagered, $requiredActivity);

            // Log action
            $this->logAction('vip_downgraded', $user, [
                'old_level' => $oldLevel->name,
                'new_level' => $newLevel->name,
                'recent_wagered' => $recentWagered,
                'required_activity' => $requiredActivity,
                'total_wagered' => $user->total_wagered,
            ]);

            return $newLevel;
        });
    }

    /**
     * Calculate VIP benefits for a user
     */
    public function calculateBenefits(User $user): array
    {
        $vipLevel = $user->vipLevel;

        return [
            'level' => $vipLevel->level,
            'name' => $vipLevel->name,
            'bonus_multiplier' => $vipLevel->bonus_multiplier,
            'wagering_reduction' => $vipLevel->wagering_reduction,
            'cashback_percentage' => $vipLevel->cashback_percentage,
            'withdrawal_limit' => [
                'daily' => $vipLevel->withdrawal_limit_daily,
                'weekly' => $vipLevel->withdrawal_limit_weekly,
                'monthly' => $vipLevel->withdrawal_limit_monthly,
            ],
            'withdrawal_time' => $vipLevel->withdrawal_processing_hours,
            'min_wager_requirement' => $vipLevel->min_wagered_amount ?? 0,
        ];
    }

    /**
     * Get progress towards next VIP level
     */
    public function getProgressToNextLevel(User $user): ?array
    {
        $currentLevel = $user->vipLevel;
        
        $nextLevel = VipLevel::where('level', '>', $currentLevel->level)
            ->orderBy('level', 'asc')
            ->first();

        $currentWagered = $user->total_wagered;

        if (!$nextLevel) {
            return [
                'current_level' => $currentLevel->name,
                'next_level' => null,
                'current_wagered' => $currentWagered,
                'required_wagered' => null,
                'progress_percentage' => 100,
            ];
        }

        $requirement = $nextLevel->min_wagered_amount;
        $percentage = $requirement > 0 ? round(($currentWagered / $requirement) * 100, 2) : 0;

        return [
            'current_level' => $currentLevel->name,
            'next_level' => $nextLevel->name,
            'current_wagered' => $currentWagered,
            'required_wagered' => $requirement,
            'progress_percentage' => min(100, $percentage),
        ];
    }

    /**
     * Get all VIP levels with requirements
     */
    public function getAllLevels(): array
    {
        return VipLevel::orderBy('level', 'asc')->get()->all();
    }

    /**
     * Calculate cashback amount for user's losses
     */
    public function calculateCashback(User $user, float $lossAmount): float
    {
        $vipLevel = $user->vipLevel;
        
        if (!$vipLevel || !$vipLevel->cashback_percentage) {
            return 0;
        }
        
        return $lossAmount * ($vipLevel->cashback_percentage / 100);
    }

    /**
     * Apply VIP wagering multiplier to reduce wagering requirements
     */
    public function applyWageringMultiplier(User $user, float $baseWagering): float
    {
        $vipLevel = $user->vipLevel;
        
        if (!$vipLevel || !$vipLevel->wagering_reduction) {
            return $baseWagering;
        }
        
        // Apply reduction (e.g., 0.9 for 10% reduction)
        $multiplier = 1 - ($vipLevel->wagering_reduction / 100);
        return $baseWagering * $multiplier;
    }

    /**
     * Check and apply weekly/monthly cashback
     * Should be run as a scheduled job
     */
    public function processCashback(string $period = 'weekly'): int
    {
        $processedCount = 0;

        // Get date range based on period
        $startDate = $period === 'weekly' 
            ? now()->subWeek() 
            : now()->subMonth();

        $users = User::where('status', 'active')
            ->where('vip_level_id', '>', 1) // Only VIP users (not Bronze)
            ->get();

        foreach ($users as $user) {
            // Calculate net loss in period
            $netLoss = $user->bets()
                ->where('created_at', '>=', $startDate)
                ->sum('profit');

            // Only apply cashback if user had net loss
            if ($netLoss < 0) {
                $lossAmount = abs($netLoss);
                $cashbackPercentage = $user->vipLevel->cashback_percentage;
                $cashbackAmount = $lossAmount * ($cashbackPercentage / 100);

                if ($cashbackAmount > 0) {
                    // Award cashback bonus
                    app(BonusService::class)->awardCashbackBonus($user, $lossAmount);
                    $processedCount++;
                }
            }
        }

        return $processedCount;
    }

    /**
     * Log VIP action
     */
    protected function logAction(string $action, User $user, array $details = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'actor_type' => 'system',
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => json_encode($details),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
