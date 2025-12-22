<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionalCampaign;
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
     * Get all campaigns (admin)
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        $status = $request->input('status', 'all'); // all, active, paused, expired

        $query = PromotionalCampaign::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $campaigns = $query->orderBy('created_at', 'desc')->paginate(20);

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
     * Get campaign statistics
     */
    public function getCampaignStatistics(int $id): JsonResponse
    {
        $campaign = PromotionalCampaign::findOrFail($id);
        $statistics = $this->campaignService->getCampaignStatistics($campaign);

        return response()->json([
            'campaign' => $campaign,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Create promotional campaign
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'code' => 'nullable|string|max:50|unique:promotional_campaigns,code',
            'type' => 'required|in:bonus,free_spins,cashback,reload,tournament',
            'value' => 'required_unless:type,reload|numeric|min:0',
            'percentage' => 'required_if:type,reload,cashback|nullable|numeric|min:0|max:100',
            'min_deposit' => 'nullable|numeric|min:0',
            'max_bonus' => 'nullable|numeric|min:0',
            'wagering_multiplier' => 'required|integer|min:1|max:100',
            'min_vip_level' => 'required|integer|min:1|max:5',
            'max_vip_level' => 'nullable|integer|min:1|max:5',
            'max_claims_total' => 'nullable|integer|min:1',
            'max_claims_per_user' => 'required|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'terms' => 'nullable|string',
        ]);

        try {
            $campaign = $this->campaignService->createCampaign($request->all());

            return response()->json([
                'message' => 'Campaign created successfully',
                'campaign' => $campaign
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create campaign',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update promotional campaign
     */
    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = PromotionalCampaign::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:active,paused,expired',
            'value' => 'sometimes|numeric|min:0',
            'percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'min_deposit' => 'sometimes|nullable|numeric|min:0',
            'max_bonus' => 'sometimes|nullable|numeric|min:0',
            'wagering_multiplier' => 'sometimes|integer|min:1|max:100',
            'max_claims_total' => 'sometimes|nullable|integer|min:1',
            'max_claims_per_user' => 'sometimes|integer|min:1',
            'starts_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date',
            'terms' => 'sometimes|nullable|string',
        ]);

        try {
            $updated = $this->campaignService->updateCampaign($campaign, $request->all());

            return response()->json([
                'message' => 'Campaign updated successfully',
                'campaign' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update campaign',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete promotional campaign
     */
    public function deleteCampaign(int $id): JsonResponse
    {
        $campaign = PromotionalCampaign::findOrFail($id);
        $campaign->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully'
        ]);
    }

    /**
     * Get daily reward statistics
     */
    public function getDailyRewardStatistics(): JsonResponse
    {
        $statistics = $this->dailyRewardService->getStatistics();

        return response()->json($statistics);
    }
}
