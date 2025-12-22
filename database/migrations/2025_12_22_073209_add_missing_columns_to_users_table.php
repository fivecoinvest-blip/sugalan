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
        Schema::table('users', function (Blueprint $table) {
            // Add auth_method column
            $table->enum('auth_method', ['phone', 'metamask', 'telegram', 'guest'])
                ->default('phone')
                ->after('password');
            
            // Add referral columns
            $table->string('referral_code', 8)->unique()->nullable()->after('status');
            $table->string('referred_by', 8)->nullable()->after('referral_code');
            
            // Add phone_verified_at timestamp
            $table->timestamp('phone_verified_at')->nullable()->after('is_phone_verified');
            
            // Add indexes
            $table->index('referral_code');
            $table->index('referred_by');
            $table->index('auth_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auth_method', 'referral_code', 'referred_by', 'phone_verified_at']);
        });
    }
};
