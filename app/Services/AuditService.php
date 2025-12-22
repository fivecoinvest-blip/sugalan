<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Log an audit event with comprehensive details
     * 
     * @param string $action The action being performed
     * @param string $description Human-readable description
     * @param array $metadata Additional context data
     * @param string $level Severity level: info, warning, critical
     * @param int|null $userId User ID (defaults to authenticated user)
     * @param int|null $adminUserId Admin user ID if applicable
     */
    public function log(
        string $action,
        string $description,
        array $metadata = [],
        string $level = 'info',
        ?int $userId = null,
        ?int $adminUserId = null
    ): void {
        try {
            $request = request();
            
            // Get user IDs if not provided
            if ($userId === null && Auth::guard('api')->check()) {
                $userId = Auth::guard('api')->id();
            }
            
            if ($adminUserId === null && Auth::guard('admin')->check()) {
                $adminUserId = Auth::guard('admin')->id();
            }

            // Create audit log record
            AuditLog::create([
                'user_id' => $userId,
                'admin_user_id' => $adminUserId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => json_encode(array_merge($metadata, [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'timestamp' => now()->toIso8601String(),
                ])),
            ]);

            // Also log to Laravel log for critical events
            if ($level === 'critical') {
                Log::critical("AUDIT: {$action}", [
                    'description' => $description,
                    'user_id' => $userId,
                    'admin_user_id' => $adminUserId,
                    'metadata' => $metadata,
                ]);
            }

        } catch (\Exception $e) {
            // Ensure audit logging failures don't break the application
            Log::error('Failed to create audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log authentication event
     */
    public function logAuth(string $event, int $userId, bool $success, array $metadata = []): void
    {
        $this->log(
            "auth.{$event}",
            $success 
                ? "User {$event} successful" 
                : "User {$event} failed",
            array_merge($metadata, ['success' => $success]),
            $success ? 'info' : 'warning',
            $userId
        );
    }

    /**
     * Log financial transaction
     */
    public function logFinancial(string $type, int $userId, float $amount, array $metadata = []): void
    {
        $this->log(
            "financial.{$type}",
            "Financial transaction: {$type} - ₱" . number_format($amount, 2),
            array_merge($metadata, [
                'amount' => $amount,
                'type' => $type,
            ]),
            'info',
            $userId
        );
    }

    /**
     * Log game activity
     */
    public function logGame(string $game, int $userId, float $betAmount, float $winAmount, array $metadata = []): void
    {
        $this->log(
            "game.{$game}",
            "Game bet: {$game} - Bet: ₱{$betAmount}, Win: ₱{$winAmount}",
            array_merge($metadata, [
                'game' => $game,
                'bet_amount' => $betAmount,
                'win_amount' => $winAmount,
                'profit' => $winAmount - $betAmount,
            ]),
            'info',
            $userId
        );
    }

    /**
     * Log admin action
     */
    public function logAdmin(string $action, int $adminUserId, string $description, array $metadata = []): void
    {
        $this->log(
            "admin.{$action}",
            $description,
            $metadata,
            'info',
            null,
            $adminUserId
        );
    }

    /**
     * Log security event
     */
    public function logSecurity(string $event, string $description, array $metadata = [], string $level = 'warning'): void
    {
        $this->log(
            "security.{$event}",
            $description,
            $metadata,
            $level
        );
    }

    /**
     * Log VIP tier change
     */
    public function logVipChange(int $userId, string $oldTier, string $newTier, array $metadata = []): void
    {
        $this->log(
            'vip.tier_change',
            "VIP tier changed: {$oldTier} → {$newTier}",
            array_merge($metadata, [
                'old_tier' => $oldTier,
                'new_tier' => $newTier,
            ]),
            'info',
            $userId
        );
    }

    /**
     * Log bonus activity
     */
    public function logBonus(string $action, int $userId, float $amount, string $type, array $metadata = []): void
    {
        $this->log(
            "bonus.{$action}",
            "Bonus {$action}: {$type} - ₱{$amount}",
            array_merge($metadata, [
                'bonus_type' => $type,
                'amount' => $amount,
            ]),
            'info',
            $userId
        );
    }

    /**
     * Get audit logs with filters
     */
    public function getLogs(array $filters = [], int $perPage = 50)
    {
        $query = AuditLog::query();

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['admin_user_id'])) {
            $query->where('admin_user_id', $filters['admin_user_id']);
        }

        if (isset($filters['action'])) {
            $query->where('action', 'like', $filters['action'] . '%');
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
