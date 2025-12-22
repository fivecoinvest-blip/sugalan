<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {}

    /**
     * Get user's referral statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->referralService->getReferralStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get referral leaderboard
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $leaderboard = $this->referralService->getLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => $leaderboard,
        ]);
    }

    /**
     * Validate referral code
     */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'referral_code' => 'required|string|size:8',
        ]);

        $referralCode = $request->input('referral_code');
        $referrer = $this->referralService->validateReferralCode($referralCode);

        if (!$referrer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid referral code',
            ], 404);
        }

        // Check if user is trying to use their own code
        if ($request->user() && $request->user()->id === $referrer->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot use your own referral code',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referrer_username' => $referrer->username,
                'referral_code' => $referralCode,
            ],
        ]);
    }

    /**
     * Get user's referral code
     */
    public function getMyReferralCode(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code,
                'referral_url' => config('app.url') . '/register?ref=' . $user->referral_code,
            ],
        ]);
    }

    /**
     * Get user's referred users
     */
    public function getReferredUsers(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        $referrals = $user->referrals()
            ->with(['referee' => function($query) {
                $query->select('id', 'username', 'vip_level_id', 'created_at');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $referrals,
        ]);
    }
}
