<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SlotSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'game_id',
        'provider_id',
        'session_token',
        'game_url',
        'initial_balance',
        'final_balance',
        'total_bets',
        'total_wins',
        'rounds_played',
        'status',
        'started_at',
        'ended_at',
        'expires_at',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'final_balance' => 'decimal:2',
        'total_bets' => 'decimal:2',
        'total_wins' => 'decimal:2',
        'rounds_played' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($session) {
            if (empty($session->uuid)) {
                $session->uuid = Str::uuid();
            }
            if (empty($session->started_at)) {
                $session->started_at = now();
            }
            if (empty($session->expires_at)) {
                $session->expires_at = now()->addMinutes(30);
            }
        });
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the game
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(SlotGame::class, 'game_id');
    }

    /**
     * Get the provider
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(SlotProvider::class, 'provider_id');
    }

    /**
     * Get transactions for this session
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(SlotTransaction::class, 'session_id');
    }

    /**
     * Scope: Active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope: Expired sessions
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
                    ->where('expires_at', '<=', now());
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at > now();
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->status === 'active' && $this->expires_at <= now();
    }

    /**
     * End the session
     */
    public function end(float $finalBalance): void
    {
        $this->update([
            'status' => 'ended',
            'final_balance' => $finalBalance,
            'ended_at' => now(),
        ]);
    }

    /**
     * Mark session as expired
     */
    public function expire(): void
    {
        if ($this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
    }
}
