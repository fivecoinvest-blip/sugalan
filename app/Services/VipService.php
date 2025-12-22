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
    public function checkForUpgrade(User $user): ?VipLevel
    {
        // Get current VIP level
        $currentLevel = $user->vipLevel;
        
        // Get next VIP level
        $nextLevel = VipLevel::where('level', '>', $currentLevel->level)
            ->orderBy('level', 'asc')
            ->first();

        // Already at max level
        if (!$nextLevel) {
            return null;
        }

        // Check if user meets the requirement
        if ($user->total_wagered >= $nextLevel->min_wager_requirement) {
            return $this->upgradeVipLevel($user, $nextLevel);
        }

        return null;
    }

    /**
     * Check and downgrade user's VIP tier if they no longer meet requirements
     * Based on wagering activity in the last 90 days
     * 
     * @param User $user
     * @param int $inactiveDays Number of days to check for inactivity (default: 90)
     * @return VipLevel|null Returns new level if downgraded, null otherwise
     */
    public function checkForDowngrade(User $user, int $inactiveDays = 90): ?VipLevel
    {
        // Get current VIP level
        $currentLevel = $user->vipLevel;
        
        // Don't downgrade Bronze (lowest tier)
        if ($currentLevel->level === 1) {
            return null;
        }

        // Calculate wagering in the last N days
        $recentWagered = $user->bets()
            ->where('created_at', '>=', now()->subDays($inactiveDays))
            ->sum('bet_amount');

        // Get minimum required wagering for current level (10% of requirement per 90 days)
        $requiredActivity = $currentLevel->min_wager_requirement * 0.10;

        // User is inactive, find appropriate lower tier
        if ($recentWagered < $requiredActivity) {
            // Find the highest tier they qualify for based on total wagered
            $newLevel = VipLevel::where('min_wager_requirement', '<=', $user->total_wagered)
                ->where('level', '<', $currentLevel->level)
                ->orderBy('level', 'desc')
                ->first();

            // Ensure we don't go below Bronze
            if (!$newLevel) {
                $newLevel = VipLevel::where('level', 1)->first();
            }

            if ($newLevel && $newLevel->level < $currentLevel->level) {
                return $this->downgradeVipLevel($user, $newLevel, $recentWagered, $requiredActivity);
            }
        }

        return null;
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
            'daily_withdraw_limit' => $vipLevel->daily_withdraw_limit,
            'weekly_withdraw_limit' => $vipLevel->weekly_withdraw_limit,
            'monthly_withdraw_limit' => $vipLevel->monthly_withdraw_limit,
            'cashback_percentage' => $vipLevel->cashback_percentage,
            'rakeback_percentage' => $vipLevel->rakeback_percentage,
            'wagering_requirement_multiplier' => $vipLevel->wagering_requirement_multiplier,
            'min_wager_requirement' => $vipLevel->min_wager_requirement,
            'progress_to_next' => $this->getProgressToNextLevel($user),
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

        if (!$nextLevel) {
            return null;
        }

        $currentWagered = $user->total_wagered;
        $requirement = $nextLevel->min_wager_requirement;
        $remaining = max(0, $requirement - $currentWagered);
        $percentage = $requirement > 0 ? round(($currentWagered / $requirement) * 100, 2) : 100;

        return [
            'next_level' => $nextLevel->name,
            'current_wagered' => $currentWagered,
            'requirement' => $requirement,
            'remaining' => $remaining,
            'percentage' => min(100, $percentage),
        ];
    }

    /**
     * Get all VIP levels with requirements
     */
    public function getAllLevels(): array
    {
        return VipLevel::orderBy('level', 'asc')
            ->get()
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'level' => $level->level,
                    'name' => $level->name,
                    'min_wager_requirement' => $level->min_wager_requirement,
                    'daily_withdraw_limit' => $level->daily_withdraw_limit,
                    'cashback_percentage' => $level->cashback_percentage,
                    'rakeback_percentage' => $level->rakeback_percentage,
                    'wagering_requirement_multiplier' => $level->wagering_requirement_multiplier,
                ];
            })
            ->toArray();
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
