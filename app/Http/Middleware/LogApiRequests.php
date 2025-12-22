<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuditService;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Log API requests for audit trail
     * 
     * Logs all sensitive API endpoints:
     * - Authentication
     * - Financial transactions
     * - Game bets
     * - Admin actions
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Process request
        $response = $next($request);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2); // ms

        // Only log sensitive endpoints
        if ($this->shouldLog($request)) {
            $this->logRequest($request, $response, $duration);
        }

        return $response;
    }

    /**
     * Determine if request should be logged
     */
    private function shouldLog(Request $request): bool
    {
        $path = $request->path();

        // Log these patterns
        $logPatterns = [
            'api/auth/',
            'api/wallet/',
            'api/deposit',
            'api/withdraw',
            'api/games/',
            'api/admin/',
            'api/bonuses/',
            'api/vip/',
        ];

        foreach ($logPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log the request details
     */
    private function logRequest(Request $request, Response $response, float $duration): void
    {
        $user = $request->user();
        $path = $request->path();
        $method = $request->method();
        $statusCode = $response->getStatusCode();

        // Determine action category
        $action = $this->getActionFromPath($path);

        // Prepare metadata
        $metadata = [
            'method' => $method,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'ip' => $request->ip(),
        ];

        // Add request parameters (sanitized)
        $params = $this->sanitizeParameters($request->all());
        if (!empty($params)) {
            $metadata['parameters'] = $params;
        }

        // Add fraud score if available
        if ($request->has('fraud_score')) {
            $metadata['fraud_score'] = $request->input('fraud_score');
        }

        // Add CAPTCHA score if available
        if ($request->has('captcha_score')) {
            $metadata['captcha_score'] = $request->input('captcha_score');
        }

        // Determine description
        $description = $this->getDescription($method, $path, $statusCode);

        // Determine log level
        $level = $statusCode >= 400 ? 'warning' : 'info';
        if ($statusCode >= 500) {
            $level = 'critical';
        }

        // Log to audit service
        $this->auditService->log(
            $action,
            $description,
            $metadata,
            $level,
            $user?->id
        );
    }

    /**
     * Get action category from path
     */
    private function getActionFromPath(string $path): string
    {
        if (str_contains($path, 'auth/')) {
            return 'api.auth';
        }
        if (str_contains($path, 'deposit')) {
            return 'api.deposit';
        }
        if (str_contains($path, 'withdraw')) {
            return 'api.withdraw';
        }
        if (str_contains($path, 'games/')) {
            return 'api.game';
        }
        if (str_contains($path, 'admin/')) {
            return 'api.admin';
        }
        if (str_contains($path, 'wallet/')) {
            return 'api.wallet';
        }
        if (str_contains($path, 'bonus')) {
            return 'api.bonus';
        }
        
        return 'api.request';
    }

    /**
     * Generate human-readable description
     */
    private function getDescription(string $method, string $path, int $statusCode): string
    {
        $success = $statusCode < 400 ? 'successful' : 'failed';
        return "{$method} {$path} - {$success} ({$statusCode})";
    }

    /**
     * Sanitize parameters to remove sensitive data
     */
    private function sanitizeParameters(array $params): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'api_key',
            'api_secret',
            'token',
            'secret',
            'captcha_token',
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($params[$key])) {
                $params[$key] = '[REDACTED]';
            }
        }

        return $params;
    }
}
