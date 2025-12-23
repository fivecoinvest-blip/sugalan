<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Bet;
use App\Models\VipLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = [
                'users' => $this->getUserStats(),
                'financial' => $this->getFinancialStats(),
                'pending' => $this->getPendingActions(),
                'games' => $this->getGameStats(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    private function getUserStats(): array
    {
        $totalUsers = User::count();
        $activeToday = User::whereDate('last_login_at', today())->count();
        
        // VIP distribution
        $vipDistribution = User::join('vip_levels', 'users.vip_level_id', '=', 'vip_levels.id')
            ->select('vip_levels.name', DB::raw('count(*) as count'))
            ->groupBy('vip_levels.name')
            ->pluck('count', 'name')
            ->toArray();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeToday,
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_users_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)->count(),
            'vip_distribution' => $vipDistribution,
        ];
    }

    /**
     * Get financial statistics
     */
    private function getFinancialStats(): array
    {
        $totalDeposits = Deposit::where('status', 'completed')->sum('amount');
        $totalWithdrawals = Withdrawal::where('status', 'completed')->sum('amount');
        $totalWagered = Bet::sum('bet_amount');
        $totalWon = Bet::sum('payout');

        return [
            'total_deposits' => (float) $totalDeposits,
            'total_deposit_count' => Deposit::where('status', 'completed')->count(),
            'total_withdrawals' => (float) $totalWithdrawals,
            'total_withdrawal_count' => Withdrawal::where('status', 'completed')->count(),
            'net_revenue' => (float) ($totalDeposits - $totalWithdrawals),
            'gross_gaming_revenue' => (float) ($totalWagered - $totalWon),
            'total_wagered' => (float) $totalWagered,
            'total_won' => (float) $totalWon,
            'deposits_today' => (float) Deposit::where('status', 'completed')->whereDate('created_at', today())->sum('amount'),
            'withdrawals_today' => (float) Withdrawal::where('status', 'completed')->whereDate('created_at', today())->sum('amount'),
        ];
    }

    /**
     * Get pending actions count
     */
    private function getPendingActions(): array
    {
        return [
            'pending_deposits' => Deposit::where('status', 'pending')->count(),
            'pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
        ];
    }

    /**
     * Get game statistics
     */
    private function getGameStats(): array
    {
        $games = Bet::select(
            'game_type as game',
            DB::raw('COUNT(*) as total_bets'),
            DB::raw('SUM(bet_amount) as total_wagered'),
            DB::raw('SUM(payout) as total_won'),
            DB::raw('ROUND((1 - (SUM(payout) / SUM(bet_amount))) * 100, 2) as house_edge')
        )
        ->groupBy('game_type')
        ->having('total_wagered', '>', 0)
        ->get()
        ->map(function ($game) {
            return [
                'game' => ucfirst($game->game),
                'total_bets' => (int) $game->total_bets,
                'total_wagered' => (float) $game->total_wagered,
                'total_won' => (float) $game->total_won,
                'house_edge' => (float) $game->house_edge,
            ];
        })
        ->toArray();

        return $games;
    }

    /**
     * Get realtime analytics
     */
    public function realtime(): JsonResponse
    {
        try {
            $data = [
                'active_users' => User::whereDate('last_login_at', today())->count(),
                'active_bets' => Bet::whereDate('created_at', today())->count(),
                'deposits_today' => Deposit::where('status', 'completed')->whereDate('created_at', today())->sum('amount'),
                'withdrawals_today' => Withdrawal::where('status', 'completed')->whereDate('created_at', today())->sum('amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch realtime data'
            ], 500);
        }
    }

    /**
     * Export analytics data
     */
    public function export(): JsonResponse
    {
        // This would generate a CSV/Excel export
        return response()->json([
            'success' => true,
            'message' => 'Export functionality coming soon'
        ]);
    }
}
