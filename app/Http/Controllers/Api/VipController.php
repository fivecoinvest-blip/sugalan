<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VipService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VipController extends Controller
{
    public function __construct(
        private VipService $vipService
    ) {}

    /**
     * Get user's VIP benefits
     */
    public function getBenefits(Request $request): JsonResponse
    {
        $user = $request->user();
        $benefits = $this->vipService->calculateBenefits($user);

        return response()->json([
            'success' => true,
            'data' => $benefits,
        ]);
    }

    /**
     * Get all VIP levels and requirements
     */
    public function getLevels(Request $request): JsonResponse
    {
        $levels = $this->vipService->getAllLevels();

        return response()->json([
            'success' => true,
            'data' => $levels,
        ]);
    }

    /**
     * Get progress to next VIP level
     */
    public function getProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        $progress = $this->vipService->getProgressToNextLevel($user);

        if (!$progress) {
            return response()->json([
                'success' => true,
                'message' => 'You are at the maximum VIP level',
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $progress,
        ]);
    }
}
