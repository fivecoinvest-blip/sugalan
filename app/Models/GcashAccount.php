<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected $hidden = [
        'account_number_encrypted',
        'account_number_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_limit' => 'decimal:2',
            'current_daily_amount' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        // Encrypt account number on save
        static::saving(function ($account) {
            if ($account->isDirty('account_number') && $account->account_number) {
                $encryption = app(EncryptionService::class);
                $account->account_number_encrypted = $encryption->encrypt($account->account_number);
                $account->account_number_hash = $encryption->hash($account->account_number);
            }
        });
    }

    // Encrypted accessor/mutator for account number
    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($this->account_number_encrypted) {
                    return app(EncryptionService::class)->decrypt($this->account_number_encrypted);
                }
                return $value;
            },
            set: fn ($value) => $value,
        );
    }

    // Masked account number for display
    public function getMaskedAccountNumberAttribute(): ?string
    {
        $number = $this->account_number;
        if (!$number) return null;
        
        return app(EncryptionService::class)->mask($number, 4);
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
