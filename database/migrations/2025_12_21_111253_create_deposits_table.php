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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Deposit Details
            $table->decimal('amount', 20, 2);
            $table->enum('payment_method', ['gcash'])->default('gcash');
            
            // GCash Details
            $table->foreignId('gcash_account_id')->nullable()->constrained('gcash_accounts')->onDelete('set null');
            $table->string('reference_number', 100)->nullable();
            $table->string('screenshot_url')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            
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
        Schema::dropIfExists('deposits');
    }
};
