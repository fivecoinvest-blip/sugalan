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
        Schema::create('vip_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('type'); // bonus, free_spins, cashback, tournament
            $table->integer('min_vip_level')->default(1); // Minimum VIP level required
            $table->integer('max_vip_level')->nullable(); // Optional max level (for tier-specific promos)
            
            // Promotion value (depends on type)
            $table->decimal('value', 15, 2)->default(0); // Amount for bonus/cashback
            $table->decimal('percentage', 5, 2)->nullable(); // Percentage for deposit bonuses
            $table->integer('wagering_multiplier')->default(20); // Wagering requirement
            
            // Validity period
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            
            // Usage limits
            $table->integer('max_uses')->nullable(); // Max total uses (null = unlimited)
            $table->integer('max_uses_per_user')->default(1); // Max uses per user
            $table->integer('current_uses')->default(0); // Current usage count
            
            // Status
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            
            // Terms and conditions
            $table->text('terms')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('min_vip_level');
            $table->index('status');
            $table->index(['starts_at', 'expires_at']);
        });

        // Pivot table for tracking user claims
        Schema::create('vip_promotion_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vip_promotion_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('bonus_id')->nullable()->constrained()->onDelete('set null'); // Link to awarded bonus
            $table->timestamp('claimed_at');
            
            // Indexes
            $table->unique(['vip_promotion_id', 'user_id']);
            $table->index('claimed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_promotion_user');
        Schema::dropIfExists('vip_promotions');
    }
};
