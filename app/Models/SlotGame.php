<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotGame extends Model
{
    protected $fillable = [
        'provider_id',
        'game_code',
        'game_id',
        'name',
        'name_en',
        'thumbnail_url',
        'banner_url',
        'description',
        'category',
        'rtp',
        'volatility',
        'is_active',
        'is_featured',
        'is_new',
        'sort_order',
        'supported_languages',
        'supported_currencies',
        'metadata',
    ];

    protected $casts = [
        'rtp' => 'decimal:2',
        'volatility' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'sort_order' => 'integer',
        'supported_languages' => 'array',
        'supported_currencies' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the provider
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(GameProvider::class, 'provider_id');
    }

    /**
     * Get the bets for this game
     */
    public function bets(): HasMany
    {
        return $this->hasMany(SlotBet::class);
    }

    /**
     * Scope: Only active games
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    /**
     * Scope: Order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
