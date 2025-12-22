<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false; // Only has created_at
    
    protected $fillable = [
        'user_id',
        'admin_id',
        'actor_type',
        'action',
        'resource_type',
        'resource_id',
        'description',
        'changes',
        'metadata',
        'ip_address',
        'user_agent',
        'request_url',
        'request_method',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
