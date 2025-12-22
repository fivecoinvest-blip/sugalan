<?php

namespace App\Services;

use App\Models\User;
use App\Models\DailyReward;
use App\Models\Bonus;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class DailyRewardService
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService
    ) {}

    /**
     * Claim daily reward
     */
    public function claimDailyReward(User $user): array
    {
        $today = now()->toDateString();

        // Check if already claimed today
        $existingClaim = DailyReward::where('user_id', $user->id)
            ->where('check_in_date', $today)
            ->first();

        if ($existingClaim) {
            throw new \Exception('Daily reward already claimed today');
        }

        return DB::transaction(function () use ($user, $today) {
            // Get current streak
            $streak = $this->calculateStreak($user);
            $newStreak = $streak + 1;

            // Calculate reward amount based on streak and VIP level
            $rewardAmount = DailyReward::calculateRewardAmount($newStreak, $user->vip_level_id);

            // Credit bonus balance
            $this->walletService->creditBonusBalance(
                $user,
                $rewardAmount,
                'daily_reward',
                "Day {$newStreak} check-in reward"
            );

            // Create bonus record with wagering
            $wageringMultiplier = 15; // Lower wagering for daily rewards
            $wageringRequirement = $user->vipLevel->calculateWageringRequirement($rewardAmount, $wageringMultiplier);

            $bonus = Bonus::create([
                'user_id' => $user->id,
                'type' => 'daily_reward',
                'amount' => $rewardAmount,
                'wagering_requirement' => $wageringRequirement,
                'wagering_progress' => 0,
                'status' => 'active',
                'expires_at' => now()->addDays(3),
            ]);

            // Record daily reward
            $dailyReward = DailyReward::create([
                'user_id' => $user->id,
                'check_in_date' => $today,
                'streak_days' => $newStreak,
                'reward_amount' => $rewardAmount,
                'bonus_id' => $bonus->id,
                'status' => 'claimed',
            ]);

            // Send notification
            $streakBonus = $newStreak % 7 === 0 ? ' 🎉 Week Complete!' : '';
            $this->notificationService->sendNotification(
                $user,
                'daily_reward_claimed',
                "Daily Check-in Reward{$streakBonus}",
                "You've earned ₱{$rewardAmount}! {$newStreak} day streak!",
                ['reward_amount' => $rewardAmount, 'streak' => $newStreak]
            );

            return [
                'daily_reward' => $dailyReward,
                'bonus' => $bonus,
                'streak' => $newStreak,
                'reward_amount' => $rewardAmount,
                'next_reward' => DailyReward::calculateRewardAmount($newStreak + 1, $user->vip_level_id),
                'message' => "Daily reward claimed! {$newStreak} day streak",
            ];
        });
    }

    /**
     * Calculate current streak
     */
    public function calculateStreak(User $user): int
    {
        $yesterday = now()->subDay()->toDateString();

        // Get most recent check-in
        $lastReward = DailyReward::where('user_id', $user->id)
            ->orderBy('check_in_date', 'desc')
            ->first();

        if (!$lastReward) {
            return 0; // No previous check-ins
        }

        // If last check-in was yesterday, continue streak
        if ($lastReward->check_in_date->toDateString() === $yesterday) {
            return $lastReward->streak_days;
        }

        // Streak broken
        return 0;
    }

    /**
     * Get user's daily reward status
     */
    public function getDailyRewardStatus(User $user): array
    {
        $today = now()->toDateString();
        $currentStreak = $this->calculateStreak($user);

        $todaysClaim = DailyReward::where('user_id', $user->id)
            ->where('check_in_date', $today)
            ->first();

        $canClaim = !$todaysClaim;
        $nextStreak = $canClaim ? $currentStreak + 1 : $currentStreak;

        // Get last 7 days check-in history
        $recentCheckIns = DailyReward::where('user_id', $user->id)
            ->where('check_in_date', '>=', now()->subDays(6)->toDateString())
            ->orderBy('check_in_date', 'desc')
            ->get();

        return [
            'can_claim' => $canClaim,
            'current_streak' => $currentStreak,
            'next_streak' => $nextStreak,
            'todays_claim' => $todaysClaim,
            'reward_amount' => $canClaim ? DailyReward::calculateRewardAmount($nextStreak, $user->vip_level_id) : 0,
            'next_reward' => DailyReward::calculateRewardAmount($nextStreak + 1, $user->vip_level_id),
            'recent_check_ins' => $recentCheckIns,
            'weekly_progress' => ($currentStreak % 7) + ($canClaim ? 1 : 0),
        ];
    }

    /**
     * Get user's daily reward history
     */
    public function getUserRewardHistory(User $user, int $perPage = 30)
    {
        return DailyReward::where('user_id', $user->id)
            ->orderBy('check_in_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get daily reward statistics
     */
    public function getStatistics(): array
    {
        $today = now()->toDateString();

        $todaysClaims = DailyReward::where('check_in_date', $today)->count();
        $todaysRewards = DailyReward::where('check_in_date', $today)->sum('reward_amount');

        // Get top streaks
        $topStreaks = DailyReward::select('user_id', DB::raw('MAX(streak_days) as max_streak'))
            ->groupBy('user_id')
            ->orderBy('max_streak', 'desc')
            ->limit(10)
            ->with('user')
            ->get();

        // Total statistics
        $totalClaims = DailyReward::count();
        $totalRewarded = DailyReward::sum('reward_amount');

        return [
            'today' => [
                'claims' => $todaysClaims,
                'total_rewarded' => $todaysRewards,
            ],
            'all_time' => [
                'total_claims' => $totalClaims,
                'total_rewarded' => $totalRewarded,
                'average_reward' => $totalClaims > 0 ? $totalRewarded / $totalClaims : 0,
            ],
            'top_streaks' => $topStreaks->map(function ($item) {
                return [
                    'user' => $item->user->phone_number ?? "User #{$item->user_id}",
                    'max_streak' => $item->max_streak,
                ];
            }),
        ];
    }
}
