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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Withdrawal Details
            $table->decimal('amount', 20, 2);
            $table->enum('payment_method', ['gcash'])->default('gcash');
            
            // GCash Details
            $table->string('gcash_number', 20);
            $table->string('gcash_name', 100)->nullable();
            
            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected', 'cancelled'])->default('pending');
            
            // Validation
            $table->boolean('wagering_complete')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->boolean('vip_limit_passed')->default(false);
            
            // Admin Action
            $table->foreignId('admin_id')->nullable()->constrained('admin_users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
