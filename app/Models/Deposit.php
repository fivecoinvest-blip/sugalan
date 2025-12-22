<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deposit extends Model
{
    protected $fillable = [
        'user_id',
        'gcash_account_id',
        'amount',
        'reference_number',
        'screenshot_url',
        'status',
        'notes',
        'admin_notes',
        'processed_by',
        'processed_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gcashAccount(): BelongsTo
    {
        return $this->belongsTo(GcashAccount::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'processed_by');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
