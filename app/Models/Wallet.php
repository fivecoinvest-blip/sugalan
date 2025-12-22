<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'real_balance',
        'bonus_balance',
        'locked_balance',
        'lifetime_deposited',
        'lifetime_withdrawn',
        'lifetime_wagered',
        'lifetime_won',
        'lifetime_lost',
    ];

    protected function casts(): array
    {
        return [
            'real_balance' => 'decimal:2',
            'bonus_balance' => 'decimal:2',
            'locked_balance' => 'decimal:2',
            'lifetime_deposited' => 'decimal:2',
            'lifetime_withdrawn' => 'decimal:2',
            'lifetime_wagered' => 'decimal:2',
            'lifetime_won' => 'decimal:2',
            'lifetime_lost' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function getTotalBalance(): float
    {
        return (float) ($this->real_balance + $this->bonus_balance);
    }

    public function getAvailableBalance(): float
    {
        return (float) ($this->real_balance + $this->bonus_balance - $this->locked_balance);
    }

    public function hasBalance(float $amount): bool
    {
        return $this->getAvailableBalance() >= $amount;
    }

    public function hasRealBalance(float $amount): bool
    {
        return $this->real_balance >= $amount;
    }
}
