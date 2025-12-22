<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * Add security headers to protect against common vulnerabilities:
     * - HSTS: Force HTTPS
     * - CSP: Content Security Policy
     * - X-Frame-Options: Prevent clickjacking
     * - X-Content-Type-Options: Prevent MIME sniffing
     * - X-XSS-Protection: Enable XSS filter
     * - Referrer-Policy: Control referrer information
     * - Permissions-Policy: Control browser features
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Strict Transport Security (HSTS)
        // Force HTTPS for 1 year including subdomains
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Content Security Policy (CSP)
        // Restrict sources for scripts, styles, images, etc.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self' https://api.coingecko.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests"
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // X-Frame-Options: Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options: Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: Enable browser XSS filter
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer-Policy: Control referrer information leakage
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: Control browser features
        $permissionsPolicy = implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=(self)',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()'
        ]);
        $response->headers->set('Permissions-Policy', $permissionsPolicy);

        // Remove potentially dangerous headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
