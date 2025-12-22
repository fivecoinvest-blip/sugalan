<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'phone_encrypted',
        'email_encrypted',
        'phone_hash',
        'email_hash',
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
        
        // Encrypt sensitive data on save
        static::saving(function ($user) {
            $encryption = app(EncryptionService::class);
            
            // Encrypt phone if changed
            if ($user->isDirty('phone_number') && $user->phone_number) {
                $user->phone_encrypted = $encryption->encryptPhone($user->phone_number);
                $user->phone_hash = $encryption->hash($user->phone_number);
            }
            
            // Encrypt email if changed
            if ($user->isDirty('email') && $user->email) {
                $user->email_encrypted = $encryption->encryptEmail($user->email);
                $user->email_hash = $encryption->hash($user->email);
            }
        });
    }

    // Encrypted Accessors/Mutators
    protected function phoneNumber(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // If encrypted version exists, use it
                if ($this->phone_encrypted) {
                    return app(EncryptionService::class)->decryptPhone($this->phone_encrypted);
                }
                return $value;
            },
            set: fn ($value) => $value,
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // If encrypted version exists, use it
                if ($this->email_encrypted) {
                    return app(EncryptionService::class)->decryptEmail($this->email_encrypted);
                }
                return $value;
            },
            set: fn ($value) => $value,
        );
    }

    // Masked data for display
    public function getMaskedPhoneAttribute(): ?string
    {
        return app(EncryptionService::class)->maskPhone($this->phone_number);
    }

    public function getMaskedEmailAttribute(): ?string
    {
        return app(EncryptionService::class)->maskEmail($this->email);
    }

    // Search by encrypted data
    public static function findByPhone(string $phone): ?self
    {
        $hash = app(EncryptionService::class)->hash($phone);
        return static::where('phone_hash', $hash)->first();
    }

    public static function findByEmail(string $email): ?self
    {
        $hash = app(EncryptionService::class)->hash($email);
        return static::where('email_hash', $hash)->first();
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
