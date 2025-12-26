<?php

namespace App\Services;

use App\Models\SlotSession;
use App\Models\SlotTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlotWalletService
{
    public function __construct(
        private SlotSessionService $sessionService
    ) {}

    /**
     * Process bet callback from provider
     */
    public function processBet(
        SlotSession $session,
        string $roundId,
        string $externalTxnId,
        float $betAmount,
        array $gameData = []
    ): array {
        return DB::transaction(function () use ($session, $roundId, $externalTxnId, $betAmount, $gameData) {
            // Check for duplicate transaction (idempotency)
            $existing = SlotTransaction::findByExternalId($externalTxnId);
            if ($existing) {
                Log::info('Duplicate bet transaction ignored', [
                    'external_txn_id' => $externalTxnId,
                    'session_id' => $session->id,
                ]);
                
                return [
                    'success' => true,
                    'balance' => $existing->balance_after,
                    'transaction_id' => $existing->uuid,
                    'duplicate' => true,
                ];
            }
            
            // Validate session
            if (!$this->sessionService->validateSession($session)) {
                throw new \Exception('Invalid or expired session');
            }
            
            // Get user's wallet
            $wallet = Wallet::where('user_id', $session->user_id)
                ->lockForUpdate()
                ->first();
            
            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }
            
            // Check sufficient balance
            if ($wallet->real_balance < $betAmount) {
                throw new \Exception('Insufficient balance');
            }
            
            // Deduct bet amount from wallet
            $balanceBefore = $wallet->real_balance;
            $wallet->real_balance -= $betAmount;
            $wallet->save();
            
            // Create core transaction record
            $coreTransaction = Transaction::create([
                'user_id' => $session->user_id,
                'type' => 'bet',
                'amount' => -$betAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->real_balance,
                'description' => "Slot bet - Round {$roundId}",
                'metadata' => ['round_id' => $roundId, 'game_id' => $session->game_id],
            ]);
            
            $balanceAfter = $wallet->real_balance;
            
            // Create slot transaction record
            $slotTransaction = SlotTransaction::create([
                'session_id' => $session->id,
                'user_id' => $session->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $coreTransaction->id,
                'round_id' => $roundId,
                'external_txn_id' => $externalTxnId,
                'type' => 'bet',
                'amount' => $betAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'game_data' => $gameData,
                'status' => 'completed',
            ]);
            
            // Update session statistics
            $this->sessionService->updateSessionStats(
                $session,
                $betAmount,
                0,
                $balanceAfter
            );
            
            Log::info('Slot bet processed', [
                'session_id' => $session->id,
                'round_id' => $roundId,
                'amount' => $betAmount,
                'balance' => $balanceAfter,
            ]);
            
            return [
                'success' => true,
                'balance' => $balanceAfter,
                'transaction_id' => $slotTransaction->uuid,
                'duplicate' => false,
            ];
        });
    }

    /**
     * Process win callback from provider
     */
    public function processWin(
        SlotSession $session,
        string $roundId,
        string $externalTxnId,
        float $winAmount,
        array $gameData = []
    ): array {
        return DB::transaction(function () use ($session, $roundId, $externalTxnId, $winAmount, $gameData) {
            // Check for duplicate transaction (idempotency)
            $existing = SlotTransaction::findByExternalId($externalTxnId);
            if ($existing) {
                Log::info('Duplicate win transaction ignored', [
                    'external_txn_id' => $externalTxnId,
                    'session_id' => $session->id,
                ]);
                
                return [
                    'success' => true,
                    'balance' => $existing->balance_after,
                    'transaction_id' => $existing->uuid,
                    'duplicate' => true,
                ];
            }
            
            // Validate session
            if (!$this->sessionService->validateSession($session)) {
                throw new \Exception('Invalid or expired session');
            }
            
            // Get user's wallet
            $wallet = Wallet::where('user_id', $session->user_id)
                ->lockForUpdate()
                ->first();
            
            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }
            
            $balanceBefore = $wallet->real_balance;
            $coreTransaction = null;
            
            // Add win amount to wallet if > 0
            if ($winAmount > 0) {
                $wallet->real_balance += $winAmount;
                $wallet->save();
                
                // Create core transaction record
                $coreTransaction = Transaction::create([
                    'user_id' => $session->user_id,
                    'type' => 'win',
                    'amount' => $winAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->real_balance,
                    'description' => "Slot win - Round {$roundId}",
                    'metadata' => ['round_id' => $roundId, 'game_id' => $session->game_id],
                ]);
            }
            
            $balanceAfter = $wallet->real_balance;
            
            // Create slot transaction record
            $slotTransaction = SlotTransaction::create([
                'session_id' => $session->id,
                'user_id' => $session->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $coreTransaction?->id,
                'round_id' => $roundId,
                'external_txn_id' => $externalTxnId,
                'type' => 'win',
                'amount' => $winAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'game_data' => $gameData,
                'status' => 'completed',
            ]);
            
            // Update session statistics
            $this->sessionService->updateSessionStats(
                $session,
                0,
                $winAmount,
                $balanceAfter
            );
            
            Log::info('Slot win processed', [
                'session_id' => $session->id,
                'round_id' => $roundId,
                'amount' => $winAmount,
                'balance' => $balanceAfter,
            ]);
            
            return [
                'success' => true,
                'balance' => $balanceAfter,
                'transaction_id' => $slotTransaction->uuid,
                'duplicate' => false,
            ];
        });
    }

    /**
     * Process rollback callback from provider
     */
    public function processRollback(
        SlotSession $session,
        string $roundId,
        string $externalTxnId
    ): array {
        return DB::transaction(function () use ($session, $roundId, $externalTxnId) {
            // Find the original transaction to rollback
            $originalTransaction = SlotTransaction::where('round_id', $roundId)
                ->where('session_id', $session->id)
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if (!$originalTransaction) {
                throw new \Exception('Original transaction not found for rollback');
            }
            
            // Check if already rolled back
            $existingRollback = SlotTransaction::where('round_id', $roundId)
                ->where('type', 'rollback')
                ->where('external_txn_id', $externalTxnId)
                ->first();
            
            if ($existingRollback) {
                Log::info('Duplicate rollback transaction ignored', [
                    'external_txn_id' => $externalTxnId,
                    'round_id' => $roundId,
                ]);
                
                return [
                    'success' => true,
                    'balance' => $existingRollback->balance_after,
                    'transaction_id' => $existingRollback->uuid,
                    'duplicate' => true,
                ];
            }
            
            // Get user's wallet
            $wallet = Wallet::where('user_id', $session->user_id)
                ->lockForUpdate()
                ->first();
            
            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }
            
            $balanceBefore = $wallet->real_balance;
            
            // Reverse the original transaction
            if ($originalTransaction->type === 'bet') {
                // Refund the bet
                $wallet->real_balance += $originalTransaction->amount;
                $wallet->save();
                
                $coreTransaction = Transaction::create([
                    'user_id' => $session->user_id,
                    'type' => 'refund',
                    'amount' => $originalTransaction->amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->real_balance,
                    'description' => "Slot bet refund - Round {$roundId}",
                    'metadata' => ['round_id' => $roundId, 'original_txn_id' => $originalTransaction->uuid],
                ]);
            } elseif ($originalTransaction->type === 'win') {
                // Deduct the win
                $wallet->real_balance -= $originalTransaction->amount;
                $wallet->save();
                
                $coreTransaction = Transaction::create([
                    'user_id' => $session->user_id,
                    'type' => 'refund',
                    'amount' => -$originalTransaction->amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->real_balance,
                    'description' => "Slot win reversal - Round {$roundId}",
                    'metadata' => ['round_id' => $roundId, 'original_txn_id' => $originalTransaction->uuid],
                ]);
            }
            
            $wallet->refresh();
            $balanceAfter = $wallet->balance;
            
            // Mark original transaction as rolled back
            $originalTransaction->update(['status' => 'rolled_back']);
            
            // Create rollback transaction record
            $rollbackTransaction = SlotTransaction::create([
                'session_id' => $session->id,
                'user_id' => $session->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $coreTransaction->id,
                'round_id' => $roundId,
                'external_txn_id' => $externalTxnId,
                'type' => 'rollback',
                'amount' => $originalTransaction->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'game_data' => ['original_txn_id' => $originalTransaction->uuid],
                'status' => 'completed',
            ]);
            
            // Update session statistics (reverse the amounts)
            if ($originalTransaction->type === 'bet') {
                $this->sessionService->updateSessionStats(
                    $session,
                    -$originalTransaction->amount,
                    0,
                    $balanceAfter
                );
            } elseif ($originalTransaction->type === 'win') {
                $this->sessionService->updateSessionStats(
                    $session,
                    0,
                    -$originalTransaction->amount,
                    $balanceAfter
                );
            }
            
            Log::info('Slot transaction rolled back', [
                'session_id' => $session->id,
                'round_id' => $roundId,
                'original_type' => $originalTransaction->type,
                'amount' => $originalTransaction->amount,
                'balance' => $balanceAfter,
            ]);
            
            return [
                'success' => true,
                'balance' => $balanceAfter,
                'transaction_id' => $rollbackTransaction->uuid,
                'duplicate' => false,
            ];
        });
    }

    /**
     * Get user's current balance
     */
    public function getUserBalance(User $user): float
    {
        $wallet = Wallet::where('user_id', $user->id)->first();
        
        return $wallet ? $wallet->real_balance : 0;
    }

    /**
     * Get session transactions
     */
    public function getSessionTransactions(SlotSession $session): array
    {
        return SlotTransaction::with(['user', 'wallet'])
            ->where('session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get round transactions
     */
    public function getRoundTransactions(SlotSession $session, string $roundId): array
    {
        return SlotTransaction::with(['user', 'wallet'])
            ->where('session_id', $session->id)
            ->where('round_id', $roundId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Process seamless transaction (AYUT format)
     * Handles both bet and win in single transaction
     * 
     * @param int $userId User ID
     * @param string $serialNumber UUID for idempotency (from AYUT)
     * @param string $gameUid Game identifier
     * @param string $gameRound Round identifier
     * @param float $betAmount Bet amount (negative = refund)
     * @param float $winAmount Win amount (negative = refund)
     * @param mixed $gameData Additional game data
     * @return array Result with balance and success status
     */
    public function processSeamlessTransaction(
        int $userId,
        string $serialNumber,
        string $gameUid,
        string $gameRound,
        float $betAmount,
        float $winAmount,
        $gameData = null
    ): array {
        return DB::transaction(function () use (
            $userId, $serialNumber, $gameUid, $gameRound, 
            $betAmount, $winAmount, $gameData
        ) {
            // Check for duplicate transaction using serial_number (idempotency)
            $existing = SlotTransaction::where('external_txn_id', $serialNumber)->first();
            
            if ($existing) {
                Log::info('Seamless transaction: Duplicate detected', [
                    'serial_number' => $serialNumber,
                    'user_id' => $userId,
                    'existing_balance' => $existing->balance_after,
                ]);
                
                return [
                    'success' => true,
                    'balance' => $existing->balance_after,
                    'duplicate' => true,
                ];
            }
            
            // Get user's wallet
            $wallet = Wallet::where('user_id', $userId)
                ->lockForUpdate()
                ->first();
            
            if (!$wallet) {
                Log::error('Seamless transaction: Wallet not found', [
                    'user_id' => $userId,
                    'serial_number' => $serialNumber,
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Wallet not found',
                    'balance' => 0,
                ];
            }
            
            $balanceBefore = $wallet->real_balance;
            
            // Calculate net amount: credit_amount = credit_amount - bet_amount + win_amount
            // If bet_amount is negative, it's a refund (add back)
            // If win_amount is negative, it's a refund (subtract)
            $netAmount = -$betAmount + $winAmount;
            $newBalance = $balanceBefore + $netAmount;
            
            // Check for sufficient balance (only if net is negative)
            if ($netAmount < 0 && $newBalance < 0) {
                Log::warning('Seamless transaction: Insufficient balance', [
                    'user_id' => $userId,
                    'serial_number' => $serialNumber,
                    'balance_before' => $balanceBefore,
                    'net_amount' => $netAmount,
                    'bet_amount' => $betAmount,
                    'win_amount' => $winAmount,
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Insufficient balance',
                    'balance' => $balanceBefore,
                ];
            }
            
            // Update wallet balance
            $wallet->real_balance = $newBalance;
            $wallet->save();
            
            // Determine transaction type
            if ($betAmount > 0 && $winAmount == 0) {
                $type = 'bet';
                $description = "Slot bet - Round {$gameRound}";
            } elseif ($betAmount == 0 && $winAmount > 0) {
                $type = 'win';
                $description = "Slot win - Round {$gameRound}";
            } elseif ($betAmount > 0 && $winAmount > 0) {
                // When both bet and win in same transaction, use 'win' type with net amount
                $type = 'win';
                $description = "Slot bet+win - Round {$gameRound}";
            } elseif ($betAmount < 0) {
                $type = 'refund';
                $description = "Slot refund - Round {$gameRound}";
            } else {
                // Use admin_adjustment for other cases
                $type = 'admin_adjustment';
                $description = "Slot adjustment - Round {$gameRound}";
            }
            
            // Create core transaction record
            $coreTransaction = Transaction::create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $netAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'description' => $description,
                'metadata' => [
                    'game_uid' => $gameUid,
                    'game_round' => $gameRound,
                    'serial_number' => $serialNumber,
                    'bet_amount' => $betAmount,
                    'win_amount' => $winAmount,
                    'game_data' => $gameData,
                ],
            ]);
            
            // Create slot transaction record
            $slotTransaction = SlotTransaction::create([
                'session_id' => null, // Seamless mode doesn't use sessions
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'transaction_id' => $coreTransaction->id,
                'round_id' => $gameRound,
                'external_txn_id' => $serialNumber, // Use serial_number for idempotency
                'type' => $type,
                'amount' => abs($netAmount),
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'game_data' => [
                    'game_uid' => $gameUid,
                    'bet_amount' => $betAmount,
                    'win_amount' => $winAmount,
                    'data' => $gameData,
                ],
                'status' => 'completed',
            ]);
            
            Log::info('Seamless transaction processed', [
                'serial_number' => $serialNumber,
                'user_id' => $userId,
                'game_round' => $gameRound,
                'bet_amount' => $betAmount,
                'win_amount' => $winAmount,
                'net_amount' => $netAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'type' => $type,
            ]);
            
            return [
                'success' => true,
                'balance' => $newBalance,
                'transaction_id' => $slotTransaction->uuid,
                'duplicate' => false,
            ];
        });
    }
}
