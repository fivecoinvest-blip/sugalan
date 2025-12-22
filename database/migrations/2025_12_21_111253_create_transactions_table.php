<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Transaction Details
            $table->enum('type', [
                'deposit', 'withdrawal', 'bet', 'win', 'refund',
                'bonus_credit', 'bonus_conversion', 'cashback',
                'referral_reward', 'admin_adjustment'
            ]);
            $table->decimal('amount', 20, 2);
            $table->enum('balance_type', ['real', 'bonus'])->default('real');
            
            // Balance Tracking
            $table->decimal('balance_before', 20, 2);
            $table->decimal('balance_after', 20, 2);
            
            // Status
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            
            // References
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Metadata
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
            $table->index('created_at');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
