<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CaptchaService;
use Symfony\Component\HttpFoundation\Response;

class VerifyCaptcha
{
    protected CaptchaService $captchaService;

    public function __construct(CaptchaService $captchaService)
    {
        $this->captchaService = $captchaService;
    }

    /**
     * Verify reCAPTCHA token for sensitive operations
     * 
     * Usage in routes:
     * ->middleware('captcha:login')
     * ->middleware('captcha:withdraw,0.7')
     */
    public function handle(Request $request, Closure $next, string $action = 'submit', ?string $minScore = null): Response
    {
        // Get CAPTCHA token from request
        $token = $request->input('captcha_token') ?? $request->header('X-Captcha-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'CAPTCHA token is required for this operation.',
            ], 422);
        }

        // Determine minimum score
        $minScore = $minScore 
            ? (float) $minScore 
            : $this->captchaService->getMinScoreForAction($action);

        // Verify CAPTCHA
        $result = $this->captchaService->verify($token, $action, $minScore);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'CAPTCHA verification failed. Please try again.',
                'error' => $result['error'] ?? 'Unknown error',
            ], 403);
        }

        // Store CAPTCHA score in request for controllers to use
        $request->merge(['captcha_score' => $result['score']]);

        return $next($request);
    }
}
