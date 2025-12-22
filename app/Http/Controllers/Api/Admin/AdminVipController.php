<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VipLevel;
use App\Models\VipPromotion;
use App\Services\VipPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminVipController extends Controller
{
    public function __construct(
        private VipPromotionService $vipPromotionService
    ) {}

    /**
     * Get VIP analytics overview
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            // User distribution by VIP level
            $distribution = User::select('vip_level_id', DB::raw('count(*) as count'))
                ->groupBy('vip_level_id')
                ->get()
                ->map(function ($item) {
                    $level = VipLevel::find($item->vip_level_id);
                    return [
                        'level' => $level?->name ?? 'Unknown',
                        'level_number' => $level?->level ?? 0,
                        'count' => $item->count,
                    ];
                });

            // Recent VIP upgrades (last 30 days)
            $recentUpgrades = DB::table('audit_logs')
                ->where('action', 'vip_upgraded')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            // Recent VIP downgrades (last 30 days)
            $recentDowngrades = DB::table('audit_logs')
                ->where('action', 'vip_downgraded')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            // Total wagering by VIP level (last 30 days)
            $wageringByLevel = User::join('bets', 'users.id', '=', 'bets.user_id')
                ->join('vip_levels', 'users.vip_level_id', '=', 'vip_levels.id')
                ->where('bets.created_at', '>=', now()->subDays(30))
                ->select('vip_levels.name', 'vip_levels.level', DB::raw('SUM(bets.bet_amount) as total_wagered'))
                ->groupBy('vip_levels.id', 'vip_levels.name', 'vip_levels.level')
                ->orderBy('vip_levels.level')
                ->get();

            // Average lifetime value by VIP level
            $avgLtvByLevel = User::join('vip_levels', 'users.vip_level_id', '=', 'vip_levels.id')
                ->select('vip_levels.name', 'vip_levels.level', DB::raw('AVG(users.total_wagered) as avg_ltv'))
                ->groupBy('vip_levels.id', 'vip_levels.name', 'vip_levels.level')
                ->orderBy('vip_levels.level')
                ->get();

            // Active promotions stats
            $activePromotions = VipPromotion::where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('expires_at', '>=', now())
                ->count();

            $totalPromotionClaims = DB::table('vip_promotion_user')
                ->where('claimed_at', '>=', now()->subDays(30))
                ->count();

            // Users close to upgrade (within 10% of next level)
            $usersCloseToUpgrade = User::where('status', 'active')
                ->get()
                ->filter(function ($user) {
                    $currentLevel = $user->vipLevel;
                    $nextLevel = VipLevel::where('level', '>', $currentLevel->level)
                        ->orderBy('level', 'asc')
                        ->first();

                    if (!$nextLevel) {
                        return false;
                    }

                    $progress = $user->total_wagered / $nextLevel->min_wager_requirement;
                    return $progress >= 0.9 && $progress < 1.0;
                })
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'distribution' => $distribution,
                    'recent_upgrades' => $recentUpgrades,
                    'recent_downgrades' => $recentDowngrades,
                    'wagering_by_level' => $wageringByLevel,
                    'avg_ltv_by_level' => $avgLtvByLevel,
                    'active_promotions' => $activePromotions,
                    'promotion_claims_30d' => $totalPromotionClaims,
                    'users_close_to_upgrade' => $usersCloseToUpgrade,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all VIP promotions (admin view)
     */
    public function getPromotions(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status');
            $promotions = $this->vipPromotionService->getAllPromotions($status);

            // Add stats to each promotion
            $promotionsWithStats = $promotions->map(function ($promo) {
                $stats = $this->vipPromotionService->getPromotionStats($promo);
                return array_merge($promo->toArray(), ['stats' => $stats]);
            });

            return response()->json([
                'success' => true,
                'data' => $promotionsWithStats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new VIP promotion
     */
    public function createPromotion(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'type' => 'required|in:bonus,cashback,free_spins,tournament',
                'min_vip_level' => 'required|integer|min:1|max:5',
                'max_vip_level' => 'nullable|integer|min:1|max:5',
                'value' => 'required|numeric|min:0',
                'percentage' => 'nullable|numeric|min:0|max:100',
                'wagering_multiplier' => 'required|integer|min:1|max:100',
                'starts_at' => 'required|date',
                'expires_at' => 'required|date|after:starts_at',
                'max_uses' => 'nullable|integer|min:1',
                'max_uses_per_user' => 'required|integer|min:1',
                'status' => 'required|in:active,inactive,expired',
                'terms' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $promotion = $this->vipPromotionService->createPromotion($request->all());

            return response()->json([
                'success' => true,
                'data' => $promotion,
                'message' => 'Promotion created successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a VIP promotion
     */
    public function updatePromotion(Request $request, int $id): JsonResponse
    {
        try {
            $promotion = VipPromotion::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'type' => 'sometimes|in:bonus,cashback,free_spins,tournament',
                'min_vip_level' => 'sometimes|integer|min:1|max:5',
                'max_vip_level' => 'nullable|integer|min:1|max:5',
                'value' => 'sometimes|numeric|min:0',
                'percentage' => 'nullable|numeric|min:0|max:100',
                'wagering_multiplier' => 'sometimes|integer|min:1|max:100',
                'starts_at' => 'sometimes|date',
                'expires_at' => 'sometimes|date',
                'max_uses' => 'nullable|integer|min:1',
                'max_uses_per_user' => 'sometimes|integer|min:1',
                'status' => 'sometimes|in:active,inactive,expired',
                'terms' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $promotion = $this->vipPromotionService->updatePromotion($promotion, $request->all());

            return response()->json([
                'success' => true,
                'data' => $promotion,
                'message' => 'Promotion updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a VIP promotion
     */
    public function deletePromotion(int $id): JsonResponse
    {
        try {
            $promotion = VipPromotion::findOrFail($id);
            $promotion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Promotion deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get VIP level progression report
     */
    public function getProgressionReport(Request $request): JsonResponse
    {
        try {
            $days = $request->query('days', 30);

            $upgrades = DB::table('audit_logs')
                ->where('action', 'vip_upgraded')
                ->where('created_at', '>=', now()->subDays($days))
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $downgrades = DB::table('audit_logs')
                ->where('action', 'vip_downgraded')
                ->where('created_at', '>=', now()->subDays($days))
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'upgrades' => $upgrades,
                    'downgrades' => $downgrades,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
