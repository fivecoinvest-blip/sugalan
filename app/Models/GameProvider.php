<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameProvider extends Model
{
    protected $fillable = [
        'code',
        'name',
        'api_provider',
        'brand_id',
        'logo_url',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the slot games for this provider
     */
    public function slotGames(): HasMany
    {
        return $this->hasMany(SlotGame::class, 'provider_id');
    }

    /**
     * Get active slot games
     */
    public function activeGames(): HasMany
    {
        return $this->slotGames()->where('is_active', true);
    }

    /**
     * Scope: Only active providers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
