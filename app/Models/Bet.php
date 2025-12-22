<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Bet extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'uuid',
        'user_id',
        'game_type',
        'game_id',
        'bet_amount',
        'balance_type',
        'multiplier',
        'payout',
        'profit',
        'result',
        'status',
        'target',
        'server_seed_hash',
        'client_seed',
        'nonce',
        'server_seed',
        'revealed_at',
        'game_result',
        'is_bonus_bet',
        'bonus_id',
        'wagering_contribution',
    ];

    protected function casts(): array
    {
        return [
            'bet_amount' => 'decimal:2',
            'multiplier' => 'decimal:2',
            'payout' => 'decimal:2',
            'profit' => 'decimal:2',
            'game_result' => 'array',
            'is_bonus_bet' => 'boolean',
            'revealed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($bet) {
            if (empty($bet->uuid)) {
                $bet->uuid = (string) Str::uuid();
            }
            if (empty($bet->game_id)) {
                $bet->game_id = uniqid('game_', true);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bonus(): BelongsTo
    {
        return $this->belongsTo(Bonus::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    // Status helpers
    public function isWin(): bool
    {
        return $this->status === 'win' && $this->profit > 0;
    }

    public function isLoss(): bool
    {
        return $this->status === 'loss' && $this->profit <= 0;
    }
}
