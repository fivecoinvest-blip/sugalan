<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BonusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BonusController extends Controller
{
    public function __construct(
        private BonusService $bonusService
    ) {}

    /**
     * Get user's active bonuses
     */
    public function getActiveBonuses(Request $request): JsonResponse
    {
        $user = $request->user();
        $bonuses = $this->bonusService->getActiveBonuses($user);

        return response()->json([
            'success' => true,
            'data' => $bonuses,
        ]);
    }

    /**
     * Get bonus history
     */
    public function getBonusHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $bonuses = $this->bonusService->getBonusHistory($user);

        return response()->json([
            'success' => true,
            'data' => $bonuses,
        ]);
    }

    /**
     * Cancel active bonus
     */
    public function cancelBonus(Request $request, int $bonusId): JsonResponse
    {
        $user = $request->user();

        try {
            $forfeited = $this->bonusService->cancelBonus($user, $bonusId);

            return response()->json([
                'success' => true,
                'message' => 'Bonus cancelled successfully',
                'forfeited_amount' => $forfeited,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get bonus wagering statistics
     */
    public function getWageringStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeBonuses = $this->bonusService->getActiveBonuses($user);

        $stats = [];
        foreach ($activeBonuses as $bonus) {
            $stats[] = [
                'bonus_id' => $bonus->id,
                'bonus_type' => $bonus->bonus_type,
                'bonus_amount' => $bonus->bonus_amount,
                'wagering_requirement' => $bonus->wagering_requirement,
                'wagering_progress' => $bonus->wagering_progress,
                'wagering_percentage' => $bonus->wagering_requirement > 0 
                    ? round(($bonus->wagering_progress / $bonus->wagering_requirement) * 100, 2)
                    : 0,
                'remaining_wagering' => max(0, $bonus->wagering_requirement - $bonus->wagering_progress),
                'expires_at' => $bonus->expires_at,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
