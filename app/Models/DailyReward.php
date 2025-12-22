<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReward extends Model
{
    protected $fillable = [
        'user_id',
        'check_in_date',
        'streak_days',
        'reward_amount',
        'bonus_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'reward_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    /**
     * Get reward amount based on streak
     */
    public static function calculateRewardAmount(int $streakDays, int $vipLevel = 1): float
    {
        // Base daily reward
        $baseRewards = [
            1 => 10,   // Day 1: ₱10
            2 => 15,   // Day 2: ₱15
            3 => 20,   // Day 3: ₱20
            4 => 25,   // Day 4: ₱25
            5 => 30,   // Day 5: ₱30
            6 => 40,   // Day 6: ₱40
            7 => 100,  // Day 7: ₱100 (bonus!)
        ];

        // Weekly cycle repeats
        $dayInCycle = (($streakDays - 1) % 7) + 1;
        $baseReward = $baseRewards[$dayInCycle] ?? 10;

        // VIP multipliers
        $vipMultipliers = [
            1 => 1.0,   // Bronze: 1x
            2 => 1.2,   // Silver: 1.2x
            3 => 1.5,   // Gold: 1.5x
            4 => 2.0,   // Platinum: 2x
            5 => 3.0,   // Diamond: 3x
        ];

        $multiplier = $vipMultipliers[$vipLevel] ?? 1.0;

        return $baseReward * $multiplier;
    }
}
