<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\AuditLog;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private DepositService $depositService,
        private WithdrawalService $withdrawalService
    ) {}

    /**
     * Get pending deposits
     */
    public function getPendingDeposits(Request $request): JsonResponse
    {
        $deposits = Deposit::with(['user', 'gcashAccount'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'deposits' => $deposits->items(),
            'pagination' => [
                'total' => $deposits->total(),
                'per_page' => $deposits->perPage(),
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
            ]
        ]);
    }

    /**
     * Get pending withdrawals
     */
    public function getPendingWithdrawals(Request $request): JsonResponse
    {
        $withdrawals = Withdrawal::with(['user', 'user.wallet'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'withdrawals' => $withdrawals->items(),
            'pagination' => [
                'total' => $withdrawals->total(),
                'per_page' => $withdrawals->perPage(),
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
            ]
        ]);
    }

    /**
     * Get deposit details
     */
    public function getDepositDetails(int $depositId): JsonResponse
    {
        $deposit = Deposit::with([
            'user',
            'user.wallet',
            'user.vipLevel',
            'gcashAccount',
            'processedBy'
        ])->findOrFail($depositId);

        // Get user's deposit history
        $depositHistory = Deposit::where('user_id', $deposit->user_id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get related audit logs
        $auditLogs = AuditLog::where('model_type', Deposit::class)
            ->where('model_id', $depositId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'deposit' => $deposit,
            'deposit_history' => $depositHistory,
            'audit_logs' => $auditLogs,
            'user_stats' => [
                'total_deposits' => Deposit::where('user_id', $deposit->user_id)
                    ->where('status', 'completed')
                    ->count(),
                'total_deposited' => Deposit::where('user_id', $deposit->user_id)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'total_withdrawn' => Withdrawal::where('user_id', $deposit->user_id)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'pending_withdrawals' => Withdrawal::where('user_id', $deposit->user_id)
                    ->where('status', 'pending')
                    ->sum('amount'),
            ]
        ]);
    }

    /**
     * Get withdrawal details
     */
    public function getWithdrawalDetails(int $withdrawalId): JsonResponse
    {
        $withdrawal = Withdrawal::with([
            'user',
            'user.wallet',
            'user.vipLevel',
            'processedBy'
        ])->findOrFail($withdrawalId);

        // Get user's withdrawal history
        $withdrawalHistory = Withdrawal::where('user_id', $withdrawal->user_id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get related audit logs
        $auditLogs = AuditLog::where('model_type', Withdrawal::class)
            ->where('model_id', $withdrawalId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'withdrawal' => $withdrawal,
            'withdrawal_history' => $withdrawalHistory,
            'audit_logs' => $auditLogs,
            'user_stats' => [
                'total_withdrawals' => Withdrawal::where('user_id', $withdrawal->user_id)
                    ->where('status', 'completed')
                    ->count(),
                'total_deposited' => Deposit::where('user_id', $withdrawal->user_id)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'total_withdrawn' => Withdrawal::where('user_id', $withdrawal->user_id)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'current_balance' => $withdrawal->user->wallet->real_balance ?? 0,
                'locked_balance' => $withdrawal->user->wallet->locked_balance ?? 0,
            ]
        ]);
    }

    /**
     * Approve deposit
     */
    public function approveDeposit(Request $request, int $depositId): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $deposit = $this->depositService->approveDeposit(
                $depositId,
                $request->user()->id,
                $request->input('admin_notes')
            );

            return response()->json([
                'message' => 'Deposit approved successfully',
                'deposit' => $deposit
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve deposit',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reject deposit
     */
    public function rejectDeposit(Request $request, int $depositId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $deposit = $this->depositService->rejectDeposit(
                $depositId,
                $request->user()->id,
                $request->input('reason')
            );

            return response()->json([
                'message' => 'Deposit rejected',
                'deposit' => $deposit
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject deposit',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Approve withdrawal
     */
    public function approveWithdrawal(Request $request, int $withdrawalId): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:500',
            'gcash_reference' => 'required|string|max:100',
        ]);

        try {
            $withdrawal = $this->withdrawalService->approveWithdrawal(
                $withdrawalId,
                $request->user()->id,
                $request->input('gcash_reference'),
                $request->input('admin_notes')
            );

            return response()->json([
                'message' => 'Withdrawal approved successfully',
                'withdrawal' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve withdrawal',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reject withdrawal
     */
    public function rejectWithdrawal(Request $request, int $withdrawalId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $withdrawal = $this->withdrawalService->rejectWithdrawal(
                $withdrawalId,
                $request->user()->id,
                $request->input('reason')
            );

            return response()->json([
                'message' => 'Withdrawal rejected',
                'withdrawal' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject withdrawal',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today'); // today, week, month, all

        $dateFilter = match($period) {
            'today' => ['created_at', '>=', now()->startOfDay()],
            'week' => ['created_at', '>=', now()->startOfWeek()],
            'month' => ['created_at', '>=', now()->startOfMonth()],
            default => null,
        };

        $deposits = Deposit::query();
        $withdrawals = Withdrawal::query();

        if ($dateFilter) {
            $deposits->where(...$dateFilter);
            $withdrawals->where(...$dateFilter);
        }

        return response()->json([
            'deposits' => [
                'pending' => (clone $deposits)->where('status', 'pending')->count(),
                'approved' => (clone $deposits)->where('status', 'completed')->count(),
                'rejected' => (clone $deposits)->where('status', 'rejected')->count(),
                'total_amount' => (clone $deposits)->where('status', 'completed')->sum('amount'),
                'pending_amount' => (clone $deposits)->where('status', 'pending')->sum('amount'),
            ],
            'withdrawals' => [
                'pending' => (clone $withdrawals)->where('status', 'pending')->count(),
                'approved' => (clone $withdrawals)->where('status', 'completed')->count(),
                'rejected' => (clone $withdrawals)->where('status', 'rejected')->count(),
                'total_amount' => (clone $withdrawals)->where('status', 'completed')->sum('amount'),
                'pending_amount' => (clone $withdrawals)->where('status', 'pending')->sum('amount'),
            ],
            'period' => $period,
        ]);
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all'); // all, deposit, withdrawal
        $status = $request->input('status', 'all'); // all, pending, completed, rejected
        $search = $request->input('search'); // user phone/ID, reference number

        $deposits = collect();
        $withdrawals = collect();

        if ($type === 'all' || $type === 'deposit') {
            $depositQuery = Deposit::with(['user', 'processedBy'])
                ->orderBy('created_at', 'desc');

            if ($status !== 'all') {
                $depositQuery->where('status', $status);
            }

            if ($search) {
                $depositQuery->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('phone_number', 'like', "%{$search}%")
                             ->orWhere('id', $search);
                      });
                });
            }

            $deposits = $depositQuery->paginate(10, ['*'], 'deposit_page');
        }

        if ($type === 'all' || $type === 'withdrawal') {
            $withdrawalQuery = Withdrawal::with(['user', 'processedBy'])
                ->orderBy('created_at', 'desc');

            if ($status !== 'all') {
                $withdrawalQuery->where('status', $status);
            }

            if ($search) {
                $withdrawalQuery->where(function ($q) use ($search) {
                    $q->where('gcash_reference', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('phone_number', 'like', "%{$search}%")
                             ->orWhere('id', $search);
                      });
                });
            }

            $withdrawals = $withdrawalQuery->paginate(10, ['*'], 'withdrawal_page');
        }

        return response()->json([
            'deposits' => $type === 'withdrawal' ? [] : [
                'data' => $deposits->items(),
                'pagination' => [
                    'total' => $deposits->total(),
                    'per_page' => $deposits->perPage(),
                    'current_page' => $deposits->currentPage(),
                    'last_page' => $deposits->lastPage(),
                ]
            ],
            'withdrawals' => $type === 'deposit' ? [] : [
                'data' => $withdrawals->items(),
                'pagination' => [
                    'total' => $withdrawals->total(),
                    'per_page' => $withdrawals->perPage(),
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                ]
            ],
        ]);
    }
}
