<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Bet;
use App\Models\Deposit;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get dashboard analytics data
     */
    public function dashboard(Request $request)
    {
        $data = [
            'totalRevenue' => $this->getTotalRevenue(),
            'revenueTrend' => $this->getRevenueTrend(),
            'totalPlayers' => $this->getTotalPlayers(),
            'playersTrend' => $this->getPlayersTrend(),
            'totalBets' => $this->getTotalBets(),
            'betsTrend' => $this->getBetsTrend(),
            'vipPlayers' => $this->getVipPlayers(),
            'vipTrend' => $this->getVipTrend(),
            'revenueData' => $this->getRevenueChartData(),
            'activityData' => $this->getActivityChartData(),
            'gameData' => $this->getGameChartData(),
            'vipData' => $this->getVipChartData(),
            'recentTransactions' => $this->getRecentTransactions()
        ];

        return response()->json($data);
    }

    /**
     * Get total revenue
     */
    private function getTotalRevenue()
    {
        return Deposit::where('status', 'approved')
            ->sum('amount');
    }

    /**
     * Get revenue trend percentage
     */
    private function getRevenueTrend()
    {
        $currentMonth = Deposit::where('status', 'approved')
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('amount');

        $lastMonth = Deposit::where('status', 'approved')
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->sum('amount');

        if ($lastMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 2);
    }

    /**
     * Get total active players
     */
    private function getTotalPlayers()
    {
        return User::where('is_active', true)
            ->where('email_verified_at', '!=', null)
            ->count();
    }

    /**
     * Get players trend percentage
     */
    private function getPlayersTrend()
    {
        $currentMonth = User::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        $lastMonth = User::whereYear('created_at', Carbon::now()->subMonth()->year)
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->count();

        if ($lastMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 2);
    }

    /**
     * Get total bets
     */
    private function getTotalBets()
    {
        return Bet::whereDate('created_at', Carbon::today())->count();
    }

    /**
     * Get bets trend percentage
     */
    private function getBetsTrend()
    {
        $today = Bet::whereDate('created_at', Carbon::today())->count();
        $yesterday = Bet::whereDate('created_at', Carbon::yesterday())->count();

        if ($yesterday == 0) {
            return $today > 0 ? 100 : 0;
        }

        return round((($today - $yesterday) / $yesterday) * 100, 2);
    }

    /**
     * Get VIP players count
     */
    private function getVipPlayers()
    {
        return User::whereNotNull('vip_tier')
            ->where('vip_tier', '>', 0)
            ->count();
    }

    /**
     * Get VIP trend percentage
     */
    private function getVipTrend()
    {
        $thisWeek = User::whereNotNull('vip_tier')
            ->where('vip_tier', '>', 0)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();

        $lastWeek = User::whereNotNull('vip_tier')
            ->where('vip_tier', '>', 0)
            ->whereBetween('created_at', [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek()
            ])
            ->count();

        if ($lastWeek == 0) {
            return $thisWeek > 0 ? 100 : 0;
        }

        return round((($thisWeek - $lastWeek) / $lastWeek) * 100, 2);
    }

    /**
     * Get revenue chart data for last 7 days
     */
    private function getRevenueChartData($days = 7)
    {
        $data = [];
        $labels = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $revenue = Deposit::where('status', 'approved')
                ->whereDate('created_at', $date)
                ->sum('amount');
            
            $data[] = $revenue;
        }

        return [
            'labels' => $labels,
            'values' => $data
        ];
    }

    /**
     * Get player activity chart data
     */
    private function getActivityChartData($days = 7)
    {
        $data = [];
        $labels = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');
            
            $activePlayers = Bet::whereDate('created_at', $date)
                ->distinct('user_id')
                ->count('user_id');
            
            $data[] = $activePlayers;
        }

        return [
            'labels' => $labels,
            'values' => $data
        ];
    }

    /**
     * Get game popularity data
     */
    private function getGameChartData()
    {
        $games = Bet::select('game_type', DB::raw('COUNT(*) as count'))
            ->groupBy('game_type')
            ->orderBy('count', 'desc')
            ->get();

        $labels = $games->pluck('game_type')->map(function($type) {
            return ucfirst($type);
        })->toArray();

        $values = $games->pluck('count')->toArray();

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    /**
     * Get VIP tier distribution data
     */
    private function getVipChartData()
    {
        $tiers = [
            ['name' => 'Regular', 'value' => 0],
            ['name' => 'Bronze', 'value' => 1],
            ['name' => 'Silver', 'value' => 2],
            ['name' => 'Gold', 'value' => 3],
            ['name' => 'Platinum', 'value' => 4]
        ];

        $labels = [];
        $values = [];

        foreach ($tiers as $tier) {
            $count = User::where('vip_tier', $tier['value'])->count();
            $labels[] = $tier['name'];
            $values[] = $count;
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    /**
     * Get recent transactions
     */
    private function getRecentTransactions($limit = 10)
    {
        return Transaction::with('user:id,username')
            ->latest()
            ->take($limit)
            ->get()
            ->map(function($transaction) {
                return [
                    'id' => $transaction->id,
                    'user_name' => $transaction->user->username ?? 'N/A',
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status ?? 'completed',
                    'created_at' => $transaction->created_at->toISOString()
                ];
            });
    }

    /**
     * Export analytics data to CSV
     */
    public function export(Request $request)
    {
        $type = $request->input('type', 'transactions');

        switch ($type) {
            case 'transactions':
                return $this->exportTransactions();
            case 'revenue':
                return $this->exportRevenue();
            case 'players':
                return $this->exportPlayers();
            default:
                return $this->exportTransactions();
        }
    }

    /**
     * Export transactions to CSV
     */
    private function exportTransactions()
    {
        $transactions = Transaction::with('user:id,username')
            ->latest()
            ->take(1000)
            ->get();

        $filename = 'transactions_' . Carbon::now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, ['Date', 'User', 'Type', 'Amount', 'Balance Before', 'Balance After', 'Status']);

            // Data rows
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->user->username ?? 'N/A',
                    $transaction->type,
                    $transaction->amount,
                    $transaction->balance_before,
                    $transaction->balance_after,
                    $transaction->status ?? 'completed'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export revenue data to CSV
     */
    private function exportRevenue()
    {
        $deposits = Deposit::where('status', 'approved')
            ->with('user:id,username')
            ->latest()
            ->take(1000)
            ->get();

        $filename = 'revenue_' . Carbon::now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($deposits) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, ['Date', 'User', 'Amount', 'Reference Number', 'Status', 'Approved At']);

            // Data rows
            foreach ($deposits as $deposit) {
                fputcsv($file, [
                    $deposit->created_at->format('Y-m-d H:i:s'),
                    $deposit->user->username ?? 'N/A',
                    $deposit->amount,
                    $deposit->reference_number,
                    $deposit->status,
                    $deposit->approved_at ? $deposit->approved_at->format('Y-m-d H:i:s') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export players data to CSV
     */
    private function exportPlayers()
    {
        $players = User::with('wallet:id,user_id,real_balance,bonus_balance')
            ->latest()
            ->take(1000)
            ->get();

        $filename = 'players_' . Carbon::now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($players) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, ['ID', 'Username', 'Email', 'VIP Tier', 'Real Balance', 'Bonus Balance', 'Registered', 'Last Active']);

            // Data rows
            foreach ($players as $player) {
                fputcsv($file, [
                    $player->id,
                    $player->username,
                    $player->email,
                    $player->vip_tier ?? 0,
                    $player->wallet->real_balance ?? 0,
                    $player->wallet->bonus_balance ?? 0,
                    $player->created_at->format('Y-m-d H:i:s'),
                    $player->last_login_at ? $player->last_login_at->format('Y-m-d H:i:s') : 'Never'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get real-time statistics
     */
    public function realtime()
    {
        return response()->json([
            'online_players' => $this->getOnlinePlayers(),
            'active_bets' => $this->getActiveBets(),
            'pending_deposits' => $this->getPendingDeposits(),
            'pending_withdrawals' => $this->getPendingWithdrawals(),
            'total_wagered_today' => $this->getTotalWageredToday(),
            'total_won_today' => $this->getTotalWonToday()
        ]);
    }

    /**
     * Get online players count (active in last 15 minutes)
     */
    private function getOnlinePlayers()
    {
        return User::where('last_login_at', '>=', Carbon::now()->subMinutes(15))->count();
    }

    /**
     * Get active bets count
     */
    private function getActiveBets()
    {
        return Bet::where('status', 'pending')->count();
    }

    /**
     * Get pending deposits count
     */
    private function getPendingDeposits()
    {
        return Deposit::where('status', 'pending')->count();
    }

    /**
     * Get pending withdrawals count
     */
    private function getPendingWithdrawals()
    {
        return Withdrawal::where('status', 'pending')->count();
    }

    /**
     * Get total wagered today
     */
    private function getTotalWageredToday()
    {
        return Bet::whereDate('created_at', Carbon::today())->sum('bet_amount');
    }

    /**
     * Get total won today
     */
    private function getTotalWonToday()
    {
        return Bet::where('status', 'win')
            ->whereDate('created_at', Carbon::today())
            ->sum('payout');
    }
}
