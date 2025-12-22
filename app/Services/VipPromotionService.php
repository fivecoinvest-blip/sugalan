<?php

namespace App\Services;

use App\Models\User;
use App\Models\VipPromotion;
use App\Models\Bonus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VipPromotionService
{
    public function __construct(
        private BonusService $bonusService,
        private NotificationService $notificationService
    ) {}

    /**
     * Get all available promotions for a user based on their VIP level
     */
    public function getAvailablePromotions(User $user)
    {
        return VipPromotion::active()
            ->forVipLevel($user->vipLevel->level)
            ->get()
            ->filter(function ($promotion) use ($user) {
                return $promotion->isEligibleFor($user);
            });
    }

    /**
     * Get all active promotions (admin view)
     */
    public function getAllPromotions(?string $status = null)
    {
        $query = VipPromotion::query()->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Create a new VIP promotion
     */
    public function createPromotion(array $data): VipPromotion
    {
        return VipPromotion::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'type' => $data['type'],
            'min_vip_level' => $data['min_vip_level'],
            'max_vip_level' => $data['max_vip_level'] ?? null,
            'value' => $data['value'] ?? 0,
            'percentage' => $data['percentage'] ?? null,
            'wagering_multiplier' => $data['wagering_multiplier'] ?? 20,
            'starts_at' => $data['starts_at'],
            'expires_at' => $data['expires_at'],
            'max_uses' => $data['max_uses'] ?? null,
            'max_uses_per_user' => $data['max_uses_per_user'] ?? 1,
            'status' => $data['status'] ?? 'active',
            'terms' => $data['terms'] ?? null,
        ]);
    }

    /**
     * Update a VIP promotion
     */
    public function updatePromotion(VipPromotion $promotion, array $data): VipPromotion
    {
        $promotion->update($data);
        return $promotion->fresh();
    }

    /**
     * Claim a VIP promotion
     */
    public function claimPromotion(User $user, int $promotionId): array
    {
        $promotion = VipPromotion::findOrFail($promotionId);

        // Validate eligibility
        if (!$promotion->isEligibleFor($user)) {
            throw new \Exception('You are not eligible for this promotion');
        }

        return DB::transaction(function () use ($user, $promotion) {
            // Increment usage count
            $promotion->increment('current_uses');

            // Award bonus based on promotion type
            $bonus = null;
            switch ($promotion->type) {
                case 'bonus':
                    $bonus = $this->bonusService->awardPromotionalBonus(
                        $user,
                        $promotion->value,
                        $promotion->wagering_multiplier,
                        $promotion->title
                    );
                    break;

                case 'cashback':
                    // Calculate cashback based on recent losses
                    $recentLosses = $user->bets()
                        ->where('created_at', '>=', now()->subDays(7))
                        ->where('result', 'loss')
                        ->sum('bet_amount');
                    
                    if ($recentLosses > 0) {
                        $cashbackAmount = $recentLosses * ($promotion->percentage / 100);
                        $bonus = $this->bonusService->awardCashbackBonus($user, $cashbackAmount);
                    }
                    break;

                case 'free_spins':
                    // Create bonus with specific type
                    $bonus = Bonus::create([
                        'uuid' => Str::uuid(),
                        'user_id' => $user->id,
                        'type' => 'free_spins',
                        'amount' => $promotion->value, // Number of free spins
                        'wagering_requirement' => $promotion->value * $promotion->wagering_multiplier,
                        'wagered_amount' => 0,
                        'status' => 'active',
                        'expires_at' => now()->addDays(30),
                    ]);
                    break;

                default:
                    throw new \Exception('Invalid promotion type');
            }

            // Record the claim
            $user->vipPromotions()->attach($promotion->id, [
                'bonus_id' => $bonus?->id,
                'claimed_at' => now(),
            ]);

            // Send notification
            $this->notificationService->createNotification(
                $user,
                'promotion_claimed',
                'Promotion Claimed!',
                "You've successfully claimed: {$promotion->title}",
                [
                    'promotion_id' => $promotion->id,
                    'promotion_title' => $promotion->title,
                    'bonus_id' => $bonus?->id,
                ]
            );

            return [
                'promotion' => $promotion,
                'bonus' => $bonus,
                'message' => 'Promotion claimed successfully!',
            ];
        });
    }

    /**
     * Get user's claimed promotions
     */
    public function getUserClaimedPromotions(User $user)
    {
        return $user->vipPromotions()
            ->withPivot(['bonus_id', 'claimed_at'])
            ->orderBy('vip_promotion_user.claimed_at', 'desc')
            ->get();
    }

    /**
     * Check if user has claimed a promotion
     */
    public function hasUserClaimed(User $user, int $promotionId): bool
    {
        return $user->vipPromotions()->where('vip_promotion_id', $promotionId)->exists();
    }

    /**
     * Auto-expire old promotions
     * Should be run as a scheduled job
     */
    public function expireOldPromotions(): int
    {
        return VipPromotion::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Get promotion statistics
     */
    public function getPromotionStats(VipPromotion $promotion): array
    {
        $totalClaims = $promotion->users()->count();
        $uniqueUsers = $promotion->users()->distinct('user_id')->count();
        $totalBonusAwarded = $promotion->users()
            ->join('bonuses', 'vip_promotion_user.bonus_id', '=', 'bonuses.id')
            ->sum('bonuses.amount');

        return [
            'total_claims' => $totalClaims,
            'unique_users' => $uniqueUsers,
            'total_bonus_awarded' => $totalBonusAwarded,
            'usage_percentage' => $promotion->max_uses 
                ? round(($promotion->current_uses / $promotion->max_uses) * 100, 2) 
                : 0,
        ];
    }
}
