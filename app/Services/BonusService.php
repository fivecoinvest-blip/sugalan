<?php

namespace App\Services;

use App\Models\User;
use App\Models\Bonus;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class BonusService
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService
    ) {}

    /**
     * Award sign-up bonus to new user
     */
    public function awardSignUpBonus(User $user): ?Bonus
    {
        // Guest users don't get sign-up bonus
        if ($user->auth_method === 'guest') {
            return null;
        }

        // Check if user already received sign-up bonus
        $existingBonus = Bonus::where('user_id', $user->id)
            ->where('type', 'signup')
            ->exists();

        if ($existingBonus) {
            return null;
        }

        return DB::transaction(function () use ($user) {
            $bonusAmount = 50; // ₱50 sign-up bonus
            $wageringMultiplier = 30; // 30x wagering
            $wageringRequirement = $user->vipLevel->calculateWageringRequirement($bonusAmount, $wageringMultiplier);

            // Credit bonus balance
            $this->walletService->creditBonusBalance(
                $user,
                $bonusAmount,
                'signup_bonus',
                'Welcome bonus for new registration'
            );

            // Create bonus record
            $bonus = Bonus::create([
                'user_id' => $user->id,
                'type' => 'signup',
                'amount' => $bonusAmount,
                'wagering_requirement' => $wageringRequirement,
                'wagering_progress' => 0,
                'status' => 'active',
                'expires_at' => now()->addDays(7),
            ]);

            // Send notification
            $this->notificationService->notifyBonusReceived($user, $bonusAmount, 'Welcome');

            return $bonus;
        });
    }

    /**
     * Award reload bonus on deposit
     */
    public function awardReloadBonus(User $user, float $depositAmount): ?Bonus
    {
        // Minimum deposit for reload bonus
        if ($depositAmount < 500) {
            return null;
        }

        // Check if user has active reload bonus
        $hasActiveReload = Bonus::where('user_id', $user->id)
            ->where('type', 'reload')
            ->where('status', 'active')
            ->exists();

        if ($hasActiveReload) {
            return null;
        }

        return DB::transaction(function () use ($user, $depositAmount) {
            // Reload bonus: 10% of deposit, max ₱500
            $bonusPercentage = 10 + ($user->vipLevel->bonus_percentage - 100); // VIP boost
            $bonusAmount = min($depositAmount * ($bonusPercentage / 100), 500);

            $wageringMultiplier = 25;
            $wageringRequirement = $user->vipLevel->calculateWageringRequirement($bonusAmount, $wageringMultiplier);

            // Credit bonus balance
            $this->walletService->creditBonusBalance(
                $user,
                $bonusAmount,
                'reload_bonus',
                "Reload bonus on ₱{$depositAmount} deposit"
            );

            // Create bonus record
            $bonus = Bonus::create([
                'user_id' => $user->id,
                'type' => 'reload',
                'amount' => $bonusAmount,
                'wagering_requirement' => $wageringRequirement,
                'wagering_progress' => 0,
                'status' => 'active',
                'expires_at' => now()->addDays(14),
            ]);

            // Send notification
            $this->notificationService->notifyBonusReceived($user, $bonusAmount, 'Reload');

            return $bonus;
        });
    }

    /**
     * Award cashback bonus
     */
    public function awardCashbackBonus(User $user, float $lossAmount): ?Bonus
    {
        // Minimum loss for cashback
        if ($lossAmount < 1000) {
            return null;
        }

        return DB::transaction(function () use ($user, $lossAmount) {
            // Cashback based on VIP level
            $cashbackPercentage = $user->vipLevel->cashback_percentage;
            $bonusAmount = $lossAmount * ($cashbackPercentage / 100);
            $maxCashback = 2000; // ₱2000 max
            $bonusAmount = min($bonusAmount, $maxCashback);

            $wageringMultiplier = 10; // Lower wagering for cashback
            $wageringRequirement = $user->vipLevel->calculateWageringRequirement($bonusAmount, $wageringMultiplier);

            // Credit bonus balance
            $this->walletService->creditBonusBalance(
                $user,
                $bonusAmount,
                'cashback',
                "Cashback on ₱{$lossAmount} losses"
            );

            // Create bonus record
            $bonus = Bonus::create([
                'user_id' => $user->id,
                'type' => 'cashback',
                'amount' => $bonusAmount,
                'wagering_requirement' => $wageringRequirement,
                'wagering_progress' => 0,
                'status' => 'active',
                'expires_at' => now()->addDays(7),
            ]);

            // Send notification
            $this->notificationService->notifyBonusReceived($user, $bonusAmount, 'Cashback');

            return $bonus;
        });
    }

    /**
     * Award promotional bonus (manual by admin)
     */
    public function awardPromotionalBonus(
        User $user,
        float $amount,
        int $wageringMultiplier = 20,
        int $expiryDays = 30,
        ?string $description = null
    ): Bonus {
        return DB::transaction(function () use ($user, $amount, $wageringMultiplier, $expiryDays, $description) {
            $wageringRequirement = $user->vipLevel->calculateWageringRequirement($amount, $wageringMultiplier);

            // Credit bonus balance
            $this->walletService->creditBonusBalance(
                $user,
                $amount,
                'promotional',
                $description ?? 'Promotional bonus'
            );

            // Create bonus record
            $bonus = Bonus::create([
                'user_id' => $user->id,
                'type' => 'promotional',
                'amount' => $amount,
                'wagering_requirement' => $wageringRequirement,
                'wagering_progress' => 0,
                'status' => 'active',
                'expires_at' => now()->addDays($expiryDays),
            ]);

            // Send notification
            $this->notificationService->notifyBonusReceived($user, $amount, 'Promotional');

            return $bonus;
        });
    }

    /**
     * Update wagering progress on bet
     */
    public function updateWageringProgress(User $user, float $betAmount): void
    {
        $activeBonus = Bonus::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$activeBonus) {
            return;
        }

        DB::transaction(function () use ($activeBonus, $betAmount) {
            // Update wagering progress
            $activeBonus->increment('wagering_progress', $betAmount);

            // Check if wagering requirement met
            if ($activeBonus->wagering_progress >= $activeBonus->wagering_requirement) {
                $activeBonus->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Notify user that bonus wagering is complete
                $this->notificationService->createNotification(
                    $activeBonus->user,
                    'bonus_completed',
                    'Bonus Wagering Complete!',
                    "You've completed the wagering requirement for your {$activeBonus->type} bonus. You can now withdraw!",
                    ['bonus_amount' => $activeBonus->amount]
                );
            }
        });
    }

    /**
     * Cancel/forfeit bonus
     */
    public function cancelBonus(Bonus $bonus): void
    {
        if ($bonus->status !== 'active') {
            throw new \Exception('Only active bonuses can be cancelled');
        }

        DB::transaction(function () use ($bonus) {
            // Remove bonus balance
            $wallet = $bonus->user->wallet;
            $bonusBalance = $wallet->bonus_balance;

            if ($bonusBalance > 0) {
                $this->walletService->debitBonusBalance(
                    $bonus->user,
                    min($bonusBalance, $bonus->amount),
                    'bonus_forfeited',
                    "Forfeited {$bonus->type} bonus"
                );
            }

            // Mark bonus as forfeited
            $bonus->update([
                'status' => 'forfeited',
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Expire old bonuses
     */
    public function expireOldBonuses(): int
    {
        $expiredBonuses = Bonus::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredBonuses as $bonus) {
            try {
                $this->cancelBonus($bonus);
                $bonus->update(['status' => 'expired']);
                
                // Notify user
                $this->notificationService->createNotification(
                    $bonus->user,
                    'bonus_expired',
                    'Bonus Expired',
                    "Your {$bonus->type} bonus has expired. Unused bonus balance has been removed.",
                    ['bonus_amount' => $bonus->amount]
                );
                
                $count++;
            } catch (\Exception $e) {
                // Log error but continue
                \Log::error("Failed to expire bonus {$bonus->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Get user's active bonuses
     */
    public function getActiveBonuses(User $user)
    {
        return Bonus::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($bonus) {
                return [
                    'id' => $bonus->id,
                    'type' => $bonus->type,
                    'amount' => $bonus->amount,
                    'wagering_requirement' => $bonus->wagering_requirement,
                    'wagering_progress' => $bonus->wagering_progress,
                    'wagering_percentage' => $bonus->getWageringPercentage(),
                    'remaining_wagering' => $bonus->getRemainingWagering(),
                    'expires_at' => $bonus->expires_at,
                    'created_at' => $bonus->created_at,
                ];
            });
    }

    /**
     * Get bonus history
     */
    public function getBonusHistory(User $user, int $perPage = 20)
    {
        return Bonus::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
