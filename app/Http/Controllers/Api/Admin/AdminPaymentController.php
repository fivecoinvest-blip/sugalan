<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use App\Models\Deposit;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPaymentController extends Controller
{
    public function __construct(
        private DepositService $depositService,
        private WithdrawalService $withdrawalService
    ) {
        $this->middleware('auth:admin');
    }

    /**
     * Get pending deposits
     */
    public function getPendingDeposits(Request $request)
    {
        try {
            $this->checkPermission('manage_deposits');

            $perPage = $request->input('per_page', 50);
            $deposits = $this->depositService->getPendingDeposits($perPage);

            return response()->json([
                'success' => true,
                'data' => $deposits->map(function ($deposit) {
                    return [
                        'id' => $deposit->id,
                        'user' => [
                            'id' => $deposit->user->id,
                            'uuid' => $deposit->user->uuid,
                            'username' => $deposit->user->username,
                            'vip_level' => $deposit->user->vipLevel->name,
                        ],
                        'amount' => $deposit->amount,
                        'reference_number' => $deposit->reference_number,
                        'gcash_account' => [
                            'name' => $deposit->gcashAccount->account_name,
                            'number' => $deposit->gcashAccount->account_number,
                        ],
                        'screenshot_url' => $deposit->screenshot_url ? asset('storage/' . $deposit->screenshot_url) : null,
                        'notes' => $deposit->notes,
                        'created_at' => $deposit->created_at,
                    ];
                }),
                'pagination' => [
                    'total' => $deposits->total(),
                    'per_page' => $deposits->perPage(),
                    'current_page' => $deposits->currentPage(),
                    'last_page' => $deposits->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Approve deposit
     */
    public function approveDeposit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->checkPermission('manage_deposits');

            $deposit = Deposit::findOrFail($id);
            $admin = auth('admin')->user();

            $this->depositService->approveDeposit(
                $deposit,
                $admin->id,
                $request->admin_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Deposit approved successfully. User balance has been credited.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject deposit
     */
    public function rejectDeposit(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejected_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->checkPermission('manage_deposits');

            $deposit = Deposit::findOrFail($id);
            $admin = auth('admin')->user();

            $this->depositService->rejectDeposit(
                $deposit,
                $admin->id,
                $request->rejected_reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Deposit rejected successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get pending withdrawals
     */
    public function getPendingWithdrawals(Request $request)
    {
        try {
            $this->checkPermission('manage_withdrawals');

            $perPage = $request->input('per_page', 50);
            $withdrawals = $this->withdrawalService->getPendingWithdrawals($perPage);

            return response()->json([
                'success' => true,
                'data' => $withdrawals->map(function ($withdrawal) {
                    return [
                        'id' => $withdrawal->id,
                        'user' => [
                            'id' => $withdrawal->user->id,
                            'uuid' => $withdrawal->user->uuid,
                            'username' => $withdrawal->user->username,
                            'vip_level' => $withdrawal->user->vipLevel->name,
                            'total_deposited' => $withdrawal->user->total_deposited,
                            'total_wagered' => $withdrawal->user->total_wagered,
                        ],
                        'amount' => $withdrawal->amount,
                        'gcash_number' => $withdrawal->gcash_number,
                        'gcash_name' => $withdrawal->gcash_name,
                        'wagering_complete' => $withdrawal->wagering_complete,
                        'phone_verified' => $withdrawal->phone_verified,
                        'vip_limit_passed' => $withdrawal->vip_limit_passed,
                        'can_process' => $withdrawal->canProcess(),
                        'created_at' => $withdrawal->created_at,
                    ];
                }),
                'pagination' => [
                    'total' => $withdrawals->total(),
                    'per_page' => $withdrawals->perPage(),
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Approve withdrawal
     */
    public function approveWithdrawal(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->checkPermission('manage_withdrawals');

            $withdrawal = Withdrawal::findOrFail($id);
            $admin = auth('admin')->user();

            $this->withdrawalService->approveWithdrawal(
                $withdrawal,
                $admin->id,
                $request->admin_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal approved successfully. Please process GCash payment manually.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject withdrawal
     */
    public function rejectWithdrawal(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejected_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->checkPermission('manage_withdrawals');

            $withdrawal = Withdrawal::findOrFail($id);
            $admin = auth('admin')->user();

            $this->withdrawalService->rejectWithdrawal(
                $withdrawal,
                $admin->id,
                $request->rejected_reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal rejected successfully. Balance has been unlocked.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get deposit statistics
     */
    public function getDepositStats(Request $request)
    {
        try {
            $this->checkPermission('view_reports');

            $period = $request->input('period', 'today');
            $stats = $this->depositService->getDepositStatistics($period);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get withdrawal statistics
     */
    public function getWithdrawalStats(Request $request)
    {
        try {
            $this->checkPermission('view_reports');

            $period = $request->input('period', 'today');
            $stats = $this->withdrawalService->getWithdrawalStatistics($period);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Check if admin has permission
     */
    private function checkPermission(string $permission): void
    {
        $admin = auth('admin')->user();
        if (!$admin->hasPermission($permission)) {
            throw new \Exception('Insufficient permissions');
        }
    }
}
