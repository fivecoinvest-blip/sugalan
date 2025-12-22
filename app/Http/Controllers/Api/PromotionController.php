<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromotionalCampaignService;
use App\Services\DailyRewardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    public function __construct(
        private PromotionalCampaignService $campaignService,
        private DailyRewardService $dailyRewardService
    ) {}

    /**
     * Get all active promotional campaigns
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        $campaigns = $this->campaignService->getActiveCampaigns($request->user());

        return response()->json([
            'campaigns' => $campaigns
        ]);
    }

    /**
     * Get campaign by code
     */
    public function getCampaignByCode(Request $request, string $code): JsonResponse
    {
        $campaign = $this->campaignService->getCampaignByCode($code);

        if (!$campaign) {
            return response()->json([
                'message' => 'Campaign not found or expired'
            ], 404);
        }

        $eligible = $campaign->isEligibleFor($request->user());

        return response()->json([
            'campaign' => $campaign,
            'is_eligible' => $eligible,
        ]);
    }

    /**
     * Claim promotional campaign
     */
    public function claimCampaign(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => 'required|integer|exists:promotional_campaigns,id',
            'deposit_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->campaignService->claimCampaign(
                $request->user(),
                $request->input('campaign_id'),
                $request->input('deposit_amount')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get user's claimed campaigns
     */
    public function getClaimedCampaigns(Request $request): JsonResponse
    {
        $campaigns = $this->campaignService->getUserClaimedCampaigns($request->user());

        return response()->json([
            'campaigns' => $campaigns->items(),
            'pagination' => [
                'total' => $campaigns->total(),
                'per_page' => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
            ]
        ]);
    }

    /**
     * Get daily reward status
     */
    public function getDailyRewardStatus(Request $request): JsonResponse
    {
        $status = $this->dailyRewardService->getDailyRewardStatus($request->user());

        return response()->json($status);
    }

    /**
     * Claim daily reward
     */
    public function claimDailyReward(Request $request): JsonResponse
    {
        try {
            $result = $this->dailyRewardService->claimDailyReward($request->user());

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get daily reward history
     */
    public function getDailyRewardHistory(Request $request): JsonResponse
    {
        $history = $this->dailyRewardService->getUserRewardHistory($request->user());

        return response()->json([
            'history' => $history->items(),
            'pagination' => [
                'total' => $history->total(),
                'per_page' => $history->perPage(),
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
            ]
        ]);
    }
}
