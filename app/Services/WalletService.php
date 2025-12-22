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
     * Lock balance (for pending withdrawals)
     */
    public function lockBalance(User $user, float $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if ($wallet->getAvailableBalance() < $amount) {
                throw new \Exception('Insufficient available balance');
            }

            $wallet->locked_balance += $amount;
            $wallet->save();

            $this->logWalletAction('lock_balance', $wallet, $amount);
        });
    }

    /**
     * Unlock balance (canceled withdrawal)
     */
    public function unlockBalance(User $user, float $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            $wallet->locked_balance -= $amount;
            $wallet->save();

            $this->logWalletAction('unlock_balance', $wallet, $amount);
        });
    }

    /**
     * Deduct bet from balance (prefers real balance over bonus)
     */
    public function deductBet(User $user, float $amount): array
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if (!$wallet->hasBalance($amount)) {
                throw new \InvalidArgumentException('Insufficient balance for bet');
            }

            $realUsed = 0;
            $bonusUsed = 0;

            // Use real balance first
            if ($wallet->real_balance >= $amount) {
                $realUsed = $amount;
            } else {
                $realUsed = $wallet->real_balance;
                $bonusUsed = $amount - $realUsed;
            }

            $wallet->real_balance -= $realUsed;
            $wallet->bonus_balance -= $bonusUsed;
            $wallet->lifetime_wagered += $amount;
            $wallet->save();

            return [
                'real_used' => $realUsed,
                'bonus_used' => $bonusUsed,
            ];
        });
    }

    /**
     * Credit win payout
     */
    public function creditWin(User $user, float $amount, bool $wasBonusBet): Transaction
    {
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
