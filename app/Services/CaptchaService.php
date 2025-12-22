<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptchaService
{
    /**
     * Verify Google reCAPTCHA v3 response
     * 
     * @param string $token The reCAPTCHA token from client
     * @param string $action The action name (e.g., 'login', 'register', 'withdraw')
     * @param float $minScore Minimum acceptable score (0.0 to 1.0)
     * @return array ['success' => bool, 'score' => float, 'action' => string]
     */
    public function verify(string $token, string $action = 'submit', float $minScore = 0.5): array
    {
        // Skip CAPTCHA in local environment if not configured
        if (app()->environment('local') && !config('services.recaptcha.secret')) {
            return [
                'success' => true,
                'score' => 1.0,
                'action' => $action,
                'bypassed' => true,
            ];
        }

        $secret = config('services.recaptcha.secret');
        
        if (!$secret) {
            Log::error('reCAPTCHA secret not configured');
            return [
                'success' => false,
                'error' => 'CAPTCHA not configured',
            ];
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            $result = $response->json();

            // Verify response
            if (!$result['success']) {
                Log::warning('reCAPTCHA verification failed', [
                    'errors' => $result['error-codes'] ?? [],
                    'action' => $action,
                ]);
                
                return [
                    'success' => false,
                    'error' => 'CAPTCHA verification failed',
                    'error_codes' => $result['error-codes'] ?? [],
                ];
            }

            // Check score threshold
            $score = $result['score'] ?? 0;
            
            if ($score < $minScore) {
                Log::warning('reCAPTCHA score too low', [
                    'score' => $score,
                    'min_score' => $minScore,
                    'action' => $action,
                ]);
                
                return [
                    'success' => false,
                    'score' => $score,
                    'error' => 'CAPTCHA score too low',
                ];
            }

            // Verify action matches
            if (isset($result['action']) && $result['action'] !== $action) {
                Log::warning('reCAPTCHA action mismatch', [
                    'expected' => $action,
                    'received' => $result['action'],
                ]);
                
                return [
                    'success' => false,
                    'error' => 'CAPTCHA action mismatch',
                ];
            }

            return [
                'success' => true,
                'score' => $score,
                'action' => $result['action'] ?? $action,
            ];

        } catch (\Exception $e) {
            Log::error('reCAPTCHA API error', [
                'message' => $e->getMessage(),
                'action' => $action,
            ]);
            
            return [
                'success' => false,
                'error' => 'CAPTCHA service unavailable',
            ];
        }
    }

    /**
     * Get minimum score requirement for an action
     * Higher scores for more sensitive operations
     */
    public function getMinScoreForAction(string $action): float
    {
        return match($action) {
            'login', 'register' => 0.5,
            'deposit', 'withdraw' => 0.7,
            'password_reset', 'change_password' => 0.6,
            'bet', 'cashout' => 0.4,
            default => 0.5,
        };
    }
}
