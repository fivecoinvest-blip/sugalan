<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SlotProvider Model
 * 
 * Represents a slot game aggregator (e.g., AYUT API).
 * An aggregator provides access to multiple game manufacturers/providers.
 * 
 * Example:
 * - AYUT (aggregator) → provides access to JILI, PG Soft, Pragmatic Play, etc.
 */
class SlotProvider extends Model
{
    protected $fillable = [
        'name',
        'code',
        'agency_uid',
        'aes_key',
        'player_prefix',
        'api_url',
        'callback_url',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    protected $hidden = [
        'aes_key',
    ];

    /**
     * Get games for this provider
     */
    public function games(): HasMany
    {
        return $this->hasMany(SlotGame::class, 'provider_id');
    }

    /**
     * Get active games for this provider
     */
    public function activeGames(): HasMany
    {
        return $this->games()->where('is_active', true);
    }

    /**
     * Get sessions for this provider
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SlotSession::class, 'provider_id');
    }

    /**
     * Scope to only active providers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get provider by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
