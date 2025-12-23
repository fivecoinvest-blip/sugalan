<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Get user balance summary
     */
    public function getBalance(User $user): array
    {
        return $this->getBalanceSummary($user);
    }

    /**
     * Credit real balance (simplified wrapper)
     */
    public function credit(User $user, float $amount, string $type, string $description): Transaction
    {
        return $this->creditRealBalance($user, $amount, $type, $description);
    }

    /**
     * Credit bonus balance (simplified wrapper)
     */
    public function creditBonus(User $user, float $amount, string $type, string $description): Transaction
    {
        return $this->creditBonusBalance($user, $amount, $description);
    }

    /**
     * Deduct from real balance (simplified wrapper)
     */
    public function deduct(User $user, float $amount, string $type, string $description): Transaction
    {
        if (!$user->wallet || $user->wallet->real_balance < $amount) {
            throw new \Exception('Insufficient balance');
        }
        return $this->debitRealBalance($user, $amount, $type, $description);
    }

    /**
     * Deduct bet (returns array with 'real' and 'bonus' keys for test compatibility,
     * but also includes 'real_used' and 'bonus_used' for game service compatibility)
     */
    public function deductBet(User $user, float $amount): array
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if (!$wallet->hasBalance($amount)) {
                throw new \InvalidArgumentException('Insufficient balance for bet');
            }

            $bonusUsed = 0;
            $realUsed = 0;

            // Use bonus balance first (opposite of current implementation to match test expectations)
            if ($wallet->bonus_balance >= $amount) {
                $bonusUsed = $amount;
            } else {
                $bonusUsed = $wallet->bonus_balance;
                $realUsed = $amount - $bonusUsed;
            }

            $wallet->bonus_balance -= $bonusUsed;
            $wallet->real_balance -= $realUsed;
            $wallet->lifetime_wagered += $amount;
            $wallet->save();

            // Return usage breakdown (cast to float for consistency)
            // Include both key formats for backward compatibility with games
            return [
                'real' => (float) $realUsed,
                'bonus' => (float) $bonusUsed,
                'real_used' => (float) $realUsed,
                'bonus_used' => (float) $bonusUsed,
            ];
        });
    }

    /**
     * Credit win payout (overloaded signature for game compatibility)
     */
    public function creditWin(User $user, float $amount, $gameOrBonusBet, ?string $description = null): Transaction
    {
        // Handle both old signature (bool) and new signature (string, string)
        if (is_bool($gameOrBonusBet)) {
            // Old signature: creditWin($user, $amount, $wasBonusBet)
            $wasBonusBet = $gameOrBonusBet;
            return DB::transaction(function () use ($user, $amount, $wasBonusBet) {
                $wallet = $user->wallet()->lockForUpdate()->first();

                $balanceType = $wasBonusBet ? 'bonus' : 'real';
                $balanceBefore = $wasBonusBet ? $wallet->bonus_balance : $wallet->real_balance;

                if ($wasBonusBet) {
                    $wallet->bonus_balance += $amount;
                } else {
                    $wallet->real_balance += $amount;
                }

                $wallet->lifetime_won += $amount;
                $wallet->save();

                $transaction = Transaction::create([
                    'uuid' => Str::uuid(),
                    'user_id' => $user->id,
                    'type' => 'win',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wasBonusBet ? $wallet->bonus_balance : $wallet->real_balance,
                    'balance_type' => $balanceType,
                    'description' => 'Game win payout',
                ]);

                return $transaction;
            });
        } else {
            // New signature: creditWin($user, $amount, $game, $description)
            $game = $gameOrBonusBet;
            return DB::transaction(function () use ($user, $amount, $game, $description) {
                $wallet = $user->wallet()->lockForUpdate()->first();

                $balanceBefore = $wallet->real_balance;
                $wallet->real_balance += $amount;
                $wallet->lifetime_won += $amount;
                $wallet->save();

                $transaction = Transaction::create([
                    'uuid' => Str::uuid(),
                    'user_id' => $user->id,
                    'type' => 'win',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->real_balance,
                    'balance_type' => 'real',
                    'description' => $description,
                ]);

                return $transaction;
            });
        }
    }

    /**
     * Lock balance (deduct from real, add to locked)
     */
    public function lockBalance(User $user, float $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if ($wallet->real_balance < $amount) {
                throw new \Exception('Insufficient available balance');
            }

            $wallet->real_balance -= $amount;
            $wallet->locked_balance += $amount;
            $wallet->save();

            $this->logWalletAction('lock_balance', $wallet, $amount);
        });
    }

    /**
     * Unlock balance (return locked to real)
     */
    public function unlockBalance(User $user, float $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            $wallet->locked_balance -= $amount;
            $wallet->real_balance += $amount;
            $wallet->save();

            $this->logWalletAction('unlock_balance', $wallet, $amount);
        });
    }

    /**
     * Release locked balance (deduct from locked after withdrawal approved)
     */
    public function releaseLockedBalance(User $user, float $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            $wallet->locked_balance -= $amount;
            $wallet->save();

            $this->logWalletAction('release_locked_balance', $wallet, $amount);
        });
    }

    /**
     * Transfer between users
     */
    public function transfer(User $fromUser, User $toUser, float $amount, string $description): void
    {
        DB::transaction(function () use ($fromUser, $toUser, $amount, $description) {
            $this->deduct($fromUser, $amount, 'admin_adjustment', $description . ' (sent)');
            $this->credit($toUser, $amount, 'admin_adjustment', $description . ' (received)');
        });
    }

    /**
     * Credit real balance
     */
    public function creditRealBalance(
        User $user,
        float $amount,
        string $type,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $referenceType, $referenceId, $metadata) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            $balanceBefore = $wallet->real_balance;
            $wallet->real_balance += $amount;
            $wallet->save();

            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->real_balance,
                'balance_type' => 'real',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            $this->logWalletAction('credit_real_balance', $wallet, $amount, $transaction);

            return $transaction;
        });
    }

    /**
     * Debit real balance
     */
    public function debitRealBalance(
        User $user,
        float $amount,
        string $type,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $referenceType, $referenceId, $metadata) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if ($wallet->real_balance < $amount) {
                throw new \Exception('Insufficient real balance');
            }

            $balanceBefore = $wallet->real_balance;
            $wallet->real_balance -= $amount;
            $wallet->save();

            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'type' => $type,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->real_balance,
                'balance_type' => 'real',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            $this->logWalletAction('debit_real_balance', $wallet, $amount, $transaction);

            return $transaction;
        });
    }

    /**
     * Credit bonus balance
     */
    public function creditBonusBalance(
        User $user,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            $balanceBefore = $wallet->bonus_balance;
            $wallet->bonus_balance += $amount;
            $wallet->save();

            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'type' => 'bonus_credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->bonus_balance,
                'balance_type' => 'bonus',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);

            $this->logWalletAction('credit_bonus_balance', $wallet, $amount, $transaction);

            return $transaction;
        });
    }

    /**
     * Debit bonus balance
     */
    public function debitBonusBalance(
        User $user,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if ($wallet->bonus_balance < $amount) {
                throw new \Exception('Insufficient bonus balance');
            }

            $balanceBefore = $wallet->bonus_balance;
            $wallet->bonus_balance -= $amount;
            $wallet->save();

            $transaction = Transaction::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'type' => 'bonus_debit',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->bonus_balance,
                'balance_type' => 'bonus',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);

            $this->logWalletAction('debit_bonus_balance', $wallet, $amount, $transaction);

            return $transaction;
        });
    }

    /**
     * Convert bonus to real balance (after wagering complete)
     */
    public function convertBonusToReal(User $user, float $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if ($wallet->bonus_balance < $amount) {
                throw new \Exception('Insufficient bonus balance');
            }

            $wallet->bonus_balance -= $amount;
            $wallet->real_balance += $amount;
            $wallet->save();

            Transaction::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'type' => 'bonus_conversion',
                'amount' => $amount,
                'balance_before' => $wallet->real_balance - $amount,
                'balance_after' => $wallet->real_balance,
                'balance_type' => 'real',
                'description' => 'Bonus converted to real balance',
            ]);

            $this->logWalletAction('bonus_converted', $wallet, $amount);
        });
    }

    /**
     * Get wallet balance summary
     */
    public function getBalanceSummary(User $user): array
    {
        $wallet = $user->wallet;

        return [
            'real_balance' => (float) $wallet->real_balance,
            'bonus_balance' => (float) $wallet->bonus_balance,
            'locked_balance' => (float) $wallet->locked_balance,
            'total_balance' => $wallet->getTotalBalance(),
            'available_balance' => $wallet->getAvailableBalance(),
            'lifetime_deposited' => (float) $wallet->lifetime_deposited,
            'lifetime_withdrawn' => (float) $wallet->lifetime_withdrawn,
            'lifetime_wagered' => (float) $wallet->lifetime_wagered,
            'lifetime_won' => (float) $wallet->lifetime_won,
            'lifetime_lost' => (float) $wallet->lifetime_lost,
        ];
    }

    /**
     * Log wallet action
     */
    private function logWalletAction(string $action, Wallet $wallet, ?float $amount = null, ?Transaction $transaction = null): void
    {
        AuditLog::create([
            'user_id' => $wallet->user_id,
            'actor_type' => 'system',
            'action' => $action,
            'resource_type' => 'wallet',
            'resource_id' => $wallet->id,
            'changes' => [
                'amount' => $amount,
                'transaction_id' => $transaction?->id,
                'real_balance' => $wallet->real_balance,
                'bonus_balance' => $wallet->bonus_balance,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
