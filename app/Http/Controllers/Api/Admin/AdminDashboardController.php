<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminUser;
use App\Models\Bet;
use App\Models\Deposit;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Get dashboard overview statistics
     */
    public function getOverview(Request $request)
    {
        try {
            $this->checkPermission('view_dashboard');

            $period = $request->input('period', 'today'); // today, week, month, all

            $stats = [
                'users' => $this->getUserStats($period),
                'financial' => $this->getFinancialStats($period),
                'games' => $this->getGameStats($period),
                'pending' => $this->getPendingStats(),
            ];

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
     * Get user statistics
     */
    private function getUserStats(string $period): array
    {
        $query = User::query();

        if ($period !== 'all') {
            $query->where('created_at', '>=', $this->getPeriodStart($period));
        }

        $totalUsers = $query->count();
        $activeUsers = (clone $query)->where('status', 'active')->count();
        $guestUsers = (clone $query)->where('auth_method', 'guest')->count();
        
        // VIP distribution
        $vipDistribution = User::select('vip_level_id', DB::raw('count(*) as count'))
            ->groupBy('vip_level_id')
            ->with('vipLevel:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'vip_level' => $item->vipLevel->name,
                    'count' => $item->count,
                ];
            });

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'guest_users' => $guestUsers,
            'vip_distribution' => $vipDistribution,
        ];
    }

    /**
     * Get financial statistics
     */
    private function getFinancialStats(string $period): array
    {
        $depositQuery = Deposit::where('status', 'approved');
        $withdrawalQuery = Withdrawal::where('status', 'approved');
        $betQuery = Bet::query();

        if ($period !== 'all') {
            $start = $this->getPeriodStart($period);
            $depositQuery->where('processed_at', '>=', $start);
            $withdrawalQuery->where('processed_at', '>=', $start);
            $betQuery->where('created_at', '>=', $start);
        }

        $totalDeposits = $depositQuery->sum('amount');
        $totalWithdrawals = $withdrawalQuery->sum('amount');
        $totalBets = $betQuery->sum('bet_amount');
        $totalWinnings = $betQuery->where('status', 'won')->sum('payout');
        
        $grossRevenue = $totalBets - $totalWinnings;
        $netRevenue = $totalDeposits - $totalWithdrawals;

        return [
            'total_deposits' => (float) $totalDeposits,
            'total_withdrawals' => (float) $totalWithdrawals,
            'total_bets' => (float) $totalBets,
            'total_winnings' => (float) $totalWinnings,
            'gross_revenue' => (float) $grossRevenue,
            'net_revenue' => (float) $netRevenue,
            'profit_margin' => $totalBets > 0 ? round(($grossRevenue / $totalBets) * 100, 2) : 0,
        ];
    }

    /**
     * Get game statistics
     */
    private function getGameStats(string $period): array
    {
        $query = Bet::query();

        if ($period !== 'all') {
            $query->where('created_at', '>=', $this->getPeriodStart($period));
        }

        $gameStats = $query->select('game_type', 
            DB::raw('count(*) as total_bets'),
            DB::raw('sum(bet_amount) as total_wagered'),
            DB::raw('sum(CASE WHEN status = "won" THEN payout ELSE 0 END) as total_won')
        )
        ->groupBy('game_type')
        ->get()
        ->map(function ($item) {
            $rtp = $item->total_wagered > 0 ? round(($item->total_won / $item->total_wagered) * 100, 2) : 0;
            return [
                'game' => $item->game_type,
                'total_bets' => $item->total_bets,
                'total_wagered' => (float) $item->total_wagered,
                'total_won' => (float) $item->total_won,
                'house_edge' => round(100 - $rtp, 2),
                'rtp' => $rtp,
            ];
        });

        return [
            'by_game' => $gameStats,
            'total_bets' => $query->count(),
        ];
    }

    /**
     * Get pending items statistics
     */
    private function getPendingStats(): array
    {
        return [
            'pending_deposits' => Deposit::where('status', 'pending')->count(),
            'pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
        ];
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(Request $request)
    {
        try {
            $this->checkPermission('view_dashboard');

            $limit = $request->input('limit', 20);

            $recentDeposits = Deposit::where('status', 'pending')
                ->with('user:id,username')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($deposit) {
                    return [
                        'type' => 'deposit',
                        'user' => $deposit->user->username,
                        'amount' => $deposit->amount,
                        'status' => $deposit->status,
                        'created_at' => $deposit->created_at,
                    ];
                });

            $recentWithdrawals = Withdrawal::where('status', 'pending')
                ->with('user:id,username')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($withdrawal) {
                    return [
                        'type' => 'withdrawal',
                        'user' => $withdrawal->user->username,
                        'amount' => $withdrawal->amount,
                        'status' => $withdrawal->status,
                        'created_at' => $withdrawal->created_at,
                    ];
                });

            $recentBets = Bet::with('user:id,username')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($bet) {
                    return [
                        'type' => 'bet',
                        'user' => $bet->user->username,
                        'game' => $bet->game_type,
                        'amount' => $bet->bet_amount,
                        'result' => $bet->status,
                        'payout' => $bet->payout,
                        'created_at' => $bet->created_at,
                    ];
                });

            $activity = collect()
                ->concat($recentDeposits)
                ->concat($recentWithdrawals)
                ->concat($recentBets)
                ->sortByDesc('created_at')
                ->take($limit)
                ->values();

            return response()->json([
                'success' => true,
                'data' => $activity,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get user management data
     */
    public function getUsers(Request $request)
    {
        try {
            $this->checkPermission('manage_users');

            $perPage = $request->input('per_page', 50);
            $search = $request->input('search');
            $status = $request->input('status');
            $vipLevel = $request->input('vip_level');

            $query = User::with('vipLevel', 'wallet');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($vipLevel) {
                $query->where('vip_level_id', $vipLevel);
            }

            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'uuid' => $user->uuid,
                        'username' => $user->username,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'auth_method' => $user->auth_method,
                        'status' => $user->status,
                        'vip_level' => $user->vipLevel->name,
                        'wallet' => [
                            'real_balance' => $user->wallet->real_balance,
                            'bonus_balance' => $user->wallet->bonus_balance,
                            'locked_balance' => $user->wallet->locked_balance,
                        ],
                        'total_deposited' => $user->total_deposited,
                        'total_withdrawn' => $user->total_withdrawn,
                        'total_wagered' => $user->total_wagered,
                        'created_at' => $user->created_at,
                        'last_login_at' => $user->last_login_at,
                    ];
                }),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
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
     * Helper: Get period start date
     */
    private function getPeriodStart(string $period): \DateTime
    {
        return match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };
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
