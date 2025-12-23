<?php

namespace App\Services;

use App\Models\User;
use App\Models\ResponsibleGaming;
use App\Models\Deposit;
use App\Models\Bet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ResponsibleGamingService
{
    /**
     * Set deposit limit (single period wrapper for tests)
     */
    public function setDepositLimit(int $userId, string $period, float $amount): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        // Validate minimum amount
        if ($amount < 100) {
            return false;
        }

        $settings = $this->getSettings($user);
        $column = "{$period}_deposit_limit";
        
        // Check cooldown for limit increases (not for first-time setting)
        $currentLimit = $settings->$column ?? 0;
        if ($currentLimit > 0 && $amount > $currentLimit) {
            $lastUpdate = $settings->updated_at;
            if ($lastUpdate && $lastUpdate->diffInHours(now()) < 24) {
                return false;
            }
        }
        
        $settings->update([$column => $amount]);
        
        return true;
    }

    /**
     * Set wager limit (single period wrapper for tests)
     */
    public function setWagerLimit(int $userId, string $period, float $amount): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        // Validate minimum amount
        if ($amount < 100) {
            return false;
        }

        $settings = $this->getSettings($user);
        $column = "{$period}_wager_limit";
        
        // Check cooldown for limit increases (not for first-time setting)
        $currentLimit = $settings->$column ?? 0;
        if ($currentLimit > 0 && $amount > $currentLimit) {
            $lastUpdate = $settings->updated_at;
            if ($lastUpdate && $lastUpdate->diffInHours(now()) < 24) {
                return false;
            }
        }
        
        $settings->update([$column => $amount]);
        
        return true;
    }

    /**
     * Set loss limit (single period wrapper for tests)
     */
    public function setLossLimit(int $userId, string $period, float $amount): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        // Validate minimum amount
        if ($amount < 100) {
            return false;
        }

        $settings = $this->getSettings($user);
        $column = "{$period}_loss_limit";
        
        // Check cooldown for limit increases (not for first-time setting)
        $currentLimit = $settings->$column ?? 0;
        if ($currentLimit > 0 && $amount > $currentLimit) {
            $lastUpdate = $settings->updated_at;
            if ($lastUpdate && $lastUpdate->diffInHours(now()) < 24) {
                return false;
            }
        }
        
        $settings->update([$column => $amount]);
        
        return true;
    }

    /**
     * Check if deposit is within limit
     */
    public function checkDepositLimit(int $userId, float $amount): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = ResponsibleGaming::where('user_id', $userId)->first();
        if (!$settings) return true; // No limits set

        // Check daily, weekly, monthly limits
        $dailySpent = $this->getDepositSpent($userId, 'daily');
        if ($settings->daily_deposit_limit && ($dailySpent + $amount) > $settings->daily_deposit_limit) {
            return false;
        }

        $weeklySpent = $this->getDepositSpent($userId, 'weekly');
        if ($settings->weekly_deposit_limit && ($weeklySpent + $amount) > $settings->weekly_deposit_limit) {
            return false;
        }

        $monthlySpent = $this->getDepositSpent($userId, 'monthly');
        if ($settings->monthly_deposit_limit && ($monthlySpent + $amount) > $settings->monthly_deposit_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if wager is within limit
     */
    public function checkWagerLimit(int $userId, float $amount): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = ResponsibleGaming::where('user_id', $userId)->first();
        if (!$settings) return true; // No limits set

        $dailyWagered = $this->getWagerSpent($userId, 'daily');
        if ($settings->daily_wager_limit && ($dailyWagered + $amount) > $settings->daily_wager_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if loss is within limit
     */
    public function checkLossLimit(int $userId, ?float $amount = null): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = ResponsibleGaming::where('user_id', $userId)->first();
        if (!$settings) return true; // No limit set, can play

        // If amount not provided, check current loss
        if ($amount === null) {
            $dailyLoss = $this->getLossAmount($userId, 'daily');
            if ($settings->daily_loss_limit && $dailyLoss >= $settings->daily_loss_limit) {
                return false; // Limit exceeded
            }
            return true;
        }

        // If amount provided, check if it would exceed limit
        if ($settings->daily_loss_limit && $amount >= $settings->daily_loss_limit) {
            return false; // Would exceed limit
        }

        return true; // Under limit, can play
    }

    /**
     * Check if user can play (alias for checkCanPlay)
     */
    public function canUserPlay(int $userId): bool
    {
        return $this->checkCanPlay($userId);
    }

    /**
     * Check if user can play (not self-excluded)
     */
    public function checkCanPlay(int $userId): bool
    {
        $settings = ResponsibleGaming::where('user_id', $userId)->first();
        if (!$settings) return true;

        // Check self-exclusion
        if ($settings->self_exclusion_status === 'permanent') {
            return false;
        }

        if (in_array($settings->self_exclusion_status, ['temporary', 'active']) && $settings->self_exclusion_end) {
            if (now()->lt($settings->self_exclusion_end)) {
                return false;
            }
        }

        // Check cool-off
        if ($settings->cool_off_until && now()->lt($settings->cool_off_until)) {
            return false;
        }

        return true;
    }

    /**
     * Set self-exclusion
     */
    public function setSelfExclusion(int $userId, $period, ?string $reason = null): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = $this->getSettings($user);
        
        // If period is 0, set permanent self-exclusion
        if ($period === 0 || $period === '0') {
            $settings->update([
                'self_exclusion_status' => 'permanent',
                'self_exclusion_start' => now(),
                'self_exclusion_end' => null,
                'self_exclusion_reason' => $reason,
            ]);
            return true;
        }
        
        // If period is numeric (hours), set active (temporary exclusion)
        if (is_numeric($period)) {
            $settings->update([
                'self_exclusion_status' => 'active',
                'self_exclusion_start' => now(),
                'self_exclusion_end' => now()->addHours($period),
                'self_exclusion_reason' => $reason,
            ]);
            return true;
        }
        
        // Otherwise parse period string
        $data = [
            'self_exclusion_status' => $period === 'permanent' ? 'permanent' : 'temporary',
            'self_exclusion_start' => now(),
            'self_exclusion_reason' => $reason,
        ];

        if ($period !== 'permanent') {
            $data['self_exclusion_end'] = $this->calculateExclusionEnd($period);
        }

        $settings->update($data);
        return true;
    }

    /**
     * Check if user is self-excluded
     */
    public function isUserSelfExcluded(int $userId): bool
    {
        $settings = ResponsibleGaming::where('user_id', $userId)->first();
        if (!$settings) return false;

        if ($settings->self_exclusion_status === 'permanent') {
            return true;
        }

        if (in_array($settings->self_exclusion_status, ['temporary', 'active']) && $settings->self_exclusion_end) {
            return now()->lt($settings->self_exclusion_end);
        }

        return false;
    }

    /**
     * Set session limit
     */
    public function setSessionLimit(int $userId, int $minutes): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = $this->getSettings($user);
        $settings->update(['session_duration_limit' => $minutes]);
        
        return true;
    }

    /**
     * Start gaming session (alias)
     */
    public function startGamingSession(int $userId): void
    {
        $this->startSession($userId);
    }

    /**
     * Start gaming session
     */
    public function startSession(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = $this->getSettings($user);
        $settings->update(['current_session_start' => now()]);
        return true;
    }

    /**
     * Check session timeout
     */
    public function checkSessionTimeout(int $userId): bool
    {
        return $this->hasSessionExpired($userId);
    }

    /**
     * Get user statistics (wrapper accepting userId)
     */
    public function getUserStatistics(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) return [];

        return $this->getStatisticsDetailed($user);
    }

    /**
     * Get user statistics (alias for getUserStatistics)
     */
    public function getStatistics(int|User $userOrId): array
    {
        if (is_int($userOrId)) {
            return $this->getUserStatistics($userOrId);
        }
        
        return $this->getStatisticsDetailed($userOrId);
    }

    /**
     * Remove a limit (set to null)
     */
    public function removeLimit(int $userId, string $limitType, string $period): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = $this->getSettings($user);
        
        // Map limit type and period to column name
        $column = "{$period}_{$limitType}_limit";
        
        $settings->update([$column => null]);
        
        return true;
    }

    /**
     * Check if session has expired
     */
    public function hasSessionExpired(int $userId): bool
    {
        $settings = ResponsibleGaming::where('user_id', $userId)->first();
        if (!$settings) {
            return false;
        }

        $durationLimit = $settings->session_duration_limit;
        if (!$durationLimit) {
            return false;
        }

        $sessionStart = $settings->current_session_start;
        if (!$sessionStart) {
            return false;
        }

        $minutesElapsed = now()->diffInMinutes($sessionStart, false);
        return abs($minutesElapsed) >= $durationLimit;
    }

    /**
     * Set reality check interval
     */
    public function setRealityCheck(int $userId, int $minutes): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $settings = $this->getSettings($user);
        $settings->update(['reality_check_interval' => $minutes]);
        
        return true;
    }

    /**
     * Get deposit spent in period
     */
    private function getDepositSpent(int $userId, string $period): float
    {
        $startDate = $this->getStartDateForPeriod($period);
        
        return Deposit::where('user_id', $userId)
            ->where('status', 'approved')
            ->where('created_at', '>=', $startDate)
            ->sum('amount');
    }

    /**
     * Get wager spent in period
     */
    private function getWagerSpent(int $userId, string $period): float
    {
        $startDate = $this->getStartDateForPeriod($period);
        
        return Bet::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->sum('bet_amount');
    }

    /**
     * Get loss amount in period
     */
    private function getLossAmount(int $userId, string $period): float
    {
        $startDate = $this->getStartDateForPeriod($period);
        
        $wagered = Bet::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->sum('bet_amount');
            
        $won = Bet::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->sum('payout');
            
        return max(0, $wagered - $won);
    }

    /**
     * Get start date for period
     */
    private function getStartDateForPeriod(string $period): Carbon
    {
        return match($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };
    }

    /**
     * Calculate exclusion end date
     */
    private function calculateExclusionEnd(string $period): Carbon
    {
        // Parse periods like "6_months", "1_year", "24_hours", etc
        $parts = explode('_', $period);
        $value = (int)$parts[0];
        $unit = $parts[1] ?? 'days';

        return match($unit) {
            'hours', 'hour' => now()->addHours($value),
            'days', 'day' => now()->addDays($value),
            'weeks', 'week' => now()->addWeeks($value),
            'months', 'month' => now()->addMonths($value),
            'years', 'year' => now()->addYears($value),
            default => now()->addDays($value),
        };
    }

    /**
     * Get or create responsible gaming settings for user
     */
    public function getSettings(User $user): ResponsibleGaming
    {
        return ResponsibleGaming::firstOrCreate(
            ['user_id' => $user->id],
            [
                'reality_check_interval' => 60,
                'self_exclusion_status' => 'none',
            ]
        );
    }

    /**
     * Set deposit limits
     */
    public function setDepositLimits(User $user, array $limits): ResponsibleGaming
    {
        $settings = $this->getSettings($user);

        $settings->update([
            'daily_deposit_limit' => $limits['daily'] ?? $settings->daily_deposit_limit,
            'weekly_deposit_limit' => $limits['weekly'] ?? $settings->weekly_deposit_limit,
            'monthly_deposit_limit' => $limits['monthly'] ?? $settings->monthly_deposit_limit,
        ]);

        // Log the change
        app(AuditService::class)->logSecurityEvent(
            $user->id,
            'deposit_limits_updated',
            'Responsible Gaming',
            ['limits' => $limits]
        );

        return $settings->fresh();
    }

    /**
     * Set wager limits
     */
    public function setWagerLimits(User $user, array $limits): ResponsibleGaming
    {
        $settings = $this->getSettings($user);

        $settings->update([
            'daily_wager_limit' => $limits['daily'] ?? $settings->daily_wager_limit,
            'weekly_wager_limit' => $limits['weekly'] ?? $settings->weekly_wager_limit,
            'monthly_wager_limit' => $limits['monthly'] ?? $settings->monthly_wager_limit,
        ]);

        app(AuditService::class)->logSecurityEvent(
            $user->id,
            'wager_limits_updated',
            'Responsible Gaming',
            ['limits' => $limits]
        );

        return $settings->fresh();
    }

    /**
     * Set loss limits
     */
    public function setLossLimits(User $user, array $limits): ResponsibleGaming
    {
        $settings = $this->getSettings($user);

        $settings->update([
            'daily_loss_limit' => $limits['daily'] ?? $settings->daily_loss_limit,
            'weekly_loss_limit' => $limits['weekly'] ?? $settings->weekly_loss_limit,
            'monthly_loss_limit' => $limits['monthly'] ?? $settings->monthly_loss_limit,
        ]);

        app(AuditService::class)->logSecurityEvent(
            $user->id,
            'loss_limits_updated',
            'Responsible Gaming',
            ['limits' => $limits]
        );

        return $settings->fresh();
    }

    /**
     * Set session limits
     */
    public function setSessionLimits(User $user, int $durationMinutes, int $realityCheckInterval): ResponsibleGaming
    {
        $settings = $this->getSettings($user);

        $settings->update([
            'session_duration_limit' => $durationMinutes,
            'reality_check_interval' => $realityCheckInterval,
        ]);

        app(AuditService::class)->logSecurityEvent(
            $user->id,
            'session_limits_updated',
            'Responsible Gaming',
            [
                'duration' => $durationMinutes,
                'reality_check' => $realityCheckInterval,
            ]
        );

        return $settings->fresh();
    }

    /**
     * Check if wager is within limits (renamed to avoid conflict)
     */
    public function checkWagerLimitDetailed(User $user, float $amount): array
    {
        $settings = $this->getSettings($user);

        $dailyTotal = $this->getWagerTotal($user, 'daily');
        $weeklyTotal = $this->getWagerTotal($user, 'weekly');
        $monthlyTotal = $this->getWagerTotal($user, 'monthly');

        $violations = [];

        if ($settings->daily_wager_limit && ($dailyTotal + $amount) > $settings->daily_wager_limit) {
            $violations[] = [
                'period' => 'daily',
                'limit' => $settings->daily_wager_limit,
                'current' => $dailyTotal,
                'attempted' => $amount,
                'remaining' => max(0, $settings->daily_wager_limit - $dailyTotal),
            ];
        }

        if ($settings->weekly_wager_limit && ($weeklyTotal + $amount) > $settings->weekly_wager_limit) {
            $violations[] = [
                'period' => 'weekly',
                'limit' => $settings->weekly_wager_limit,
                'current' => $weeklyTotal,
                'attempted' => $amount,
                'remaining' => max(0, $settings->weekly_wager_limit - $weeklyTotal),
            ];
        }

        if ($settings->monthly_wager_limit && ($monthlyTotal + $amount) > $settings->monthly_wager_limit) {
            $violations[] = [
                'period' => 'monthly',
                'limit' => $settings->monthly_wager_limit,
                'current' => $monthlyTotal,
                'attempted' => $amount,
                'remaining' => max(0, $settings->monthly_wager_limit - $monthlyTotal),
            ];
        }

        return [
            'allowed' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Check if loss is within limits (renamed to avoid conflict)
     */
    public function checkLossLimitDetailed(User $user, float $amount): array
    {
        $settings = $this->getSettings($user);

        $dailyLoss = $this->getLossTotal($user, 'daily');
        $weeklyLoss = $this->getLossTotal($user, 'weekly');
        $monthlyLoss = $this->getLossTotal($user, 'monthly');

        $violations = [];

        if ($settings->daily_loss_limit && ($dailyLoss + $amount) > $settings->daily_loss_limit) {
            $violations[] = [
                'period' => 'daily',
                'limit' => $settings->daily_loss_limit,
                'current' => $dailyLoss,
                'remaining' => max(0, $settings->daily_loss_limit - $dailyLoss),
            ];
        }

        if ($settings->weekly_loss_limit && ($weeklyLoss + $amount) > $settings->weekly_loss_limit) {
            $violations[] = [
                'period' => 'weekly',
                'limit' => $settings->weekly_loss_limit,
                'current' => $weeklyLoss,
                'remaining' => max(0, $settings->weekly_loss_limit - $weeklyLoss),
            ];
        }

        if ($settings->monthly_loss_limit && ($monthlyLoss + $amount) > $settings->monthly_loss_limit) {
            $violations[] = [
                'period' => 'monthly',
                'limit' => $settings->monthly_loss_limit,
                'current' => $monthlyLoss,
                'remaining' => max(0, $settings->monthly_loss_limit - $monthlyLoss),
            ];
        }

        return [
            'allowed' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Enable self-exclusion
     */
    public function enableSelfExclusion(User $user, string $type, ?Carbon $endDate = null, ?string $reason = null): ResponsibleGaming
    {
        $settings = $this->getSettings($user);

        $data = [
            'self_exclusion_status' => $type,
            'self_exclusion_start' => now(),
            'self_exclusion_reason' => $reason,
        ];

        if ($type === 'temporary' && $endDate) {
            $data['self_exclusion_end'] = $endDate;
        } elseif ($type === 'permanent') {
            $data['self_exclusion_end'] = null;
        }

        $settings->update($data);

        // Log self-exclusion
        app(AuditService::class)->logSecurityEvent(
            $user->id,
            'self_exclusion_enabled',
            'Responsible Gaming',
            [
                'type' => $type,
                'end_date' => $endDate?->toDateTimeString(),
                'reason' => $reason,
            ]
        );

        // Lock the user account
        $user->update(['status' => 'self_excluded']);

        return $settings->fresh();
    }

    /**
     * Request self-exclusion removal (requires admin approval)
     */
    public function requestSelfExclusionRemoval(User $user, string $reason): bool
    {
        $settings = $this->getSettings($user);

        if ($settings->self_exclusion_status === 'permanent') {
            throw new \Exception('Permanent self-exclusion cannot be removed.');
        }

        if ($settings->self_exclusion_status === 'none') {
            throw new \Exception('No active self-exclusion to remove.');
        }

        // Check if end date has passed
        if ($settings->self_exclusion_end && now()->isBefore($settings->self_exclusion_end)) {
            throw new \Exception('Self-exclusion period has not ended yet.');
        }

        // Log removal request (admin will need to approve)
        app(AuditService::class)->logSecurityEvent(
            $user->id,
            'self_exclusion_removal_requested',
            'Responsible Gaming',
            ['reason' => $reason]
        );

        return true;
    }

    /**
     * Enable cool-off period
     */
    public function enableCoolOff(User $user, int $hours): ResponsibleGaming
    {
        $settings = $this->getSettings($user);

        $settings->update([
            'cool_off_until' => now()->addHours($hours),
        ]);

        app(AuditService::class)->logSecurityEvent(
            $user->id,
            'cool_off_enabled',
            'Responsible Gaming',
            ['duration_hours' => $hours]
        );

        return $settings->fresh();
    }

    /**
     * Start session tracking (detailed)
     */
    public function startSessionTracking(User $user): ResponsibleGaming
    {
        $settings = $this->getSettings($user);

        $settings->update([
            'last_session_start' => now(),
            'last_reality_check' => now(),
        ]);

        return $settings->fresh();
    }

    /**
     * Check if user can play (not excluded, not in cool-off, session not exceeded)
     */
    public function canPlay(User $user): array
    {
        $settings = $this->getSettings($user);

        $reasons = [];

        if ($settings->isSelfExcluded()) {
            $reasons[] = [
                'type' => 'self_exclusion',
                'message' => 'Your account is currently self-excluded.',
                'until' => $settings->self_exclusion_end?->toDateTimeString(),
            ];
        }

        if ($settings->isInCoolOff()) {
            $reasons[] = [
                'type' => 'cool_off',
                'message' => 'Your account is in cool-off period.',
                'until' => $settings->cool_off_until?->toDateTimeString(),
            ];
        }

        if ($settings->isSessionLimitExceeded()) {
            $reasons[] = [
                'type' => 'session_limit',
                'message' => 'Your session time limit has been exceeded.',
                'limit_minutes' => $settings->session_duration_limit,
            ];
        }

        return [
            'allowed' => empty($reasons),
            'restrictions' => $reasons,
        ];
    }

    /**
     * Get statistics for user
     */
    public function getStatisticsDetailed(User $user): array
    {
        $settings = $this->getSettings($user);

        return [
            'deposit_limits' => [
                    'daily' => [
                        'limit' => $settings->daily_deposit_limit,
                        'used' => $this->getDepositTotal($user, 'daily'),
                        'remaining' => $settings->daily_deposit_limit 
                            ? max(0, $settings->daily_deposit_limit - $this->getDepositTotal($user, 'daily'))
                            : null,
                    ],
                    'weekly' => [
                        'limit' => $settings->weekly_deposit_limit,
                        'used' => $this->getDepositTotal($user, 'weekly'),
                        'remaining' => $settings->weekly_deposit_limit 
                            ? max(0, $settings->weekly_deposit_limit - $this->getDepositTotal($user, 'weekly'))
                            : null,
                    ],
                    'monthly' => [
                        'limit' => $settings->monthly_deposit_limit,
                        'used' => $this->getDepositTotal($user, 'monthly'),
                        'remaining' => $settings->monthly_deposit_limit 
                            ? max(0, $settings->monthly_deposit_limit - $this->getDepositTotal($user, 'monthly'))
                            : null,
                    ],
                ],
            'wager_limits' => [
                    'daily' => [
                        'limit' => $settings->daily_wager_limit,
                        'used' => $this->getWagerTotal($user, 'daily'),
                    ],
                    'weekly' => [
                        'limit' => $settings->weekly_wager_limit,
                        'used' => $this->getWagerTotal($user, 'weekly'),
                    ],
                    'monthly' => [
                        'limit' => $settings->monthly_wager_limit,
                        'used' => $this->getWagerTotal($user, 'monthly'),
                    ],
                ],
            'loss_limits' => [
                    'daily' => [
                        'limit' => $settings->daily_loss_limit,
                        'current' => $this->getLossTotal($user, 'daily'),
                    ],
                    'weekly' => [
                        'limit' => $settings->weekly_loss_limit,
                        'current' => $this->getLossTotal($user, 'weekly'),
                    ],
                    'monthly' => [
                        'limit' => $settings->monthly_loss_limit,
                        'current' => $this->getLossTotal($user, 'monthly'),
                    ],
                ],
            'session' => [
                'duration_limit' => $settings->session_duration_limit,
                'started_at' => $settings->last_session_start,
                'minutes_elapsed' => $settings->last_session_start 
                    ? now()->diffInMinutes($settings->last_session_start)
                    : null,
            ],
            'exclusion' => [
                'status' => $settings->self_exclusion_status,
                'start' => $settings->self_exclusion_start,
                'end' => $settings->self_exclusion_end,
                'is_active' => $settings->isSelfExcluded(),
            ],
            'cool_off' => [
                'until' => $settings->cool_off_until,
                'is_active' => $settings->isInCoolOff(),
            ],
        ];
    }

    /**
     * Get total deposits for period
     */
    private function getDepositTotal(User $user, string $period): float
    {
        $query = Deposit::where('user_id', $user->id)
            ->where('status', 'completed');

        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get total wagers for period
     */
    private function getWagerTotal(User $user, string $period): float
    {
        $query = Bet::where('user_id', $user->id);

        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get total losses for period
     */
    private function getLossTotal(User $user, string $period): float
    {
        $query = Bet::where('user_id', $user->id)
            ->where('result', 'loss');

        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        return (float) $query->sum('amount');
    }
}
