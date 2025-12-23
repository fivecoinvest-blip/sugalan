<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Financial Transaction Security Middleware
 * 
 * Monitors and protects critical financial endpoints with enhanced security checks.
 */
class FinancialTransactionSecurity
{
    private const RATE_LIMIT_ATTEMPTS = 5;
    private const RATE_LIMIT_DECAY = 60; // seconds
    private const SUSPICIOUS_IP_THRESHOLD = 10; // failed attempts
    
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $operationType = 'financial'): Response
    {
        $user = $request->user();
        $ipAddress = $request->ip();
        
        // Log all financial transaction attempts
        $this->logTransactionAttempt($request, $operationType);
        
        // Rate limiting per user
        if ($user) {
            $rateLimitKey = "financial_rate_limit:user:{$user->id}";
            if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_ATTEMPTS)) {
                Log::channel('security')->warning('RATE_LIMIT_EXCEEDED', [
                    'user_id' => $user->id,
                    'ip_address' => $ipAddress,
                    'operation_type' => $operationType,
                    'url' => $request->fullUrl(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ], 429);
            }
            
            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);
        }
        
        // Rate limiting per IP
        $ipRateLimitKey = "financial_rate_limit:ip:{$ipAddress}";
        if (RateLimiter::tooManyAttempts($ipRateLimitKey, self::RATE_LIMIT_ATTEMPTS * 2)) {
            Log::channel('security')->alert('IP_RATE_LIMIT_EXCEEDED', [
                'ip_address' => $ipAddress,
                'operation_type' => $operationType,
                'url' => $request->fullUrl(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Too many requests from this IP address.',
            ], 429);
        }
        
        RateLimiter::hit($ipRateLimitKey, self::RATE_LIMIT_DECAY);
        
        // Check for suspicious IP patterns
        if ($this->isSuspiciousIP($ipAddress)) {
            Log::channel('security')->alert('SUSPICIOUS_IP_DETECTED', [
                'ip_address' => $ipAddress,
                'user_id' => $user?->id,
                'operation_type' => $operationType,
                'url' => $request->fullUrl(),
            ]);
            
            // Don't block, but log for review
        }
        
        // Validate request integrity
        if (!$this->validateRequestIntegrity($request)) {
            Log::channel('security')->alert('INVALID_REQUEST_INTEGRITY', [
                'ip_address' => $ipAddress,
                'user_id' => $user?->id,
                'operation_type' => $operationType,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid request.',
            ], 400);
        }
        
        // Execute request
        $response = $next($request);
        
        // Log successful transaction
        if ($response->status() >= 200 && $response->status() < 300) {
            $this->logSuccessfulTransaction($request, $response, $operationType);
        } else {
            $this->logFailedTransaction($request, $response, $operationType);
        }
        
        return $response;
    }
    
    /**
     * Log transaction attempt
     */
    private function logTransactionAttempt(Request $request, string $operationType): void
    {
        Log::channel('financial')->info('TRANSACTION_ATTEMPT', [
            'operation_type' => $operationType,
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Log successful transaction
     */
    private function logSuccessfulTransaction(Request $request, Response $response, string $operationType): void
    {
        $user = $request->user();
        
        Log::channel('financial')->info('TRANSACTION_SUCCESS', [
            'operation_type' => $operationType,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'status_code' => $response->status(),
            'timestamp' => now()->toIso8601String(),
        ]);
        
        // Clear rate limit on successful transaction
        if ($user) {
            $rateLimitKey = "financial_rate_limit:user:{$user->id}";
            RateLimiter::clear($rateLimitKey);
        }
    }
    
    /**
     * Log failed transaction
     */
    private function logFailedTransaction(Request $request, Response $response, string $operationType): void
    {
        Log::channel('security')->warning('TRANSACTION_FAILED', [
            'operation_type' => $operationType,
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'status_code' => $response->status(),
            'timestamp' => now()->toIso8601String(),
        ]);
        
        // Track failed attempts per IP
        $ipAddress = $request->ip();
        $failedAttemptsKey = "failed_financial_attempts:{$ipAddress}";
        $failedCount = Cache::increment($failedAttemptsKey, 1);
        Cache::expire($failedAttemptsKey, 3600); // 1 hour
        
        // Alert on suspicious pattern
        if ($failedCount >= self::SUSPICIOUS_IP_THRESHOLD) {
            Log::channel('security')->alert('HIGH_FAILURE_RATE_IP', [
                'ip_address' => $ipAddress,
                'failed_count' => $failedCount,
                'period_hours' => 1,
            ]);
        }
    }
    
    /**
     * Check if IP is suspicious
     */
    private function isSuspiciousIP(string $ipAddress): bool
    {
        // Check if IP has high failure rate
        $failedAttemptsKey = "failed_financial_attempts:{$ipAddress}";
        $failedCount = Cache::get($failedAttemptsKey, 0);
        
        if ($failedCount >= self::SUSPICIOUS_IP_THRESHOLD) {
            return true;
        }
        
        // Check if IP is in blocklist
        $blocklist = Cache::get('ip_blocklist', []);
        if (in_array($ipAddress, $blocklist)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate request integrity
     */
    private function validateRequestIntegrity(Request $request): bool
    {
        // Check for required headers
        if (!$request->hasHeader('User-Agent')) {
            return false;
        }
        
        // Check for suspicious patterns in user agent
        $userAgent = $request->userAgent();
        $suspiciousPatterns = ['bot', 'crawler', 'scraper', 'curl', 'wget'];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                Log::channel('security')->warning('SUSPICIOUS_USER_AGENT', [
                    'user_agent' => $userAgent,
                    'ip_address' => $request->ip(),
                ]);
                // Don't block, just log
            }
        }
        
        return true;
    }
}
