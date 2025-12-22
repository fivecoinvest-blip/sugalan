<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;
use Symfony\Component\HttpFoundation\Response;

class DetectFraud
{
    /**
     * Detect and prevent fraudulent activity
     * 
     * Checks for:
     * - Multiple accounts from same IP
     * - Rapid betting patterns
     * - Suspicious withdrawal patterns
     * - VPN/Proxy usage indicators
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $userId = $request->user()?->id;

        // Skip fraud detection for admin users
        if ($request->user() && $request->user() instanceof \App\Models\AdminUser) {
            return $next($request);
        }

        $fraudScore = 0;
        $flags = [];

        // Check 1: Multiple accounts from same IP (last 24 hours)
        if ($userId) {
            $accountsFromIp = Cache::remember(
                "ip_accounts:{$ip}",
                now()->addMinutes(10),
                function () use ($ip) {
                    return \App\Models\User::where('last_login_ip', $ip)
                        ->where('created_at', '>', now()->subDay())
                        ->count();
                }
            );

            if ($accountsFromIp > 3) {
                $fraudScore += 30;
                $flags[] = 'multiple_accounts_same_ip';
            }
        }

        // Check 2: Rapid action frequency
        $actionKey = "user_actions:{$userId}:{$request->path()}";
        $recentActions = Cache::get($actionKey, 0);
        
        if ($recentActions > 20) { // More than 20 actions per minute
            $fraudScore += 25;
            $flags[] = 'rapid_action_frequency';
        }
        
        Cache::put($actionKey, $recentActions + 1, now()->addMinute());

        // Check 3: Suspicious betting patterns (for game endpoints)
        if (str_contains($request->path(), 'games/') && str_contains($request->path(), '/play')) {
            $betPatternKey = "bet_pattern:{$userId}";
            $recentBets = Cache::get($betPatternKey, []);
            
            // Check for identical bet amounts (potential bot)
            if (count($recentBets) >= 5) {
                $uniqueBets = count(array_unique($recentBets));
                if ($uniqueBets <= 2) {
                    $fraudScore += 20;
                    $flags[] = 'identical_bet_pattern';
                }
            }
        }

        // Check 4: Known proxy/VPN IP ranges (basic check)
        if ($this->isPotentialProxy($ip)) {
            $fraudScore += 15;
            $flags[] = 'potential_vpn_proxy';
        }

        // Check 5: Rapid withdrawal attempts
        if (str_contains($request->path(), 'withdraw') && $request->isMethod('POST')) {
            $withdrawalKey = "withdrawals:{$userId}";
            $recentWithdrawals = Cache::get($withdrawalKey, 0);
            
            if ($recentWithdrawals > 3) {
                $fraudScore += 35;
                $flags[] = 'rapid_withdrawal_attempts';
            }
            
            Cache::put($withdrawalKey, $recentWithdrawals + 1, now()->addHour());
        }

        // Log high fraud scores
        if ($fraudScore >= 50) {
            Log::warning('High fraud score detected', [
                'user_id' => $userId,
                'ip' => $ip,
                'fraud_score' => $fraudScore,
                'flags' => $flags,
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            // Create audit log
            if ($userId) {
                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'fraud_detection',
                    'description' => "High fraud score: {$fraudScore}. Flags: " . implode(', ', $flags),
                    'ip_address' => $ip,
                    'user_agent' => $request->userAgent(),
                    'metadata' => json_encode([
                        'fraud_score' => $fraudScore,
                        'flags' => $flags,
                        'path' => $request->path(),
                    ]),
                ]);
            }

            // Block if fraud score is very high
            if ($fraudScore >= 80) {
                return response()->json([
                    'success' => false,
                    'message' => 'Suspicious activity detected. Please contact support.',
                ], 403);
            }
        }

        // Add fraud score to request for controllers to use
        $request->merge(['fraud_score' => $fraudScore]);

        return $next($request);
    }

    /**
     * Basic check for potential proxy/VPN IPs
     * In production, use a service like IPQualityScore or MaxMind
     */
    private function isPotentialProxy(string $ip): bool
    {
        // Check against known cloud provider IP ranges
        $cloudProviders = [
            '35.', '34.', '104.', // Google Cloud
            '52.', '54.', '18.', // AWS
            '13.', '20.', '40.', // Azure
        ];

        foreach ($cloudProviders as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
