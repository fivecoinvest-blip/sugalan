<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponsibleGaming extends Model
{
    use HasFactory;

    protected $table = 'responsible_gaming';

    protected $fillable = [
        'user_id',
        'daily_deposit_limit',
        'weekly_deposit_limit',
        'monthly_deposit_limit',
        'daily_wager_limit',
        'weekly_wager_limit',
        'monthly_wager_limit',
        'daily_loss_limit',
        'weekly_loss_limit',
        'monthly_loss_limit',
        'session_duration_limit',
        'reality_check_interval',
        'self_exclusion_status',
        'self_exclusion_start',
        'self_exclusion_end',
        'self_exclusion_reason',
        'cool_off_until',
        'last_reality_check',
        'last_session_start',
    ];

    protected $casts = [
        'daily_deposit_limit' => 'decimal:2',
        'weekly_deposit_limit' => 'decimal:2',
        'monthly_deposit_limit' => 'decimal:2',
        'daily_wager_limit' => 'decimal:2',
        'weekly_wager_limit' => 'decimal:2',
        'monthly_wager_limit' => 'decimal:2',
        'daily_loss_limit' => 'decimal:2',
        'weekly_loss_limit' => 'decimal:2',
        'monthly_loss_limit' => 'decimal:2',
        'self_exclusion_start' => 'datetime',
        'self_exclusion_end' => 'datetime',
        'cool_off_until' => 'datetime',
        'last_reality_check' => 'datetime',
        'last_session_start' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user is self-excluded
     */
    public function isSelfExcluded(): bool
    {
        if ($this->self_exclusion_status === 'permanent') {
            return true;
        }

        if ($this->self_exclusion_status === 'temporary' && $this->self_exclusion_end) {
            return now()->isBefore($this->self_exclusion_end);
        }

        return false;
    }

    /**
     * Check if user is in cool-off period
     */
    public function isInCoolOff(): bool
    {
        return $this->cool_off_until && now()->isBefore($this->cool_off_until);
    }

    /**
     * Check if reality check is needed
     */
    public function needsRealityCheck(): bool
    {
        if (!$this->last_reality_check || !$this->reality_check_interval) {
            return false;
        }

        $minutesSinceLastCheck = now()->diffInMinutes($this->last_reality_check);
        return $minutesSinceLastCheck >= $this->reality_check_interval;
    }

    /**
     * Check if session duration limit is exceeded
     */
    public function isSessionLimitExceeded(): bool
    {
        if (!$this->session_duration_limit || !$this->last_session_start) {
            return false;
        }

        $minutesSinceStart = now()->diffInMinutes($this->last_session_start);
        return $minutesSinceStart >= $this->session_duration_limit;
    }
}
