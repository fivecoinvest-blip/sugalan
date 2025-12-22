<?php

namespace App\Services;

use App\Models\User;
use App\Models\PromotionalCampaign;
use App\Models\Bonus;
use App\Services\BonusService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class PromotionalCampaignService
{
    public function __construct(
        private BonusService $bonusService,
        private NotificationService $notificationService
    ) {}

    /**
     * Get all active campaigns
     */
    public function getActiveCampaigns(?User $user = null): array
    {
        $query = PromotionalCampaign::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            });

        $campaigns = $query->orderBy('created_at', 'desc')->get();

        return $campaigns->map(function ($campaign) use ($user) {
            $data = $campaign->toArray();
            
            if ($user) {
                $data['is_eligible'] = $campaign->isEligibleFor($user);
                $data['user_claims'] = $campaign->users()->where('user_id', $user->id)->count();
                $data['can_claim'] = $campaign->isEligibleFor($user);
            }

            $data['remaining_claims'] = $campaign->getRemainingClaims();

            return $data;
        })->toArray();
    }

    /**
     * Get campaign by code
     */
    public function getCampaignByCode(string $code): ?PromotionalCampaign
    {
        return PromotionalCampaign::where('code', $code)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Claim promotional campaign
     */
    public function claimCampaign(User $user, int $campaignId, ?float $depositAmount = null): array
    {
        $campaign = PromotionalCampaign::findOrFail($campaignId);

        if (!$campaign->isEligibleFor($user)) {
            throw new \Exception('You are not eligible for this campaign');
        }

        // Check minimum deposit requirement
        if ($campaign->min_deposit && (!$depositAmount || $depositAmount < $campaign->min_deposit)) {
            throw new \Exception("Minimum deposit of ₱{$campaign->min_deposit} required");
        }

        return DB::transaction(function () use ($user, $campaign, $depositAmount) {
            $bonus = null;

            switch ($campaign->type) {
                case 'bonus':
                    $bonus = $this->bonusService->awardPromotionalBonus(
                        $user,
                        $campaign->value,
                        $campaign->wagering_multiplier,
                        30,
                        $campaign->title
                    );
                    break;

                case 'reload':
                    if (!$depositAmount) {
                        throw new \Exception('Deposit amount required for reload bonus');
                    }

                    $bonusAmount = $campaign->calculateBonusAmount($depositAmount);
                    $bonus = $this->bonusService->awardPromotionalBonus(
                        $user,
                        $bonusAmount,
                        $campaign->wagering_multiplier,
                        30,
                        $campaign->title
                    );
                    break;

                case 'cashback':
                    // Calculate cashback based on recent losses
                    $recentLosses = $user->bets()
                        ->where('created_at', '>=', now()->subDays(7))
                        ->where('result', 'loss')
                        ->sum('bet_amount');

                    if ($recentLosses > 0 && $campaign->percentage) {
                        $cashbackAmount = $recentLosses * ($campaign->percentage / 100);
                        $bonus = $this->bonusService->awardCashbackBonus($user, $cashbackAmount);
                    }
                    break;

                case 'free_spins':
                    // Create free spins bonus
                    $bonus = Bonus::create([
                        'user_id' => $user->id,
                        'type' => 'free_spins',
                        'amount' => $campaign->value,
                        'wagering_requirement' => $campaign->value * $campaign->wagering_multiplier,
                        'wagering_progress' => 0,
                        'status' => 'active',
                        'expires_at' => now()->addDays(7),
                        'source_type' => PromotionalCampaign::class,
                        'source_id' => $campaign->id,
                    ]);
                    break;
            }

            // Record claim
            $campaign->users()->attach($user->id, [
                'bonus_id' => $bonus?->id,
                'claimed_at' => now(),
            ]);

            // Increment claim counter
            $campaign->increment('current_claims');

            // Send notification
            $this->notificationService->sendNotification(
                $user,
                'promotion_claimed',
                "🎉 {$campaign->title}",
                "You've claimed: {$campaign->description}",
                ['campaign_id' => $campaign->id, 'bonus_id' => $bonus?->id]
            );

            return [
                'campaign' => $campaign,
                'bonus' => $bonus,
                'message' => 'Campaign claimed successfully',
            ];
        });
    }

    /**
     * Get user's claimed campaigns
     */
    public function getUserClaimedCampaigns(User $user, int $perPage = 20)
    {
        return $user->promotionalCampaigns()
            ->orderBy('campaign_user.created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create promotional campaign (admin)
     */
    public function createCampaign(array $data): PromotionalCampaign
    {
        // Generate unique code if not provided
        if (empty($data['code'])) {
            $data['code'] = strtoupper(substr(md5(uniqid()), 0, 8));
        }

        return PromotionalCampaign::create($data);
    }

    /**
     * Update promotional campaign (admin)
     */
    public function updateCampaign(PromotionalCampaign $campaign, array $data): PromotionalCampaign
    {
        $campaign->update($data);
        return $campaign->fresh();
    }

    /**
     * Expire old campaigns
     */
    public function expireOldCampaigns(): int
    {
        return PromotionalCampaign::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStatistics(PromotionalCampaign $campaign): array
    {
        $totalClaims = $campaign->users()->count();
        $totalBonusValue = $campaign->users()
            ->join('bonuses', 'campaign_user.bonus_id', '=', 'bonuses.id')
            ->sum('bonuses.amount');

        $uniqueClaimers = $campaign->users()->distinct('user_id')->count('user_id');

        return [
            'total_claims' => $totalClaims,
            'unique_claimers' => $uniqueClaimers,
            'total_bonus_value' => $totalBonusValue,
            'average_bonus' => $uniqueClaimers > 0 ? $totalBonusValue / $uniqueClaimers : 0,
            'remaining_claims' => $campaign->getRemainingClaims(),
            'is_active' => $campaign->isActive(),
        ];
    }
}
