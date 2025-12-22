<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(
        private DepositService $depositService,
        private WithdrawalService $withdrawalService
    ) {}

    /**
     * Get available GCash accounts for deposits
     */
    public function getGcashAccounts()
    {
        try {
            $accounts = $this->depositService->getAvailableGcashAccounts();

            return response()->json([
                'success' => true,
                'data' => $accounts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create deposit request
     */
    public function createDeposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gcash_account_id' => 'required|exists:gcash_accounts,id',
            'amount' => 'required|numeric|min:100',
            'reference_number' => 'required|string|max:100',
            'screenshot' => 'required|image|max:5120', // 5MB max
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = auth()->user();

            $deposit = $this->depositService->createDepositRequest(
                $user,
                $request->gcash_account_id,
                $request->amount,
                $request->reference_number,
                $request->file('screenshot'),
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Deposit request submitted successfully. Please wait for admin approval.',
                'data' => [
                    'id' => $deposit->id,
                    'amount' => $deposit->amount,
                    'reference_number' => $deposit->reference_number,
                    'status' => $deposit->status,
                    'created_at' => $deposit->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's deposit history
     */
    public function getDepositHistory(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 20);

            $deposits = $this->depositService->getUserDepositHistory($user, $perPage);

            return response()->json([
                'success' => true,
                'data' => $deposits->map(function ($deposit) {
                    return [
                        'id' => $deposit->id,
                        'amount' => $deposit->amount,
                        'reference_number' => $deposit->reference_number,
                        'status' => $deposit->status,
                        'gcash_account' => [
                            'name' => $deposit->gcashAccount->account_name,
                            'number' => $deposit->gcashAccount->account_number,
                        ],
                        'screenshot_url' => $deposit->screenshot_url ? asset('storage/' . $deposit->screenshot_url) : null,
                        'notes' => $deposit->notes,
                        'admin_notes' => $deposit->admin_notes,
                        'rejected_reason' => $deposit->rejected_reason,
                        'processed_by' => $deposit->processedBy?->name,
                        'processed_at' => $deposit->processed_at,
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
            ], 400);
        }
    }

    /**
     * Create withdrawal request
     */
    public function createWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:200',
            'gcash_number' => 'required|string|regex:/^09[0-9]{9}$/',
            'gcash_name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = auth()->user();

            $withdrawal = $this->withdrawalService->createWithdrawalRequest(
                $user,
                $request->amount,
                $request->gcash_number,
                $request->gcash_name
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully. Please wait for admin approval.',
                'data' => [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                    'gcash_number' => $withdrawal->gcash_number,
                    'gcash_name' => $withdrawal->gcash_name,
                    'status' => $withdrawal->status,
                    'wagering_complete' => $withdrawal->wagering_complete,
                    'phone_verified' => $withdrawal->phone_verified,
                    'vip_limit_passed' => $withdrawal->vip_limit_passed,
                    'created_at' => $withdrawal->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's withdrawal history
     */
    public function getWithdrawalHistory(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 20);

            $withdrawals = $this->withdrawalService->getUserWithdrawalHistory($user, $perPage);

            return response()->json([
                'success' => true,
                'data' => $withdrawals->map(function ($withdrawal) {
                    return [
                        'id' => $withdrawal->id,
                        'amount' => $withdrawal->amount,
                        'gcash_number' => $withdrawal->gcash_number,
                        'gcash_name' => $withdrawal->gcash_name,
                        'status' => $withdrawal->status,
                        'wagering_complete' => $withdrawal->wagering_complete,
                        'phone_verified' => $withdrawal->phone_verified,
                        'vip_limit_passed' => $withdrawal->vip_limit_passed,
                        'admin_notes' => $withdrawal->admin_notes,
                        'rejected_reason' => $withdrawal->rejected_reason,
                        'processed_by' => $withdrawal->processedBy?->name,
                        'processed_at' => $withdrawal->processed_at,
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
            ], 400);
        }
    }

    /**
     * Get payment statistics (user)
     */
    public function getPaymentStats()
    {
        try {
            $user = auth()->user();

            $totalDeposited = $user->deposits()
                ->where('status', 'approved')
                ->sum('amount');

            $totalWithdrawn = $user->withdrawals()
                ->where('status', 'approved')
                ->sum('amount');

            $pendingDeposits = $user->deposits()
                ->where('status', 'pending')
                ->count();

            $pendingWithdrawals = $user->withdrawals()
                ->where('status', 'pending')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_deposited' => (float) $totalDeposited,
                    'total_withdrawn' => (float) $totalWithdrawn,
                    'net_balance' => (float) ($totalDeposited - $totalWithdrawn),
                    'pending_deposits' => $pendingDeposits,
                    'pending_withdrawals' => $pendingWithdrawals,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
