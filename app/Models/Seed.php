<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Seed extends Model
{
    protected $fillable = [
        'user_id',
        'server_seed',
        'server_seed_hash',
        'client_seed',
        'nonce',
        'is_active',
        'revealed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'revealed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($seed) {
            if (empty($seed->server_seed)) {
                $seed->server_seed = Str::random(64);
                $seed->server_seed_hash = hash('sha256', $seed->server_seed);
            }
            if (empty($seed->client_seed)) {
                $seed->client_seed = Str::random(16);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incrementNonce(): int
    {
        $this->increment('nonce');
        return $this->nonce;
    }

    public function reveal(): void
    {
        $this->update([
            'is_active' => false,
            'revealed_at' => now(),
        ]);
    }
}
