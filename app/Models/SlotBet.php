<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotBet extends Model
{
    protected $fillable = [
        'user_id',
        'slot_game_id',
        'transaction_id',
        'round_id',
        'bet_amount',
        'win_amount',
        'payout',
        'status',
        'balance_type',
        'game_data',
        'completed_at',
    ];

    protected $casts = [
        'bet_amount' => 'decimal:2',
        'win_amount' => 'decimal:2',
        'payout' => 'decimal:2',
        'game_data' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the slot game
     */
    public function slotGame(): BelongsTo
    {
        return $this->belongsTo(SlotGame::class);
    }

    /**
     * Scope: By status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Completed bets
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: For a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
