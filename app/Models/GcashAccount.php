<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GcashAccount extends Model
{
    protected $fillable = [
        'account_name',
        'account_number',
        'qr_code_url',
        'is_active',
        'daily_limit',
        'current_daily_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_limit' => 'decimal:2',
            'current_daily_amount' => 'decimal:2',
        ];
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function hasReachedDailyLimit(): bool
    {
        return $this->daily_limit > 0 && 
               $this->current_daily_amount >= $this->daily_limit;
    }

    public function getRemainingDailyLimit(): float
    {
        if ($this->daily_limit <= 0) {
            return PHP_FLOAT_MAX;
        }
        return max(0, $this->daily_limit - $this->current_daily_amount);
    }
}
