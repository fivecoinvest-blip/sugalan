<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'username',
        'email',
        'phone_number',
        'password',
        'wallet_address',
        'telegram_id',
        'telegram_username',
        'auth_method',
        'vip_level_id',
        'referral_code',
        'referred_by',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'total_deposited' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'total_wagered' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
            if (empty($user->referral_code)) {
                $user->referral_code = strtoupper(Str::random(8));
            }
        });
    }

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'uuid' => $this->uuid,
            'auth_method' => $this->auth_method,
            'vip_level' => $this->vip_level_id,
        ];
    }

    // Relationships
    public function vipLevel(): BelongsTo
    {
        return $this->belongsTo(VipLevel::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function seeds(): HasMany
    {
        return $this->hasMany(Seed::class);
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function vipPromotions()
    {
        return $this->belongsToMany(VipPromotion::class, 'vip_promotion_user')
            ->withPivot(['bonus_id', 'claimed_at'])
            ->withTimestamps();
    }

    public function promotionalCampaigns()
    {
        return $this->belongsToMany(PromotionalCampaign::class, 'campaign_user')
            ->withPivot(['bonus_id', 'claimed_at'])
            ->withTimestamps();
    }

    public function dailyRewards(): HasMany
    {
        return $this->hasMany(DailyReward::class);
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }
}
