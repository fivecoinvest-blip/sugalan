<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated as admin
        if (!$request->user() || !($request->user() instanceof \App\Models\AdminUser)) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 401);
        }

        // Check if admin is active
        if (!$request->user()->is_active) {
            return response()->json([
                'message' => 'Admin account is inactive.'
            ], 403);
        }

        // IP whitelist check (if configured)
        $ipWhitelist = $request->user()->ip_whitelist;
        if (!empty($ipWhitelist) && !in_array($request->ip(), $ipWhitelist)) {
            return response()->json([
                'message' => 'Access denied from this IP address.'
            ], 403);
        }

        // Update last activity
        $request->user()->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
