# Payment System - Manual GCash Implementation

## Overview

Complete implementation guide for the manual GCash payment system, including deposit and withdrawal flows with admin approval.

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    PAYMENT SYSTEM OVERVIEW                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  USER                    SYSTEM                    ADMIN         │
│                                                                   │
│  Deposit Request  ──→  Pending Queue  ──→  Manual Verification  │
│                                                ↓                  │
│  Balance Updated  ←──  Wallet Credit  ←──  Approve/Reject       │
│                                                                   │
│  Withdrawal Req   ──→  Validation     ──→  Pending Queue        │
│                        • Wagering                ↓               │
│                        • VIP Limits     Manual Review            │
│                        • Phone Check             ↓               │
│  Notification     ←──  Update Status  ←──  Send GCash + Confirm │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 1. Deposit System

### 1.1 Deposit Flow Diagram

```
USER ACTIONS                 SYSTEM LOGIC                  ADMIN ACTIONS
     │                            │                              │
     │ 1. View deposit page       │                              │
     ├───────────────────────────>│                              │
     │                            │                              │
     │ 2. Display GCash accounts  │                              │
     │<───────────────────────────┤                              │
     │                            │                              │
     │ 3. Send money via GCash    │                              │
     │    (outside platform)      │                              │
     │                            │                              │
     │ 4. Submit deposit form     │                              │
     │    • Amount                │                              │
     │    • Reference number      │                              │
     │    • Screenshot            │                              │
     ├───────────────────────────>│                              │
     │                            │                              │
     │                            │ 5. Validate input            │
     │                            │    • Amount limits           │
     │                            │    • Required fields         │
     │                            │    • File upload             │
     │                            │                              │
     │                            │ 6. Create deposit record     │
     │                            │    Status: PENDING           │
     │                            │                              │
     │                            │ 7. Send notification         │
     │                            ├──────────────────────────────>│
     │                            │                              │
     │ 8. Confirmation message    │                              │
     │<───────────────────────────┤                              │
     │                            │                              │
     │                            │              9. Review       │
     │                            │                  deposit     │
     │                            │                              │
     │                            │              10. Verify      │
     │                            │                   GCash txn  │
     │                            │                              │
     │                            │ 11. Admin approves           │
     │                            │<──────────────────────────────┤
     │                            │                              │
     │                            │ 12. Start transaction        │
     │                            │     Lock wallet              │
     │                            │     Credit real_balance      │
     │                            │     Create transaction log   │
     │                            │     Update deposit status    │
     │                            │     Create audit log         │
     │                            │     Commit transaction       │
     │                            │                              │
     │                            │ 13. Send notification        │
     │                            ├──────────────────────────────>│
     │                            │                              │
     │ 14. Balance updated        │                              │
     │     notification           │                              │
     │<───────────────────────────┤                              │
```

### 1.2 Deposit Implementation

#### Controller

```php
<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\DepositService;
use App\Http\Requests\Payment\DepositRequest;
use Illuminate\Http\JsonResponse;

class DepositController extends Controller
{
    public function __construct(
        private DepositService $depositService
    ) {}

    /**
     * Get available GCash accounts
     */
    public function getGCashAccounts(): JsonResponse
    {
        $accounts = $this->depositService->getActiveGCashAccounts();
        
        return response()->json([
            'success' => true,
            'data' => $accounts
        ]);
    }

    /**
     * Submit deposit request
     */
    public function submit(DepositRequest $request): JsonResponse
    {
        try {
            $deposit = $this->depositService->createDeposit(
                userId: auth()->id(),
                amount: $request->input('amount'),
                gcashAccountId: $request->input('gcash_account_id'),
                referenceNumber: $request->input('reference_number'),
                screenshot: $request->file('screenshot'),
                ipAddress: $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => 'Deposit request submitted successfully',
                'data' => $deposit
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get user's deposit history
     */
    public function history(): JsonResponse
    {
        $deposits = $this->depositService->getUserDeposits(auth()->id());
        
        return response()->json([
            'success' => true,
            'data' => $deposits
        ]);
    }
}
```

#### Service Layer

```php
<?php

namespace App\Services\Payment;

use App\Models\Deposit;
use App\Models\GCashAccount;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DepositService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private NotificationService $notificationService
    ) {}

    /**
     * Get active GCash accounts
     */
    public function getActiveGCashAccounts()
    {
        return GCashAccount::where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'account_name', 'account_number'])
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->account_name,
                    'number' => $this->maskAccountNumber($account->account_number)
                ];
            });
    }

    /**
     * Create deposit request
     */
    public function createDeposit(
        int $userId,
        float $amount,
        int $gcashAccountId,
        string $referenceNumber,
        $screenshot,
        string $ipAddress
    ): Deposit {
        // Validate amount limits
        $paymentMethod = PaymentMethod::where('code', 'gcash')->first();
        
        if ($amount < $paymentMethod->min_deposit || $amount > $paymentMethod->max_deposit) {
            throw new \Exception("Amount must be between {$paymentMethod->min_deposit} and {$paymentMethod->max_deposit}");
        }

        // Upload screenshot
        $screenshotPath = null;
        if ($screenshot) {
            $screenshotPath = $screenshot->store('deposits', 'private');
        }

        // Create deposit record
        $deposit = DB::transaction(function () use (
            $userId,
            $amount,
            $gcashAccountId,
            $referenceNumber,
            $screenshotPath,
            $ipAddress
        ) {
            $deposit = Deposit::create([
                'uuid' => Str::uuid(),
                'user_id' => $userId,
                'amount' => $amount,
                'payment_method' => 'gcash',
                'gcash_account_id' => $gcashAccountId,
                'reference_number' => $referenceNumber,
                'screenshot_url' => $screenshotPath,
                'status' => 'pending'
            ]);

            // Create audit log
            $this->auditLogService->log(
                action: 'deposit_requested',
                userId: $userId,
                resourceType: 'deposit',
                resourceId: $deposit->id,
                ipAddress: $ipAddress,
                metadata: [
                    'amount' => $amount,
                    'reference' => $referenceNumber
                ]
            );

            return $deposit;
        });

        // Notify admins
        $this->notificationService->notifyAdmins('new_deposit', $deposit);

        return $deposit;
    }

    /**
     * Admin approves deposit
     */
    public function approveDeposit(
        int $depositId,
        int $adminId,
        string $adminNotes = null,
        string $ipAddress
    ): Deposit {
        return DB::transaction(function () use ($depositId, $adminId, $adminNotes, $ipAddress) {
            // Lock and get deposit
            $deposit = Deposit::where('id', $depositId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            // Lock wallet
            $wallet = Wallet::where('user_id', $deposit->user_id)
                ->lockForUpdate()
                ->first();

            // Credit wallet
            $balanceBefore = $wallet->real_balance;
            $wallet->increment('real_balance', $deposit->amount);
            $wallet->increment('lifetime_deposits', $deposit->amount);

            // Create transaction record
            Transaction::create([
                'uuid' => Str::uuid(),
                'user_id' => $deposit->user_id,
                'type' => 'deposit',
                'amount' => $deposit->amount,
                'balance_type' => 'real',
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->real_balance,
                'status' => 'completed',
                'reference_type' => 'deposit',
                'reference_id' => $deposit->id,
                'description' => "GCash deposit - Ref: {$deposit->reference_number}"
            ]);

            // Update deposit status
            $deposit->update([
                'status' => 'approved',
                'admin_id' => $adminId,
                'admin_notes' => $adminNotes,
                'processed_at' => now()
            ]);

            // Create audit log
            $this->auditLogService->log(
                action: 'deposit_approved',
                userId: null,
                adminId: $adminId,
                resourceType: 'deposit',
                resourceId: $deposit->id,
                ipAddress: $ipAddress,
                metadata: [
                    'amount' => $deposit->amount,
                    'user_id' => $deposit->user_id
                ]
            );

            return $deposit;
        });

        // Notify user
        $this->notificationService->notifyUser(
            $deposit->user_id,
            'deposit_approved',
            $deposit
        );

        return $deposit;
    }

    /**
     * Admin rejects deposit
     */
    public function rejectDeposit(
        int $depositId,
        int $adminId,
        string $reason,
        string $ipAddress
    ): Deposit {
        $deposit = Deposit::findOrFail($depositId);

        if ($deposit->status !== 'pending') {
            throw new \Exception('Can only reject pending deposits');
        }

        $deposit->update([
            'status' => 'rejected',
            'admin_id' => $adminId,
            'admin_notes' => $reason,
            'processed_at' => now()
        ]);

        // Create audit log
        $this->auditLogService->log(
            action: 'deposit_rejected',
            userId: null,
            adminId: $adminId,
            resourceType: 'deposit',
            resourceId: $deposit->id,
            ipAddress: $ipAddress,
            metadata: [
                'reason' => $reason,
                'user_id' => $deposit->user_id
            ]
        );

        // Notify user
        $this->notificationService->notifyUser(
            $deposit->user_id,
            'deposit_rejected',
            $deposit
        );

        return $deposit;
    }

    /**
     * Mask account number for security
     */
    private function maskAccountNumber(string $number): string
    {
        return substr($number, 0, 4) . '****' . substr($number, -3);
    }
}
```

---

## 2. Withdrawal System

### 2.1 Withdrawal Flow

```
USER                        SYSTEM                          ADMIN
  │                           │                               │
  │ 1. Request withdrawal     │                               │
  ├──────────────────────────>│                               │
  │                           │                               │
  │                           │ 2. Pre-validation checks      │
  │                           │    ✓ Is guest? → Require upgrade
  │                           │    ✓ Phone verified?          │
  │                           │    ✓ Wagering complete?       │
  │                           │    ✓ VIP limits OK?           │
  │                           │    ✓ Sufficient balance?      │
  │                           │                               │
  │ 3. Validation result      │                               │
  │<──────────────────────────┤                               │
  │                           │                               │
  │ 4. Confirm withdrawal     │                               │
  │    (GCash number)         │                               │
  ├──────────────────────────>│                               │
  │                           │                               │
  │                           │ 5. Lock funds                 │
  │                           │    real_balance → locked_balance
  │                           │                               │
  │                           │ 6. Create withdrawal record   │
  │                           │    Status: PENDING            │
  │                           │                               │
  │                           │ 7. Notify admins              │
  │                           ├───────────────────────────────>│
  │                           │                               │
  │ 8. Confirmation           │                               │
  │<──────────────────────────┤                               │
  │                           │                               │
  │                           │               9. Review       │
  │                           │                  withdrawal   │
  │                           │                               │
  │                           │               10. Send GCash  │
  │                           │                   manually    │
  │                           │                               │
  │                           │ 11. Confirm payment           │
  │                           │<───────────────────────────────┤
  │                           │                               │
  │                           │ 12. Process withdrawal        │
  │                           │     Deduct locked_balance     │
  │                           │     Create transaction        │
  │                           │     Update status: COMPLETED  │
  │                           │     Create audit log          │
  │                           │                               │
  │ 13. Notification          │                               │
  │<──────────────────────────┤                               │
```

### 2.2 Withdrawal Implementation

#### Service Layer

```php
<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\Transaction;
use App\Models\Bonus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WithdrawalService
{
    /**
     * Request withdrawal
     */
    public function requestWithdrawal(
        int $userId,
        float $amount,
        string $gcashNumber,
        string $gcashName,
        string $ipAddress
    ): Withdrawal {
        // Run all pre-validation checks
        $this->validateWithdrawal($userId, $amount);

        return DB::transaction(function () use (
            $userId,
            $amount,
            $gcashNumber,
            $gcashName,
            $ipAddress
        ) {
            // Lock wallet
            $wallet = Wallet::where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            // Transfer from real_balance to locked_balance
            if ($wallet->real_balance < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $wallet->decrement('real_balance', $amount);
            $wallet->increment('locked_balance', $amount);

            // Create withdrawal record
            $withdrawal = Withdrawal::create([
                'uuid' => Str::uuid(),
                'user_id' => $userId,
                'amount' => $amount,
                'payment_method' => 'gcash',
                'gcash_number' => $gcashNumber,
                'gcash_name' => $gcashName,
                'status' => 'pending',
                'wagering_complete' => true,
                'phone_verified' => User::find($userId)->is_phone_verified,
                'vip_limit_passed' => true
            ]);

            // Create audit log
            $this->auditLogService->log(
                action: 'withdrawal_requested',
                userId: $userId,
                resourceType: 'withdrawal',
                resourceId: $withdrawal->id,
                ipAddress: $ipAddress,
                metadata: [
                    'amount' => $amount,
                    'gcash_number' => $this->maskGCashNumber($gcashNumber)
                ]
            );

            return $withdrawal;
        });

        // Notify admins
        $this->notificationService->notifyAdmins('new_withdrawal', $withdrawal);

        return $withdrawal;
    }

    /**
     * Validate withdrawal eligibility
     */
    public function validateWithdrawal(int $userId, float $amount): void
    {
        $user = User::with('wallet', 'vipLevel')->findOrFail($userId);

        // Check 1: Guest must upgrade
        if ($user->is_guest) {
            throw new \Exception('Guest users must upgrade account before withdrawal');
        }

        // Check 2: Phone verification
        if (!$user->is_phone_verified) {
            throw new \Exception('Phone number must be verified');
        }

        // Check 3: Wagering requirements
        if (!$this->isWageringComplete($userId)) {
            throw new \Exception('Wagering requirements not completed');
        }

        // Check 4: VIP limits
        $dailyLimit = $user->vipLevel->withdrawal_limit_daily;
        $todayWithdrawn = Withdrawal::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('amount');

        if ($todayWithdrawn + $amount > $dailyLimit) {
            throw new \Exception("Daily withdrawal limit exceeded. Limit: {$dailyLimit}");
        }

        // Check 5: Minimum withdrawal amount
        $paymentMethod = PaymentMethod::where('code', 'gcash')->first();
        if ($amount < $paymentMethod->min_withdrawal) {
            throw new \Exception("Minimum withdrawal amount is {$paymentMethod->min_withdrawal}");
        }

        // Check 6: Maximum withdrawal amount
        if ($amount > $paymentMethod->max_withdrawal) {
            throw new \Exception("Maximum withdrawal amount is {$paymentMethod->max_withdrawal}");
        }

        // Check 7: Sufficient balance
        if ($user->wallet->real_balance < $amount) {
            throw new \Exception('Insufficient balance');
        }
    }

    /**
     * Check if wagering requirements are complete
     */
    private function isWageringComplete(int $userId): bool
    {
        // Check if user has active bonuses with incomplete wagering
        $activeBonuses = Bonus::where('user_id', $userId)
            ->where('status', 'active')
            ->where('bonus_balance', '>', 0)
            ->exists();

        if ($activeBonuses) {
            return false;
        }

        return true;
    }

    /**
     * Admin approves withdrawal
     */
    public function approveWithdrawal(
        int $withdrawalId,
        int $adminId,
        string $adminNotes = null,
        string $ipAddress
    ): Withdrawal {
        return DB::transaction(function () use (
            $withdrawalId,
            $adminId,
            $adminNotes,
            $ipAddress
        ) {
            // Lock and get withdrawal
            $withdrawal = Withdrawal::where('id', $withdrawalId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            // Lock wallet
            $wallet = Wallet::where('user_id', $withdrawal->user_id)
                ->lockForUpdate()
                ->first();

            // Deduct from locked balance
            $wallet->decrement('locked_balance', $withdrawal->amount);
            $wallet->increment('lifetime_withdrawals', $withdrawal->amount);

            // Create transaction record
            Transaction::create([
                'uuid' => Str::uuid(),
                'user_id' => $withdrawal->user_id,
                'type' => 'withdrawal',
                'amount' => $withdrawal->amount,
                'balance_type' => 'real',
                'balance_before' => $wallet->real_balance + $withdrawal->amount,
                'balance_after' => $wallet->real_balance,
                'status' => 'completed',
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id,
                'description' => "GCash withdrawal to {$withdrawal->gcash_number}"
            ]);

            // Update withdrawal status
            $withdrawal->update([
                'status' => 'completed',
                'admin_id' => $adminId,
                'admin_notes' => $adminNotes,
                'processed_at' => now()
            ]);

            // Create audit log
            $this->auditLogService->log(
                action: 'withdrawal_approved',
                userId: null,
                adminId: $adminId,
                resourceType: 'withdrawal',
                resourceId: $withdrawal->id,
                ipAddress: $ipAddress,
                metadata: [
                    'amount' => $withdrawal->amount,
                    'user_id' => $withdrawal->user_id,
                    'gcash_number' => $this->maskGCashNumber($withdrawal->gcash_number)
                ]
            );

            return $withdrawal;
        });

        // Notify user
        $this->notificationService->notifyUser(
            $withdrawal->user_id,
            'withdrawal_completed',
            $withdrawal
        );

        return $withdrawal;
    }

    /**
     * Admin rejects withdrawal
     */
    public function rejectWithdrawal(
        int $withdrawalId,
        int $adminId,
        string $reason,
        string $ipAddress
    ): Withdrawal {
        return DB::transaction(function () use (
            $withdrawalId,
            $adminId,
            $reason,
            $ipAddress
        ) {
            // Lock and get withdrawal
            $withdrawal = Withdrawal::where('id', $withdrawalId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            // Lock wallet
            $wallet = Wallet::where('user_id', $withdrawal->user_id)
                ->lockForUpdate()
                ->first();

            // Return locked balance to real balance
            $wallet->decrement('locked_balance', $withdrawal->amount);
            $wallet->increment('real_balance', $withdrawal->amount);

            // Update withdrawal status
            $withdrawal->update([
                'status' => 'rejected',
                'admin_id' => $adminId,
                'admin_notes' => $reason,
                'processed_at' => now()
            ]);

            // Create audit log
            $this->auditLogService->log(
                action: 'withdrawal_rejected',
                userId: null,
                adminId: $adminId,
                resourceType: 'withdrawal',
                resourceId: $withdrawal->id,
                ipAddress: $ipAddress,
                metadata: [
                    'reason' => $reason,
                    'user_id' => $withdrawal->user_id
                ]
            ]);

            return $withdrawal;
        });

        // Notify user
        $this->notificationService->notifyUser(
            $withdrawal->user_id,
            'withdrawal_rejected',
            $withdrawal
        );

        return $withdrawal;
    }

    /**
     * Mask GCash number for security
     */
    private function maskGCashNumber(string $number): string
    {
        return substr($number, 0, 4) . '****' . substr($number, -2);
    }
}
```

---

## 3. Admin Panel Routes

```php
// Admin deposit management
Route::prefix('admin')->middleware(['auth:admin', 'ip.whitelist'])->group(function () {
    Route::get('/deposits/pending', [AdminDepositController::class, 'pending']);
    Route::post('/deposits/{id}/approve', [AdminDepositController::class, 'approve']);
    Route::post('/deposits/{id}/reject', [AdminDepositController::class, 'reject']);
    
    Route::get('/withdrawals/pending', [AdminWithdrawalController::class, 'pending']);
    Route::post('/withdrawals/{id}/approve', [AdminWithdrawalController::class, 'approve']);
    Route::post('/withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject']);
});
```

---

## 4. Security Measures

### Anti-Fraud Protection

```php
// Check for duplicate deposits
$recentDeposit = Deposit::where('user_id', $userId)
    ->where('reference_number', $referenceNumber)
    ->where('created_at', '>', now()->subHours(24))
    ->exists();

if ($recentDeposit) {
    throw new \Exception('Duplicate deposit detected');
}

// Rate limiting
$recentDepositCount = Deposit::where('user_id', $userId)
    ->where('created_at', '>', now()->subHour())
    ->count();

if ($recentDepositCount >= 5) {
    throw new \Exception('Too many deposit requests. Please try again later.');
}
```

---

**Last Updated**: December 21, 2025
