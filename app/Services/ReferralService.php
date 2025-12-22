<?php

namespace App\Services;

use App\Models\User;
use App\Models\Referral;
use App\Models\Bonus;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService
    ) {}

    /**
     * Process referral when new user makes first deposit
     */
    public function processFirstDepositReferral(User $referee, float $depositAmount): void
    {
        // Check if user was referred
        if (!$referee->referred_by) {
            return;
        }

        // Find referrer by referral code
        $referrer = User::where('referral_code', $referee->referred_by)
            ->where('status', 'active')
            ->first();

        if (!$referrer) {
            return;
        }

        // Check if referral already rewarded
        $referral = Referral::where('referrer_id', $referrer->id)
            ->where('referee_id', $referee->id)
            ->first();

        if (!$referral || $referral->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($referral, $referrer, $referee, $depositAmount) {
            // Calculate referral bonus based on VIP level
            $bonusPercentage = $this->getReferralBonusPercentage($referrer);
            $bonusAmount = $depositAmount * ($bonusPercentage / 100);

            // Apply max limit
            $maxBonus = 1000; // ₱1000 max per referral
            $bonusAmount = min($bonusAmount, $maxBonus);

            // Credit referrer's wallet as bonus
            $this->walletService->creditBonusBalance(
                $referrer,
                $bonusAmount,
                "Referral bonus for {$referee->username}'s first deposit",
                Referral::class,
                $referral->id
            );

            // Create bonus record for wagering tracking
            $wageringRequirement = $referrer->vipLevel->calculateWageringRequirement($bonusAmount, 20);
            
            Bonus::create([
                'user_id' => $referrer->id,
                'type' => 'referral',
                'amount' => $bonusAmount,
                'wagering_requirement' => $wageringRequirement,
                'wagering_progress' => 0,
                'status' => 'active',
                'expires_at' => now()->addDays(30),
            ]);

            // Update referral record
            $referral->update([
                'reward_amount' => $bonusAmount,
                'status' => 'paid',
                'rewarded_at' => now(),
            ]);

            // Update referrer stats
            $referrer->increment('total_referral_earnings', $bonusAmount);

            // Send notification
            $this->notificationService->createNotification(
                $referrer,
                'referral_bonus',
                'Referral Bonus Received!',
                "You earned ₱{$bonusAmount} bonus from {$referee->username}'s first deposit!",
                [
                    'amount' => $bonusAmount,
                    'referee' => $referee->username,
                    'wagering_required' => $wageringRequirement,
                ]
            );
        });
    }

    /**
     * Get referral bonus percentage based on VIP level
     */
    private function getReferralBonusPercentage(User $referrer): float
    {
        return match($referrer->vipLevel->name) {
            'Bronze' => 5.0,   // 5% of first deposit
            'Silver' => 7.5,   // 7.5%
            'Gold' => 10.0,    // 10%
            'Platinum' => 12.5, // 12.5%
            'Diamond' => 15.0,  // 15%
            default => 5.0,
        };
    }

    /**
     * Track referral registration
     */
    public function trackReferral(User $referee, string $referralCode): bool
    {
        $referrer = User::where('referral_code', $referralCode)->first();

        if (!$referrer || $referrer->id === $referee->id) {
            return false;
        }

        // Prevent self-referral abuse (check IP, device)
        if ($this->isSuspiciousReferral($referrer, $referee)) {
            return false;
        }

        DB::transaction(function () use ($referrer, $referee) {
            // Create referral record
            Referral::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $referee->id,
                'status' => 'pending',
            ]);

            // Increment referrer's count
            $referrer->increment('referral_count');
        });

        return true;
    }

    /**
     * Check for suspicious referral patterns
     */
    private function isSuspiciousReferral(User $referrer, User $referee): bool
    {
        // Check if same IP address via last_login_ip (skip if no request IP - e.g. CLI/Tinker)
        $requestIp = request()->ip();
        if ($requestIp && $referrer->last_login_ip && $referrer->last_login_ip === $requestIp) {
            return true; // Same IP - suspicious
        }

        // Check if referee was created very recently (same minute as referrer)
        if ($referrer->created_at->diffInMinutes($referee->created_at) < 1) {
            return true; // Created too close together
        }

        // Check if excessive referrals in short time
        $recentReferrals = Referral::where('referrer_id', $referrer->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentReferrals >= 10) {
            return true; // Too many referrals in 24h
        }

        return false;
    }

    /**
     * Get user's referral statistics
     */
    public function getReferralStats(User $user): array
    {
        $referrals = Referral::where('referrer_id', $user->id)
            ->with('referee:id,username,created_at,total_deposited')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalReferrals = $referrals->count();
        $paidReferrals = $referrals->where('reward_status', 'paid')->count();
        $pendingReferrals = $referrals->where('reward_status', 'pending')->count();
        $totalEarned = $referrals->where('reward_status', 'paid')->sum('reward_amount');

        return [
            'referral_code' => $user->referral_code,
            'referral_link' => config('app.url') . '/register?ref=' . $user->referral_code,
            'total_referrals' => $totalReferrals,
            'pending_referrals' => $pendingReferrals,
            'paid_referrals' => $paidReferrals,
            'total_earned' => (float) $totalEarned,
            'bonus_percentage' => $this->getReferralBonusPercentage($user),
            'referrals' => $referrals->map(function ($referral) {
                return [
                    'username' => $referral->referee->username,
                    'registered_at' => $referral->created_at,
                    'total_deposited' => $referral->referee->total_deposited,
                    'reward_amount' => $referral->reward_amount,
                    'reward_status' => $referral->reward_status,
                    'rewarded_at' => $referral->rewarded_at,
                ];
            }),
        ];
    }

    /**
     * Get referral leaderboard
     */
    public function getLeaderboard(int $limit = 10): array
    {
        return User::where('referral_count', '>', 0)
            ->orderBy('total_referral_earnings', 'desc')
            ->orderBy('referral_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'username' => $user->username,
                    'vip_level' => $user->vipLevel->name,
                    'referral_count' => $user->referral_count,
                    'total_earned' => (float) $user->total_referral_earnings,
                ];
            })
            ->toArray();
    }

    /**
     * Validate referral code
     */
    public function validateReferralCode(string $code): ?User
    {
        return User::where('referral_code', $code)
            ->where('status', 'active')
            ->first();
    }
}
