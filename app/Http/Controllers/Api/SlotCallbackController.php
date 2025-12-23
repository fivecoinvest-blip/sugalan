<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotBet;
use App\Models\SlotGame;
use App\Services\SoftAPIService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlotCallbackController extends Controller
{
    public function __construct(
        private SoftAPIService $softAPIService,
        private WalletService $walletService
    ) {}

    /**
     * Handle balance check callback
     */
    public function balance(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('X-Signature');
            $payload = $request->all();

            // Verify signature
            if (!$this->softAPIService->verifyCallbackSignature($payload, $signature)) {
                Log::warning('Invalid callback signature', ['payload' => $payload]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid signature',
                ], 401);
            }

            $userId = $payload['user_id'] ?? null;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID required',
                ], 400);
            }

            $wallet = $this->walletService->getWallet($userId);

            return response()->json([
                'success' => true,
                'balance' => (float) $wallet->real_balance,
                'currency' => 'PHP',
            ]);

        } catch (\Exception $e) {
            Log::error('Balance callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle debit (bet placed) callback
     */
    public function debit(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('X-Signature');
            $payload = $request->all();

            // Verify signature
            if (!$this->softAPIService->verifyCallbackSignature($payload, $signature)) {
                Log::warning('Invalid debit callback signature', ['payload' => $payload]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid signature',
                ], 401);
            }

            $userId = $payload['user_id'] ?? null;
            $amount = $payload['amount'] ?? null;
            $transactionId = $payload['transaction_id'] ?? null;
            $gameId = $payload['game_id'] ?? null;
            $roundId = $payload['round_id'] ?? null;

            if (!$userId || !$amount || !$transactionId || !$gameId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Missing required parameters',
                ], 400);
            }

            // Check for duplicate transaction
            $existingBet = SlotBet::where('transaction_id', $transactionId)->first();
            if ($existingBet) {
                $wallet = $this->walletService->getWallet($userId);
                
                return response()->json([
                    'success' => true,
                    'balance' => (float) $wallet->real_balance,
                    'transaction_id' => $transactionId,
                ]);
            }

            $slotGame = SlotGame::where('game_id', $gameId)->first();
            if (!$slotGame) {
                Log::error('Slot game not found', ['game_id' => $gameId]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Game not found',
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Deduct from user's wallet
                $this->walletService->deduct($userId, $amount, 'real', [
                    'type' => 'slot_bet',
                    'description' => "Bet placed on {$slotGame->name}",
                    'reference_id' => $transactionId,
                ]);

                // Create slot bet record
                $slotBet = SlotBet::create([
                    'user_id' => $userId,
                    'slot_game_id' => $slotGame->id,
                    'transaction_id' => $transactionId,
                    'round_id' => $roundId,
                    'bet_amount' => $amount,
                    'win_amount' => 0,
                    'payout' => -$amount,
                    'status' => 'pending',
                    'balance_type' => 'real',
                    'game_data' => $payload,
                ]);

                DB::commit();

                $wallet = $this->walletService->getWallet($userId);

                Log::info('Slot bet placed', [
                    'user_id' => $userId,
                    'game' => $slotGame->name,
                    'amount' => $amount,
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'success' => true,
                    'balance' => (float) $wallet->real_balance,
                    'transaction_id' => $transactionId,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Debit callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle credit (win) callback
     */
    public function credit(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('X-Signature');
            $payload = $request->all();

            // Verify signature
            if (!$this->softAPIService->verifyCallbackSignature($payload, $signature)) {
                Log::warning('Invalid credit callback signature', ['payload' => $payload]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid signature',
                ], 401);
            }

            $userId = $payload['user_id'] ?? null;
            $amount = $payload['amount'] ?? null;
            $transactionId = $payload['transaction_id'] ?? null;
            $gameId = $payload['game_id'] ?? null;
            $roundId = $payload['round_id'] ?? null;

            if (!$userId || !$amount || !$transactionId || !$gameId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Missing required parameters',
                ], 400);
            }

            $slotGame = SlotGame::where('game_id', $gameId)->first();
            if (!$slotGame) {
                Log::error('Slot game not found', ['game_id' => $gameId]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Game not found',
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Find the bet by round_id or create new record for standalone win
                $slotBet = SlotBet::where('round_id', $roundId)
                    ->where('user_id', $userId)
                    ->where('slot_game_id', $slotGame->id)
                    ->first();

                if ($slotBet) {
                    // Update existing bet
                    $slotBet->update([
                        'win_amount' => $amount,
                        'payout' => $amount - $slotBet->bet_amount,
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                } else {
                    // Create new record for standalone win (bonus, free spin, etc.)
                    $slotBet = SlotBet::create([
                        'user_id' => $userId,
                        'slot_game_id' => $slotGame->id,
                        'transaction_id' => $transactionId,
                        'round_id' => $roundId,
                        'bet_amount' => 0,
                        'win_amount' => $amount,
                        'payout' => $amount,
                        'status' => 'completed',
                        'balance_type' => 'real',
                        'game_data' => $payload,
                        'completed_at' => now(),
                    ]);
                }

                // Credit user's wallet
                if ($amount > 0) {
                    $this->walletService->credit($userId, $amount, 'real', [
                        'type' => 'slot_win',
                        'description' => "Win from {$slotGame->name}",
                        'reference_id' => $transactionId,
                    ]);
                }

                DB::commit();

                $wallet = $this->walletService->getWallet($userId);

                Log::info('Slot win processed', [
                    'user_id' => $userId,
                    'game' => $slotGame->name,
                    'amount' => $amount,
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'success' => true,
                    'balance' => (float) $wallet->real_balance,
                    'transaction_id' => $transactionId,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Credit callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle rollback callback
     */
    public function rollback(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('X-Signature');
            $payload = $request->all();

            // Verify signature
            if (!$this->softAPIService->verifyCallbackSignature($payload, $signature)) {
                Log::warning('Invalid rollback callback signature', ['payload' => $payload]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid signature',
                ], 401);
            }

            $transactionId = $payload['transaction_id'] ?? null;

            if (!$transactionId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Transaction ID required',
                ], 400);
            }

            $slotBet = SlotBet::where('transaction_id', $transactionId)->first();
            if (!$slotBet) {
                return response()->json([
                    'success' => false,
                    'error' => 'Transaction not found',
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Refund the bet amount
                if ($slotBet->bet_amount > 0 && $slotBet->status !== 'refunded') {
                    $this->walletService->credit($slotBet->user_id, $slotBet->bet_amount, 'real', [
                        'type' => 'slot_refund',
                        'description' => "Refund for {$slotBet->slotGame->name}",
                        'reference_id' => $transactionId,
                    ]);
                }

                // Update bet status
                $slotBet->update([
                    'status' => 'refunded',
                ]);

                DB::commit();

                $wallet = $this->walletService->getWallet($slotBet->user_id);

                Log::info('Slot bet refunded', [
                    'user_id' => $slotBet->user_id,
                    'amount' => $slotBet->bet_amount,
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'success' => true,
                    'balance' => (float) $wallet->real_balance,
                    'transaction_id' => $transactionId,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Rollback callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
