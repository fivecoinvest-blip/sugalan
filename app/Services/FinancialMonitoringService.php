<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Bet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Financial Monitoring Service
 * 
 * Monitors and logs critical financial operations for security and compliance.
 * Detects anomalies and suspicious patterns in real-time.
 */
class FinancialMonitoringService
{
    // Thresholds for anomaly detection
    private const LARGE_TRANSACTION_THRESHOLD = 50000; // 50k PHP
    private const HIGH_FREQUENCY_THRESHOLD = 10; // transactions per minute
    private const RAPID_DEPOSIT_THRESHOLD = 5; // deposits per hour
    private const SUSPICIOUS_WIN_RATE = 0.75; // 75% win rate
    private const DAILY_VOLUME_THRESHOLD = 500000; // 500k PHP
    
    /**
     * Log financial transaction with security context
     */
    public function logFinancialTransaction(
        string $type,
        User $user,
        float $amount,
        array $metadata = [],
        ?int $relatedId = null
    ): void {
        $context = [
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'related_id' => $relatedId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
            'metadata' => $metadata,
        ];
        
        // Add VIP level context
        if ($user->vipLevel) {
            $context['vip_level'] = $user->vipLevel->level;
        }
        
        // Log to dedicated financial channel
        Log::channel('financial')->info("FINANCIAL_TRANSACTION: {$type}", $context);
        
        // Check for anomalies
        $this->detectAnomalies($type, $user, $amount, $context);
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent(
        string $event,
        User $user,
        string $severity = 'info',
        array $context = []
    ): void {
        $fullContext = array_merge([
            'user_id' => $user->id,
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ], $context);
        
        // Log to security channel
        Log::channel('security')->{$severity}("SECURITY_EVENT: {$event}", $fullContext);
        
        // Critical events trigger immediate alerts
        if ($severity === 'critical' || $severity === 'alert') {
            $this->triggerSecurityAlert($event, $user, $fullContext);
        }
    }
    
    /**
     * Detect financial anomalies
     */
    private function detectAnomalies(string $type, User $user, float $amount, array $context): void
    {
        $anomalies = [];
        
        // 1. Large transaction detection
        if ($amount >= self::LARGE_TRANSACTION_THRESHOLD) {
            $anomalies[] = 'large_transaction';
            Log::channel('security')->warning('ANOMALY: Large transaction detected', [
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'threshold' => self::LARGE_TRANSACTION_THRESHOLD,
            ]);
        }
        
        // 2. High frequency detection
        $recentTransactionCount = $this->getRecentTransactionCount($user, 60);
        if ($recentTransactionCount > self::HIGH_FREQUENCY_THRESHOLD) {
            $anomalies[] = 'high_frequency';
            Log::channel('security')->warning('ANOMALY: High frequency transactions', [
                'user_id' => $user->id,
                'count' => $recentTransactionCount,
                'period_seconds' => 60,
                'threshold' => self::HIGH_FREQUENCY_THRESHOLD,
            ]);
        }
        
        // 3. Rapid deposit detection
        if ($type === 'deposit') {
            $recentDepositCount = $this->getRecentDepositCount($user, 3600);
            if ($recentDepositCount > self::RAPID_DEPOSIT_THRESHOLD) {
                $anomalies[] = 'rapid_deposits';
                Log::channel('security')->alert('ANOMALY: Rapid deposits detected', [
                    'user_id' => $user->id,
                    'count' => $recentDepositCount,
                    'period_hours' => 1,
                    'threshold' => self::RAPID_DEPOSIT_THRESHOLD,
                ]);
            }
        }
        
        // 4. Suspicious win rate detection
        if ($type === 'win') {
            $winRate = $this->calculateRecentWinRate($user);
            if ($winRate >= self::SUSPICIOUS_WIN_RATE) {
                $anomalies[] = 'suspicious_win_rate';
                Log::channel('security')->alert('ANOMALY: Suspicious win rate', [
                    'user_id' => $user->id,
                    'win_rate' => $winRate,
                    'threshold' => self::SUSPICIOUS_WIN_RATE,
                ]);
            }
        }
        
        // 5. Daily volume threshold
        $dailyVolume = $this->getDailyTransactionVolume($user);
        if ($dailyVolume >= self::DAILY_VOLUME_THRESHOLD) {
            $anomalies[] = 'high_daily_volume';
            Log::channel('security')->warning('ANOMALY: High daily transaction volume', [
                'user_id' => $user->id,
                'daily_volume' => $dailyVolume,
                'threshold' => self::DAILY_VOLUME_THRESHOLD,
            ]);
        }
        
        // Store anomalies in cache for real-time monitoring
        if (!empty($anomalies)) {
            $this->recordAnomalies($user, $anomalies, $context);
        }
    }
    
    /**
     * Get recent transaction count
     */
    private function getRecentTransactionCount(User $user, int $seconds): int
    {
        $cacheKey = "transaction_count:{$user->id}:" . now()->format('YmdHi');
        
        return Cache::remember($cacheKey, 120, function () use ($user, $seconds) {
            return Transaction::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subSeconds($seconds))
                ->count();
        });
    }
    
    /**
     * Get recent deposit count
     */
    private function getRecentDepositCount(User $user, int $seconds): int
    {
        return Deposit::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subSeconds($seconds))
            ->count();
    }
    
    /**
     * Calculate recent win rate
     */
    private function calculateRecentWinRate(User $user): float
    {
        $recentBets = Bet::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->where('status', 'settled')
            ->get();
            
        if ($recentBets->isEmpty()) {
            return 0;
        }
        
        $wins = $recentBets->where('result', 'win')->count();
        $total = $recentBets->count();
        
        return $total > 0 ? $wins / $total : 0;
    }
    
    /**
     * Get daily transaction volume
     */
    private function getDailyTransactionVolume(User $user): float
    {
        return Transaction::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->sum('amount');
    }
    
    /**
     * Record anomalies for monitoring dashboard
     */
    private function recordAnomalies(User $user, array $anomalies, array $context): void
    {
        $cacheKey = "anomalies:{$user->id}:" . today()->format('Ymd');
        
        $existingAnomalies = Cache::get($cacheKey, []);
        $existingAnomalies[] = [
            'timestamp' => now()->toIso8601String(),
            'types' => $anomalies,
            'context' => $context,
        ];
        
        Cache::put($cacheKey, $existingAnomalies, now()->addDay());
    }
    
    /**
     * Trigger security alert for critical events
     */
    private function triggerSecurityAlert(string $event, User $user, array $context): void
    {
        // Log critical security event
        Log::channel('security')->critical('SECURITY_ALERT_TRIGGERED', [
            'event' => $event,
            'user_id' => $user->id,
            'context' => $context,
        ]);
        
        // Store in cache for admin dashboard
        $alertKey = "security_alerts:" . now()->format('YmdH');
        $alerts = Cache::get($alertKey, []);
        $alerts[] = [
            'event' => $event,
            'user_id' => $user->id,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
        ];
        Cache::put($alertKey, $alerts, now()->addHours(24));
        
        // TODO: Implement notification to admin (email, SMS, Slack, etc.)
        // $this->notificationService->notifyAdmins($event, $user, $context);
    }
    
    /**
     * Monitor wallet balance integrity
     */
    public function verifyWalletIntegrity(User $user): array
    {
        $wallet = $user->wallet;
        $issues = [];
        
        // Check for negative balances
        if ($wallet->real_balance < 0) {
            $issues[] = 'negative_real_balance';
            Log::channel('security')->critical('WALLET_INTEGRITY: Negative real balance', [
                'user_id' => $user->id,
                'balance' => $wallet->real_balance,
            ]);
        }
        
        if ($wallet->bonus_balance < 0) {
            $issues[] = 'negative_bonus_balance';
            Log::channel('security')->critical('WALLET_INTEGRITY: Negative bonus balance', [
                'user_id' => $user->id,
                'balance' => $wallet->bonus_balance,
            ]);
        }
        
        if ($wallet->locked_balance < 0) {
            $issues[] = 'negative_locked_balance';
            Log::channel('security')->critical('WALLET_INTEGRITY: Negative locked balance', [
                'user_id' => $user->id,
                'balance' => $wallet->locked_balance,
            ]);
        }
        
        // Check for impossible balance combinations
        $totalBalance = $wallet->real_balance + $wallet->bonus_balance + $wallet->locked_balance;
        $totalTransacted = $wallet->lifetime_deposited + $wallet->lifetime_won - $wallet->lifetime_wagered;
        
        // Allow for some floating point precision issues
        if (abs($totalBalance - $totalTransacted) > 0.01) {
            $issues[] = 'balance_mismatch';
            Log::channel('security')->alert('WALLET_INTEGRITY: Balance mismatch detected', [
                'user_id' => $user->id,
                'total_balance' => $totalBalance,
                'calculated_balance' => $totalTransacted,
                'difference' => abs($totalBalance - $totalTransacted),
            ]);
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }
    
    /**
     * Log withdrawal approval with security context
     */
    public function logWithdrawalApproval(Withdrawal $withdrawal, int $adminUserId, ?string $notes): void
    {
        Log::channel('financial')->info('WITHDRAWAL_APPROVED', [
            'withdrawal_id' => $withdrawal->id,
            'user_id' => $withdrawal->user_id,
            'amount' => $withdrawal->amount,
            'admin_user_id' => $adminUserId,
            'admin_notes' => $notes,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);
        
        // Check if approval is outside normal patterns
        $this->detectWithdrawalAnomalies($withdrawal, $adminUserId);
    }
    
    /**
     * Log deposit approval with security context
     */
    public function logDepositApproval(Deposit $deposit, int $adminUserId, ?string $notes): void
    {
        Log::channel('financial')->info('DEPOSIT_APPROVED', [
            'deposit_id' => $deposit->id,
            'user_id' => $deposit->user_id,
            'amount' => $deposit->amount,
            'reference_number' => $deposit->reference_number,
            'admin_user_id' => $adminUserId,
            'admin_notes' => $notes,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Detect withdrawal anomalies
     */
    private function detectWithdrawalAnomalies(Withdrawal $withdrawal, int $adminUserId): void
    {
        // Check for rapid approval (less than 1 minute from request)
        $timeSinceRequest = now()->diffInSeconds($withdrawal->created_at);
        if ($timeSinceRequest < 60) {
            Log::channel('security')->warning('ANOMALY: Rapid withdrawal approval', [
                'withdrawal_id' => $withdrawal->id,
                'time_since_request' => $timeSinceRequest,
                'admin_user_id' => $adminUserId,
            ]);
        }
        
        // Check for large withdrawal
        if ($withdrawal->amount >= self::LARGE_TRANSACTION_THRESHOLD) {
            Log::channel('security')->warning('ANOMALY: Large withdrawal approved', [
                'withdrawal_id' => $withdrawal->id,
                'amount' => $withdrawal->amount,
                'admin_user_id' => $adminUserId,
            ]);
        }
    }
    
    /**
     * Get security metrics for admin dashboard
     */
    public function getSecurityMetrics(?\DateTime $since = null): array
    {
        $since = $since ?? now()->subDay();
        
        return [
            'anomaly_count' => $this->getAnomalyCount($since),
            'large_transactions' => $this->getLargeTransactionCount($since),
            'high_win_rate_users' => $this->getHighWinRateUsers($since),
            'failed_login_attempts' => $this->getFailedLoginAttempts($since),
            'suspicious_ips' => $this->getSuspiciousIPs($since),
        ];
    }
    
    private function getAnomalyCount(\DateTime $since): int
    {
        // Count anomalies from cache
        $count = 0;
        $date = \Carbon\Carbon::instance($since);
        
        while ($date <= now()) {
            $cacheKey = "anomalies:*:" . $date->format('Ymd');
            // This is a simplified version - in production, use Redis SCAN
            $date->addDay();
            $count++; // Placeholder
        }
        
        return $count;
    }
    
    private function getLargeTransactionCount(\DateTime $since): int
    {
        return Transaction::where('created_at', '>=', $since)
            ->where('amount', '>=', self::LARGE_TRANSACTION_THRESHOLD)
            ->count();
    }
    
    private function getHighWinRateUsers(\DateTime $since): int
    {
        // Users with > 75% win rate in last period
        return DB::table('bets')
            ->select('user_id')
            ->where('created_at', '>=', $since)
            ->where('status', 'settled')
            ->groupBy('user_id')
            ->havingRaw('SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) / COUNT(*) > ?', ['win', self::SUSPICIOUS_WIN_RATE])
            ->count();
    }
    
    private function getFailedLoginAttempts(\DateTime $since): int
    {
        // This would require tracking failed logins in audit logs
        return \App\Models\AuditLog::where('action', 'login_failed')
            ->where('created_at', '>=', $since)
            ->count();
    }
    
    private function getSuspiciousIPs(\DateTime $since): int
    {
        // IPs with high failed login rate or multiple accounts
        return DB::table('audit_logs')
            ->select('ip_address')
            ->where('created_at', '>=', $since)
            ->groupBy('ip_address')
            ->havingRaw('COUNT(DISTINCT user_id) > 5')
            ->count();
    }
}
