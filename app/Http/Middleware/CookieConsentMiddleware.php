<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CookieConsentMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if user has provided cookie consent
        $hasConsent = $request->cookie('cookie_consent');

        // If no consent yet, attach consent required flag to response
        if (!$hasConsent && !$request->is('api/*')) {
            // This will trigger the cookie consent banner in the frontend
            $response->headers->set('X-Cookie-Consent-Required', 'true');
        }

        return $response;
    }
}
