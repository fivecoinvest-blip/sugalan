<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class AdminUser extends Authenticatable implements JWTSubject
{
    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'role',
        'permissions',
        'ip_whitelist',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'ip_whitelist' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function processedDeposits(): HasMany
    {
        return $this->hasMany(Deposit::class, 'processed_by');
    }

    public function processedWithdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'processed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'admin_user_id');
    }

    // Permission helpers
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    // JWT methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'type' => 'admin',
        ];
    }
}
