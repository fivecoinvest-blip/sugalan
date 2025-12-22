<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyRequestSignature
{
    /**
     * Verify HMAC signature for sensitive API requests
     * 
     * Prevents replay attacks and ensures request integrity
     * Required for: withdrawals, large bets, admin actions
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip signature verification in local environment
        if (app()->environment('local') && !config('app.enforce_signatures')) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');

        // Check if signature headers are present
        if (!$signature || !$timestamp || !$nonce) {
            return response()->json([
                'success' => false,
                'message' => 'Request signature required for this operation.',
            ], 401);
        }

        // Check timestamp (prevent replay attacks - 5 minute window)
        $currentTime = time();
        if (abs($currentTime - $timestamp) > 300) {
            return response()->json([
                'success' => false,
                'message' => 'Request timestamp is too old or in the future.',
            ], 401);
        }

        // Check nonce (prevent replay attacks)
        $nonceKey = "request_nonce:{$nonce}";
        if (Cache::has($nonceKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Request nonce has already been used.',
            ], 401);
        }

        // Store nonce for 10 minutes
        Cache::put($nonceKey, true, now()->addMinutes(10));

        // Build signature payload
        $payload = $this->buildSignaturePayload($request, $timestamp, $nonce);

        // Get user's API secret
        $apiSecret = $this->getApiSecret($request);

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payload, $apiSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request signature.',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Build signature payload from request
     */
    private function buildSignaturePayload(Request $request, string $timestamp, string $nonce): string
    {
        $method = $request->method();
        $uri = $request->path();
        $body = $request->getContent();

        return "{$method}|{$uri}|{$timestamp}|{$nonce}|{$body}";
    }

    /**
     * Get API secret for the authenticated user
     */
    private function getApiSecret(Request $request): string
    {
        $user = $request->user();

        if (!$user) {
            return config('app.default_api_secret', 'default-secret');
        }

        // For production, store api_secret in users table
        // For now, use a derived secret from user ID and app key
        return hash('sha256', config('app.key') . $user->id);
    }
}
