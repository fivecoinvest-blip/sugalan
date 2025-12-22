<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipLevel extends Model
{
    protected $fillable = [
        'name',
        'level',
        'min_wagered_amount',
        'min_deposit_amount',
        'bonus_multiplier',
        'wagering_reduction',
        'cashback_percentage',
        'withdrawal_limit_daily',
        'withdrawal_limit_weekly',
        'withdrawal_limit_monthly',
        'withdrawal_processing_hours',
        'color',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'min_wagered_amount' => 'decimal:2',
            'min_deposit_amount' => 'decimal:2',
            'bonus_multiplier' => 'decimal:2',
            'wagering_reduction' => 'integer',
            'cashback_percentage' => 'integer',
            'withdrawal_limit_daily' => 'decimal:2',
            'withdrawal_limit_weekly' => 'decimal:2',
            'withdrawal_limit_monthly' => 'decimal:2',
            'withdrawal_processing_hours' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Calculate actual wagering requirement with VIP reduction
    public function calculateWageringRequirement(float $bonusAmount, int $baseMultiplier): float
    {
        $reducedMultiplier = $baseMultiplier * (1 - ($this->wagering_reduction / 100));
        return $bonusAmount * $reducedMultiplier;
    }
}
