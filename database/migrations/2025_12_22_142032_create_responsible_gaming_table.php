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
        Schema::create('responsible_gaming', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Deposit limits
            $table->decimal('daily_deposit_limit', 10, 2)->nullable();
            $table->decimal('weekly_deposit_limit', 10, 2)->nullable();
            $table->decimal('monthly_deposit_limit', 10, 2)->nullable();
            
            // Wager limits
            $table->decimal('daily_wager_limit', 10, 2)->nullable();
            $table->decimal('weekly_wager_limit', 10, 2)->nullable();
            $table->decimal('monthly_wager_limit', 10, 2)->nullable();
            
            // Loss limits
            $table->decimal('daily_loss_limit', 10, 2)->nullable();
            $table->decimal('weekly_loss_limit', 10, 2)->nullable();
            $table->decimal('monthly_loss_limit', 10, 2)->nullable();
            
            // Session limits
            $table->integer('session_duration_limit')->nullable()->comment('Minutes');
            $table->integer('reality_check_interval')->default(60)->comment('Minutes');
            
            // Self-exclusion
            $table->enum('self_exclusion_status', ['none', 'temporary', 'permanent'])->default('none');
            $table->timestamp('self_exclusion_start')->nullable();
            $table->timestamp('self_exclusion_end')->nullable();
            $table->text('self_exclusion_reason')->nullable();
            
            // Cool-off period
            $table->timestamp('cool_off_until')->nullable();
            
            // Activity tracking
            $table->timestamp('last_reality_check')->nullable();
            $table->timestamp('last_session_start')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('self_exclusion_status');
        });
        
        // Deposit tracking for limits
        Schema::create('deposit_limit_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('period', ['daily', 'weekly', 'monthly']);
            $table->date('tracking_date');
            $table->timestamps();
            
            $table->index(['user_id', 'period', 'tracking_date']);
        });
        
        // Wager tracking for limits
        Schema::create('wager_limit_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('period', ['daily', 'weekly', 'monthly']);
            $table->date('tracking_date');
            $table->timestamps();
            
            $table->index(['user_id', 'period', 'tracking_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wager_limit_tracking');
        Schema::dropIfExists('deposit_limit_tracking');
        Schema::dropIfExists('responsible_gaming');
    }
};
