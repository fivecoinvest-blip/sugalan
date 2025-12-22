<?php

namespace App\Services;

use App\Models\User;
use App\Models\Withdrawal;
use App\Models\Bonus;
use App\Models\AuditLog;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class WithdrawalService
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService
    ) {}

    /**
     * Create withdrawal request
     */
    public function createWithdrawalRequest(
        User $user,
        float $amount,
        string $gcashNumber,
        string $gcashName
    ): Withdrawal {
        return DB::transaction(function () use ($user, $amount, $gcashNumber, $gcashName) {
            // Validate user eligibility
            $this->validateWithdrawalEligibility($user, $amount);

            // Lock the balance
            $this->walletService->lockBalance($user, $amount);

            // Perform validation checks
            $wageringComplete = $this->checkWageringRequirements($user);
            $phoneVerified = !empty($user->phone_number) && !is_null($user->phone_verified_at);
            $vipLimitPassed = $this->checkVipLimits($user, $amount);

            // Create withdrawal record
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'gcash_number' => $gcashNumber,
                'gcash_name' => $gcashName,
                'status' => 'pending',
                'wagering_complete' => $wageringComplete,
                'phone_verified' => $phoneVerified,
                'vip_limit_passed' => $vipLimitPassed,
            ]);

            // Log action
            $this->logAction('withdrawal_request_created', $user, $withdrawal);

            return $withdrawal;
        });
    }

    /**
     * Validate withdrawal eligibility
     */
    private function validateWithdrawalEligibility(User $user, float $amount): void
    {
        // Check user status
        if (!$user->isActive()) {
            throw new \Exception('Account is not active');
        }

        // Guest users cannot withdraw
        if ($user->auth_method === 'guest') {
            throw new \Exception('Guest accounts must upgrade before withdrawing');
        }

        // Check minimum withdrawal
        $paymentMethod = \App\Models\PaymentMethod::where('code', 'gcash')
            ->where('is_enabled', true)
            ->firstOrFail();

        if (!$paymentMethod->validateWithdrawalAmount($amount)) {
            throw new \Exception("Amount must be between {$paymentMethod->min_withdrawal} and {$paymentMethod->max_withdrawal}");
        }

        // Check available balance
        $wallet = $user->wallet;
        if (!$wallet->hasRealBalance($amount)) {
            throw new \Exception('Insufficient real balance');
        }
    }

    /**
     * Check if user has completed wagering requirements
     */
    private function checkWageringRequirements(User $user): bool
    {
        // Check if user has any active bonuses
        $activeBonus = Bonus::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$activeBonus) {
            return true; // No active bonus, wagering complete
        }

        // Check if wagering requirement is met
        return $activeBonus->wagering_progress >= $activeBonus->wagering_requirement;
    }

    /**
     * Check VIP withdrawal limits
     */
    private function checkVipLimits(User $user, float $amount): bool
    {
        $vipLevel = $user->vipLevel;

        // Check daily limit
        $todayWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('processed_at', today())
            ->sum('amount');

        if (($todayWithdrawals + $amount) > $vipLevel->withdrawal_limit_daily) {
            return false;
        }

        // Check weekly limit
        $weekWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('processed_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('amount');

        if (($weekWithdrawals + $amount) > $vipLevel->withdrawal_limit_weekly) {
            return false;
        }

        // Check monthly limit
        $monthWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereMonth('processed_at', now()->month)
            ->sum('amount');

        if (($monthWithdrawals + $amount) > $vipLevel->withdrawal_limit_monthly) {
            return false;
        }

        return true;
    }

    /**
     * Approve withdrawal (admin action)
     */
    public function approveWithdrawal(int $withdrawalId, int $adminUserId, string $gcashReference, ?string $adminNotes = null): Withdrawal
    {
        $withdrawal = Withdrawal::findOrFail($withdrawalId);

        if (!$withdrawal->isPending()) {
            throw new \Exception('Withdrawal is not in pending status');
        }

        if (!$withdrawal->canProcess()) {
            throw new \Exception('Withdrawal failed validation checks');
        }

        DB::transaction(function () use ($withdrawal, $adminUserId, $gcashReference, $adminNotes) {
            $user = $withdrawal->user;

            // Update withdrawal status
            $withdrawal->update([
                'status' => 'approved',
                'processed_by' => $adminUserId,
                'processed_at' => now(),
                'admin_notes' => $adminNotes,
                'gcash_reference' => $gcashReference,
            ]);

            // Deduct from wallet (already locked)
            $this->walletService->debitRealBalance(
                $user,
                $withdrawal->amount,
                'withdrawal',
                "Withdrawal approved - GCash: {$withdrawal->gcash_number}",
                Withdrawal::class,
                $withdrawal->id
            );

            // Unlock the balance (deducted from real, remove from locked)
            $wallet = $user->wallet;
            $wallet->decrement('locked_balance', $withdrawal->amount);

            // Update wallet lifetime stats
            $wallet->increment('lifetime_withdrawn', $withdrawal->amount);

            // Update user total withdrawn
            $user->increment('total_withdrawn', $withdrawal->amount);

            // If user had active bonus and wagering complete, convert bonus to real
            $activeBonus = Bonus::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($activeBonus && $activeBonus->wagering_progress >= $activeBonus->wagering_requirement) {
                $bonusAmount = $wallet->bonus_balance;
                if ($bonusAmount > 0) {
                    $this->walletService->convertBonusToReal($user, $bonusAmount);
                }
                $activeBonus->update(['status' => 'completed', 'completed_at' => now()]);
            }

            // Send notification
            $this->notificationService->notifyWithdrawalApproved(
                $user,
                $withdrawal->amount,
                $withdrawal->gcash_number
            );

            // Log action
            $this->logAction('withdrawal_approved', $user, $withdrawal, $adminUserId);
        });

        return $withdrawal->fresh();
    }

    /**
     * Reject withdrawal (admin action)
     */
    public function rejectWithdrawal(int $withdrawalId, int $adminUserId, string $rejectedReason): Withdrawall
    {
        $withdrawal = Withdrawal::findOrFail($withdrawalId);

        if (!$withdrawal->isPending()) {
            throw new \Exception('Withdrawal is not in pending status');
        }

        DB::transaction(function () use ($withdrawal, $adminUserId, $rejectedReason) {
            $user = $withdrawal->user;

            // Update withdrawal status
            $withdrawal->update([
                'status' => 'rejected',
                'processed_by' => $adminUserId,
                'processed_at' => now(),
                'rejected_reason' => $rejectedReason,
            ]);

            // Unlock the balance
            $this->walletService->unlockBalance($user, $withdrawal->amount);

            // Send notification
            $this->notificationService->notifyWithdrawalRejected(
                $user,
                $withdrawal->amount,
                $rejectedReason
            );

            // Log action
            $this->logAction('withdrawal_rejected', $user, $withdrawal, $adminUserId);
        });

        return $withdrawal->fresh();
    }

    /**
     * Get user withdrawal history
     */
    public function getUserWithdrawalHistory(User $user, int $perPage = 20)
    {
        return $user->withdrawals()
            ->with('processedBy')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get pending withdrawals (admin)
     */
    public function getPendingWithdrawals(int $perPage = 50)
    {
        return Withdrawal::where('status', 'pending')
            ->with(['user', 'user.vipLevel'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Get withdrawal statistics
     */
    public function getWithdrawalStatistics(?string $period = 'today'): array
    {
        $query = Withdrawal::where('status', 'approved');

        switch ($period) {
            case 'today':
                $query->whereDate('processed_at', today());
                break;
            case 'week':
                $query->whereBetween('processed_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('processed_at', now()->month);
                break;
        }

        $total = $query->sum('amount');
        $count = $query->count();
        $average = $count > 0 ? $total / $count : 0;

        return [
            'total_amount' => (float) $total,
            'total_count' => $count,
            'average_amount' => (float) $average,
            'pending_count' => Withdrawal::where('status', 'pending')->count(),
        ];
    }

    /**
     * Log withdrawal actions
     */
    private function logAction(string $action, User $user, Withdrawal $withdrawal, ?int $adminUserId = null): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'auditable_type' => Withdrawal::class,
            'auditable_id' => $withdrawal->id,
            'new_values' => [
                'amount' => $withdrawal->amount,
                'status' => $withdrawal->status,
                'gcash_number' => $withdrawal->gcash_number,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
