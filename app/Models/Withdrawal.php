<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Withdrawal extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'amount',
        'gcash_number',
        'gcash_name',
        'gcash_reference',
        'status',
        'wagering_complete',
        'phone_verified',
        'vip_limit_passed',
        'admin_notes',
        'processed_by',
        'processed_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'wagering_complete' => 'boolean',
            'phone_verified' => 'boolean',
            'vip_limit_passed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function canProcess(): bool
    {
        return $this->wagering_complete && 
               $this->phone_verified && 
               $this->vip_limit_passed;
    }
}
