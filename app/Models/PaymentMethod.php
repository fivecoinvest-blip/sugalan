<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'min_deposit',
        'max_deposit',
        'min_withdrawal',
        'max_withdrawal',
        'is_enabled',
        'supports_deposits',
        'supports_withdrawals',
        'display_order',
        'icon_url',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'min_deposit' => 'decimal:2',
            'max_deposit' => 'decimal:2',
            'min_withdrawal' => 'decimal:2',
            'max_withdrawal' => 'decimal:2',
            'is_enabled' => 'boolean',
            'supports_deposits' => 'boolean',
            'supports_withdrawals' => 'boolean',
        ];
    }

    public function isAvailableForDeposit(): bool
    {
        return $this->is_enabled && $this->supports_deposits;
    }

    public function isAvailableForWithdrawal(): bool
    {
        return $this->is_enabled && $this->supports_withdrawals;
    }

    public function validateDepositAmount(float $amount): bool
    {
        return $amount >= $this->min_deposit && $amount <= $this->max_deposit;
    }

    public function validateWithdrawalAmount(float $amount): bool
    {
        return $amount >= $this->min_withdrawal && $amount <= $this->max_withdrawal;
    }
}
