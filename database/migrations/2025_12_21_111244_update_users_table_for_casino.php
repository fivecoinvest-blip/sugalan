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
            // Add UUID
            $table->uuid('uuid')->unique()->after('id');
            
            // Make email nullable (for guest and web3 users)
            $table->string('email')->nullable()->change();
            
            // Authentication fields
            $table->string('phone_number', 20)->unique()->nullable()->after('email');
            $table->boolean('is_guest')->default(false)->after('password');
            $table->boolean('is_phone_verified')->default(false)->after('is_guest');
            $table->boolean('is_email_verified')->default(false)->after('is_phone_verified');
            
            // Web3 Authentication
            $table->string('wallet_address', 42)->unique()->nullable()->after('is_email_verified');
            $table->string('wallet_nonce')->nullable()->after('wallet_address');
            
            // Social Authentication
            $table->bigInteger('telegram_id')->unique()->nullable()->after('wallet_nonce');
            $table->string('telegram_username')->nullable()->after('telegram_id');
            
            // Profile
            $table->string('username', 50)->unique()->nullable()->after('name');
            $table->string('display_name', 100)->nullable()->after('username');
            $table->string('avatar_url')->nullable()->after('display_name');
            $table->string('country_code', 2)->nullable()->after('avatar_url');
            $table->string('currency', 3)->default('PHP')->after('country_code');
            
            // VIP & Status
            $table->foreignId('vip_level_id')->nullable()->constrained('vip_levels')->onDelete('set null')->after('currency');
            $table->enum('status', ['active', 'suspended', 'banned', 'closed'])->default('active')->after('vip_level_id');
            
            // Statistics
            $table->decimal('total_deposited', 20, 2)->default(0)->after('status');
            $table->decimal('total_withdrawn', 20, 2)->default(0)->after('total_deposited');
            $table->decimal('total_wagered', 20, 2)->default(0)->after('total_withdrawn');
            $table->decimal('total_won', 20, 2)->default(0)->after('total_wagered');
            $table->decimal('total_lost', 20, 2)->default(0)->after('total_won');
            
            // Security
            $table->timestamp('last_login_at')->nullable()->after('total_lost');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->boolean('two_factor_enabled')->default(false)->after('last_login_ip');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            
            // Soft deletes
            $table->softDeletes()->after('updated_at');
            
            // Indexes
            $table->index('phone_number');
            $table->index('wallet_address');
            $table->index('telegram_id');
            $table->index('status');
            $table->index('vip_level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'phone_number', 'is_guest', 'is_phone_verified', 'is_email_verified',
                'wallet_address', 'wallet_nonce', 'telegram_id', 'telegram_username',
                'username', 'display_name', 'avatar_url', 'country_code', 'currency',
                'vip_level_id', 'status', 'total_deposited', 'total_withdrawn', 'total_wagered',
                'total_won', 'total_lost', 'last_login_at', 'last_login_ip',
                'two_factor_enabled', 'two_factor_secret', 'deleted_at'
            ]);
        });
    }
};
