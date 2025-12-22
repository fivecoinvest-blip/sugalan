<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Bonus extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'amount',
        'wagering_requirement',
        'wagering_progress',
        'status',
        'expires_at',
        'activated_at',
        'completed_at',
        'cancelled_at',
        'source_type',
        'source_id',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'wagering_requirement' => 'decimal:2',
            'wagering_progress' => 'decimal:2',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    // Status helpers
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    public function getWageringPercentage(): float
    {
        if ($this->wagering_requirement <= 0) {
            return 100.0;
        }
        return min(100.0, ($this->wagering_progress / $this->wagering_requirement) * 100);
    }

    public function getRemainingWagering(): float
    {
        return max(0, $this->wagering_requirement - $this->wagering_progress);
    }
}
