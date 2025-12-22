<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VipPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class VipPromotionController extends Controller
{
    public function __construct(
        private VipPromotionService $vipPromotionService
    ) {}

    /**
     * Get available promotions for the authenticated user
     */
    public function getAvailablePromotions(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $promotions = $this->vipPromotionService->getAvailablePromotions($user);

            return response()->json([
                'success' => true,
                'data' => $promotions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's claimed promotions
     */
    public function getClaimedPromotions(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $promotions = $this->vipPromotionService->getUserClaimedPromotions($user);

            return response()->json([
                'success' => true,
                'data' => $promotions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Claim a promotion
     */
    public function claimPromotion(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validator = Validator::make($request->all(), [
                'promotion_id' => 'required|integer|exists:vip_promotions,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $result = $this->vipPromotionService->claimPromotion($user, $request->promotion_id);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
