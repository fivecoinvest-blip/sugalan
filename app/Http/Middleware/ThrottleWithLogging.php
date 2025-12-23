<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ThrottleWithLogging
{
    /**
     * Enhanced rate limiting with logging and adaptive throttling
     * 
     * Implements exponential backoff for repeated violations
     * Logs suspicious activity for fraud detection
     */
    public function handle(Request $request, Closure $next, ?string $limits = '60,1'): Response
    {
        $identifier = $this->getIdentifier($request);
        $key = $this->getCacheKey($identifier, $request->path());
        
        // Parse limits (e.g., "5,60" = 5 attempts per 60 seconds)
        // Default to 60 requests per minute if not specified
        if (!$limits) {
            $limits = '60,1';
        }
        
        [$maxAttempts, $decayMinutes] = explode(',', $limits);
        $maxAttempts = (int) $maxAttempts;
        $decayMinutes = (int) $decayMinutes;

        // Check current attempts
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            // Log rate limit violation
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'path' => $request->path(),
                'attempts' => $attempts,
                'user_agent' => $request->userAgent(),
            ]);

            // Track repeated violations for adaptive throttling
            $violationKey = "rate_limit_violations:{$identifier}";
            $violations = Cache::get($violationKey, 0);
            Cache::put($violationKey, $violations + 1, now()->addHours(24));

            // Implement exponential backoff
            $backoffMultiplier = min(pow(2, $violations), 16); // Max 16x backoff
            $waitTime = $decayMinutes * 60 * $backoffMultiplier;

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $waitTime,
            ], 429);
        }

        // Increment attempts
        if ($attempts === 0) {
            Cache::put($key, 1, now()->addMinutes($decayMinutes));
        } else {
            Cache::increment($key);
        }

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $attempts - 1));
        
        return $response;
    }

    /**
     * Get unique identifier for rate limiting
     */
    private function getIdentifier(Request $request): string
    {
        // Use user ID if authenticated, otherwise use IP + User Agent hash
        if ($userId = $request->user()?->id) {
            return "user:{$userId}";
        }

        $userAgent = $request->userAgent() ?? 'unknown';
        return "ip:{$request->ip()}:" . md5($userAgent);
    }

    /**
     * Get cache key for rate limiting
     */
    private function getCacheKey(string $identifier, string $path): string
    {
        return "rate_limit:{$identifier}:" . md5($path);
    }
}
