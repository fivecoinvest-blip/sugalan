<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SlotGame Model
 * 
 * Represents a slot game in the system.
 * 
 * Architecture:
 * - provider_id: References the aggregator (e.g., AYUT API)
 * - manufacturer: The actual game provider/manufacturer (e.g., JILI, PG Soft, Pragmatic Play)
 * - category: Game type (e.g., slots, table, crash)
 */
class SlotGame extends Model
{
    protected $fillable = [
        'provider_id',
        'game_id',
        'name',
        'category',
        'manufacturer',
        'thumbnail_url',
        'min_bet',
        'max_bet',
        'rtp',
        'volatility',
        'lines',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'min_bet' => 'decimal:2',
        'max_bet' => 'decimal:2',
        'rtp' => 'decimal:2',
        'lines' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the thumbnail URL with /storage/ prefix
     */
    public function getThumbnailUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        // If already has http(s):// or starts with /storage/, return as-is
        if (str_starts_with($value, 'http://') || 
            str_starts_with($value, 'https://') || 
            str_starts_with($value, '/storage/')) {
            return $value;
        }

        // Otherwise, prepend /storage/
        return '/storage/' . $value;
    }

    /**
     * Get the aggregator provider (e.g., AYUT)
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(SlotProvider::class, 'provider_id');
    }

    /**
     * Get sessions for this game
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SlotSession::class, 'game_id');
    }

    /**
     * Scope: Only active games
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By manufacturer/game provider
     */
    public function scopeManufacturer($query, $manufacturer)
    {
        return $query->where('manufacturer', $manufacturer);
    }

    /**
     * Scope: Featured games
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: New games
     */
    public function scopeNew($query)
    {
        return $query->where('is_new', true);
    }

    /**
     * Scope: By category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
