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
        Schema::table('referrals', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('referrals_referral_code_index');
            $table->dropIndex('referrals_referred_user_id_index');
            
            // Drop foreign key and unique constraint
            $table->dropForeign(['referred_user_id']);
            $table->dropUnique('referrals_referral_code_unique');
            $table->dropUnique('referrals_referred_user_id_unique');
            
            // Drop old columns
            $table->dropColumn(['referral_code', 'referred_user_id', 'reward_paid', 'reward_paid_at', 'total_referred', 'total_earned']);
            
            // Add new columns
            $table->string('uuid', 36)->unique()->after('id');
            $table->foreignId('referee_id')->after('referrer_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending')->after('reward_amount');
            $table->timestamp('rewarded_at')->nullable()->after('status');
            
            // Add indexes
            $table->index('referee_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Revert changes
            $table->dropColumn(['uuid', 'referee_id', 'status', 'rewarded_at']);
            
            // Restore old columns
            $table->string('referral_code', 20)->unique()->after('referrer_id');
            $table->foreignId('referred_user_id')->nullable()->unique()->constrained('users')->onDelete('set null')->after('referral_code');
            $table->boolean('reward_paid')->default(false)->after('reward_amount');
            $table->timestamp('reward_paid_at')->nullable()->after('reward_paid');
            $table->integer('total_referred')->default(0)->after('reward_paid_at');
            $table->decimal('total_earned', 20, 2)->default(0)->after('total_referred');
        });
    }
};
