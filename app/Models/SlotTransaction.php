<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SlotTransaction extends Model
{
    protected $fillable = [
        'uuid',
        'session_id',
        'user_id',
        'wallet_id',
        'transaction_id',
        'round_id',
        'external_txn_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'game_data',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'game_data' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the session
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SlotSession::class, 'session_id');
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the core transaction
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Scope: Bets only
     */
    public function scopeBets($query)
    {
        return $query->where('type', 'bet');
    }

    /**
     * Scope: Wins only
     */
    public function scopeWins($query)
    {
        return $query->where('type', 'win');
    }

    /**
     * Scope: Rollbacks only
     */
    public function scopeRollbacks($query)
    {
        return $query->where('type', 'rollback');
    }

    /**
     * Scope: By round
     */
    public function scopeByRound($query, string $roundId)
    {
        return $query->where('round_id', $roundId);
    }

    /**
     * Find by external transaction ID
     */
    public static function findByExternalId(string $externalTxnId): ?self
    {
        return static::where('external_txn_id', $externalTxnId)->first();
    }

    /**
     * Check if transaction is a bet
     */
    public function isBet(): bool
    {
        return $this->type === 'bet';
    }

    /**
     * Check if transaction is a win
     */
    public function isWin(): bool
    {
        return $this->type === 'win';
    }

    /**
     * Check if transaction is a rollback
     */
    public function isRollback(): bool
    {
        return $this->type === 'rollback';
    }
}
