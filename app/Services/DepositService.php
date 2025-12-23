<?php

namespace App\Services;

use App\Models\User;
use App\Models\Deposit;
use App\Models\GcashAccount;
use App\Models\AuditLog;
use App\Services\WalletService;
use App\Services\NotificationService;
use App\Services\BonusService;
use App\Services\ReferralService;
use App\Services\FinancialMonitoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DepositService
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService,
        private BonusService $bonusService,
        private ReferralService $referralService,
        private FinancialMonitoringService $monitoringService
    ) {}

    /**
     * Get available GCash accounts for deposits
     */
    public function getAvailableGcashAccounts(): array
    {
        $accounts = GcashAccount::where('is_active', true)
            ->whereRaw('current_daily_amount < daily_limit OR daily_limit = 0')
            ->orderBy('sort_order')
            ->get();

        return $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'account_name' => $account->account_name,
                'account_number' => $account->account_number,
                'qr_code_url' => $account->qr_code_url,
                'remaining_limit' => $account->getRemainingDailyLimit(),
            ];
        })->toArray();
    }

    /**
     * Create deposit request
     */
    public function createDepositRequest(
        User $user,
        int $gcashAccountId,
        float $amount,
        string $referenceNumber,
        $screenshotFile,
        ?string $notes = null
    ): Deposit {
        // Validate amount against payment method limits
        $paymentMethod = \App\Models\PaymentMethod::where('code', 'gcash')
            ->where('is_enabled', true)
            ->firstOrFail();

        if (!$paymentMethod->validateDepositAmount($amount)) {
            throw new \Exception("Amount must be between {$paymentMethod->min_deposit} and {$paymentMethod->max_deposit}");
        }

        // Validate GCash account
        $gcashAccount = GcashAccount::where('id', $gcashAccountId)
            ->where('is_active', true)
            ->firstOrFail();

        // Check if daily limit would be exceeded
        $remainingLimit = $gcashAccount->getRemainingDailyLimit();
        if ($remainingLimit < $amount) {
            throw new \Exception('This deposit would exceed the daily limit for this GCash account');
        }

        // Check for duplicate reference number
        $existingDeposit = Deposit::where('reference_number', $referenceNumber)
            ->where('status', '!=', 'cancelled')
            ->first();
        
        if ($existingDeposit) {
            throw new \Exception('This reference number has already been used');
        }

        return DB::transaction(function () use ($user, $gcashAccountId, $amount, $referenceNumber, $screenshotFile, $notes) {
            // Upload screenshot
            $screenshotPath = null;
            if ($screenshotFile) {
                $screenshotPath = $screenshotFile->store('deposits', 'public');
            }

            // Create deposit record
            $deposit = Deposit::create([
                'user_id' => $user->id,
                'gcash_account_id' => $gcashAccountId,
                'amount' => $amount,
                'reference_number' => $referenceNumber,
                'screenshot_url' => $screenshotPath,
                'status' => 'pending',
            ]);

            // Log action
            $this->logAction('deposit_request_created', $user, $deposit);
            
            // Log to financial monitoring
            $this->monitoringService->logFinancialTransaction(
                'deposit_request',
                $user,
                $amount,
                [
                    'reference_number' => $referenceNumber,
                    'gcash_account_id' => $gcashAccountId,
                    'has_screenshot' => !is_null($screenshotPath),
                ],
                $deposit->id
            );

            return $deposit;
        });
    }

    /**
     * Approve deposit (admin action)
     */
    public function approveDeposit(int $depositId, int $adminUserId, ?string $adminNotes = null): Deposit
    {
        $deposit = Deposit::findOrFail($depositId);

        if (!$deposit->isPending()) {
            throw new \Exception('Deposit is not in pending status');
        }

        DB::transaction(function () use ($deposit, $adminUserId, $adminNotes) {
            $user = $deposit->user;

            // Update deposit status
            $deposit->update([
                'status' => 'approved',
                'processed_by' => $adminUserId,
                'processed_at' => now(),
                'admin_notes' => $adminNotes,
            ]);

            // Credit user wallet
            $this->walletService->creditRealBalance(
                $user,
                $deposit->amount,
                'deposit',
                "Deposit approved - Ref: {$deposit->reference_number}",
                Deposit::class,
                $deposit->id
            );

            // Update wallet lifetime stats
            $wallet = $user->wallet;
            $wallet->increment('lifetime_deposited', $deposit->amount);

            // Update user total deposited
            $user->increment('total_deposited', $deposit->amount);

            // Update GCash account daily amount
            $deposit->gcashAccount->increment('current_daily_amount', $deposit->amount);

            // Award reload bonus for all deposits
            $this->bonusService->awardReloadBonus($user, $deposit->amount);

            // Check if this is user's first deposit for referral rewards
            $isFirstDeposit = $user->deposits()
                ->where('status', 'approved')
                ->where('id', '!=', $deposit->id)
                ->count() === 0;

            if ($isFirstDeposit) {
                // Process referral reward if applicable
                $this->referralService->processFirstDepositReferral($user, $deposit->amount);
            }

            // Send notification
            $this->notificationService->notifyDepositApproved(
                $user,
                $deposit->amount,
                $deposit->reference_number
            );

            // Log action
            $this->logAction('deposit_approved', $user, $deposit, $adminUserId);
            
            // Log to financial monitoring
            $this->monitoringService->logDepositApproval($deposit, $adminUserId, $adminNotes);
            $this->monitoringService->logFinancialTransaction(
                'deposit_approved',
                $user,
                $deposit->amount,
                [
                    'reference_number' => $deposit->reference_number,
                    'admin_user_id' => $adminUserId,
                    'admin_notes' => $adminNotes,
                    'is_first_deposit' => $isFirstDeposit,
                ],
                $deposit->id
            );
        });

        return $deposit->fresh();
    }

    /**
     * Reject deposit (admin action)
     */
    public function rejectDeposit(int $depositId, int $adminUserId, string $rejectedReason): Deposit
    {
        $deposit = Deposit::findOrFail($depositId);

        if (!$deposit->isPending()) {
            throw new \Exception('Deposit is not in pending status');
        }

        DB::transaction(function () use ($deposit, $adminUserId, $rejectedReason) {
            $deposit->update([
                'status' => 'rejected',
                'processed_by' => $adminUserId,
                'processed_at' => now(),
                'rejected_reason' => $rejectedReason,
            ]);

            // Send notification
            $this->notificationService->notifyDepositRejected(
                $deposit->user,
                $deposit->amount,
                $rejectedReason
            );

            // Log action
            $this->logAction('deposit_rejected', $deposit->user, $deposit, $adminUserId);
        });

        return $deposit->fresh();
    }

    /**
     * Get user deposit history
     */
    public function getUserDepositHistory(User $user, int $perPage = 20)
    {
        return $user->deposits()
            ->with(['gcashAccount', 'processedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get pending deposits (admin)
     */
    public function getPendingDeposits(int $perPage = 50)
    {
        return Deposit::where('status', 'pending')
            ->with(['user', 'gcashAccount'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Get deposit statistics
     */
    public function getDepositStatistics(?string $period = 'today'): array
    {
        $query = Deposit::where('status', 'approved');

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
            'pending_count' => Deposit::where('status', 'pending')->count(),
        ];
    }

    /**
     * Log deposit actions
     */
    private function logAction(string $action, User $user, Deposit $deposit, ?int $adminUserId = null): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'admin_user_id' => $adminUserId,
            'actor_type' => $adminUserId ? 'admin' : 'user',
            'actor_id' => $adminUserId ?? $user->id,
            'action' => $action,
            'auditable_type' => Deposit::class,
            'auditable_id' => $deposit->id,
            'new_values' => [
                'amount' => $deposit->amount,
                'status' => $deposit->status,
                'reference_number' => $deposit->reference_number,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
