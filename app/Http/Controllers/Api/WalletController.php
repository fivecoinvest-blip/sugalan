<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Get wallet balance
     */
    public function balance(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $balance = $this->walletService->getBalanceSummary($user);

            return response()->json([
                'success' => true,
                'data' => $balance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction history
     */
    public function transactions(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            $transactions = $user->transactions()
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
