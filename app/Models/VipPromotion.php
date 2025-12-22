<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VipPromotion extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'min_vip_level',
        'max_vip_level',
        'value',
        'percentage',
        'wagering_multiplier',
        'starts_at',
        'expires_at',
        'max_uses',
        'max_uses_per_user',
        'current_uses',
        'status',
        'terms',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'percentage' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Users who have claimed this promotion
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vip_promotion_user')
            ->withPivot(['bonus_id', 'claimed_at'])
            ->withTimestamps();
    }

    /**
     * Check if promotion is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' 
            && now()->between($this->starts_at, $this->expires_at)
            && ($this->max_uses === null || $this->current_uses < $this->max_uses);
    }

    /**
     * Check if user is eligible for this promotion
     */
    public function isEligibleFor(User $user): bool
    {
        // Check VIP level
        $userLevel = $user->vipLevel->level;
        if ($userLevel < $this->min_vip_level) {
            return false;
        }
        
        if ($this->max_vip_level !== null && $userLevel > $this->max_vip_level) {
            return false;
        }

        // Check if promotion is active
        if (!$this->isActive()) {
            return false;
        }

        // Check per-user usage limit
        $userClaimCount = $this->users()->where('user_id', $user->id)->count();
        if ($userClaimCount >= $this->max_uses_per_user) {
            return false;
        }

        return true;
    }

    /**
     * Scope: Active promotions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->where(function ($q) {
                $q->whereNull('max_uses')
                  ->orWhereRaw('current_uses < max_uses');
            });
    }

    /**
     * Scope: For specific VIP level
     */
    public function scopeForVipLevel($query, int $level)
    {
        return $query->where('min_vip_level', '<=', $level)
            ->where(function ($q) use ($level) {
                $q->whereNull('max_vip_level')
                  ->orWhere('max_vip_level', '>=', $level);
            });
    }
}
