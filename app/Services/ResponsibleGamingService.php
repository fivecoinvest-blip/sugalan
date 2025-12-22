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
     * Check if deposit is within limits
     */
    public function checkDepositLimit(User $user, float $amount): array
    {
        $settings = $this->getSettings($user);

        // Get current deposit amounts for periods
        $dailyTotal = $this->getDepositTotal($user, 'daily');
        $weeklyTotal = $this->getDepositTotal($user, 'weekly');
        $monthlyTotal = $this->getDepositTotal($user, 'monthly');

        $violations = [];

        // Check daily limit
        if ($settings->daily_deposit_limit && ($dailyTotal + $amount) > $settings->daily_deposit_limit) {
            $violations[] = [
                'period' => 'daily',
                'limit' => $settings->daily_deposit_limit,
                'current' => $dailyTotal,
                'attempted' => $amount,
                'remaining' => max(0, $settings->daily_deposit_limit - $dailyTotal),
            ];
        }

        // Check weekly limit
        if ($settings->weekly_deposit_limit && ($weeklyTotal + $amount) > $settings->weekly_deposit_limit) {
            $violations[] = [
                'period' => 'weekly',
                'limit' => $settings->weekly_deposit_limit,
                'current' => $weeklyTotal,
                'attempted' => $amount,
                'remaining' => max(0, $settings->weekly_deposit_limit - $weeklyTotal),
            ];
        }

        // Check monthly limit
        if ($settings->monthly_deposit_limit && ($monthlyTotal + $amount) > $settings->monthly_deposit_limit) {
            $violations[] = [
                'period' => 'monthly',
                'limit' => $settings->monthly_deposit_limit,
                'current' => $monthlyTotal,
                'attempted' => $amount,
                'remaining' => max(0, $settings->monthly_deposit_limit - $monthlyTotal),
            ];
        }

        return [
            'allowed' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Check if wager is within limits
     */
    public function checkWagerLimit(User $user, float $amount): array
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
     * Check if loss is within limits
     */
    public function checkLossLimit(User $user, float $amount): array
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
     * Start session tracking
     */
    public function startSession(User $user): ResponsibleGaming
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
    public function getStatistics(User $user): array
    {
        $settings = $this->getSettings($user);

        return [
            'limits' => [
                'deposit' => [
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
                'wager' => [
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
                'loss' => [
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
