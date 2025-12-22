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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            
            // Referrer
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->string('referral_code', 20)->unique();
            
            // Referred User
            $table->foreignId('referred_user_id')->nullable()->unique()->constrained('users')->onDelete('set null');
            
            // Reward
            $table->decimal('reward_amount', 20, 2)->default(0);
            $table->boolean('reward_paid')->default(false);
            $table->timestamp('reward_paid_at')->nullable();
            
            // Statistics
            $table->integer('total_referred')->default(0);
            $table->decimal('total_earned', 20, 2)->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index('referrer_id');
            $table->index('referral_code');
            $table->index('referred_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
