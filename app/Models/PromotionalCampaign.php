<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromotionalCampaign extends Model
{
    protected $fillable = [
        'title',
        'description',
        'code',
        'type',
        'value',
        'percentage',
        'min_deposit',
        'max_bonus',
        'wagering_multiplier',
        'min_vip_level',
        'max_vip_level',
        'max_claims_total',
        'max_claims_per_user',
        'current_claims',
        'starts_at',
        'expires_at',
        'status',
        'terms',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'percentage' => 'decimal:2',
            'min_deposit' => 'decimal:2',
            'max_bonus' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'config' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_user')
            ->withPivot(['bonus_id', 'claimed_at'])
            ->withTimestamps();
    }

    /**
     * Check if campaign is active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->isBefore($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->isAfter($this->expires_at)) {
            return false;
        }

        if ($this->max_claims_total && $this->current_claims >= $this->max_claims_total) {
            return false;
        }

        return true;
    }

    /**
     * Check if user is eligible for this campaign
     */
    public function isEligibleFor(User $user): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        // Check VIP level requirements
        $userVipLevel = $user->vipLevel->level;
        if ($userVipLevel < $this->min_vip_level) {
            return false;
        }

        if ($this->max_vip_level && $userVipLevel > $this->max_vip_level) {
            return false;
        }

        // Check user claim limit
        $userClaims = $this->users()->where('user_id', $user->id)->count();
        if ($userClaims >= $this->max_claims_per_user) {
            return false;
        }

        return true;
    }

    /**
     * Get remaining claims
     */
    public function getRemainingClaims(): ?int
    {
        if (!$this->max_claims_total) {
            return null; // Unlimited
        }

        return max(0, $this->max_claims_total - $this->current_claims);
    }

    /**
     * Calculate bonus amount
     */
    public function calculateBonusAmount(float $depositAmount = 0): float
    {
        if ($this->type === 'bonus' || $this->type === 'cashback') {
            return $this->value;
        }

        if ($this->type === 'reload' && $this->percentage) {
            $bonus = $depositAmount * ($this->percentage / 100);
            
            if ($this->max_bonus) {
                $bonus = min($bonus, $this->max_bonus);
            }

            return $bonus;
        }

        return 0;
    }
}
