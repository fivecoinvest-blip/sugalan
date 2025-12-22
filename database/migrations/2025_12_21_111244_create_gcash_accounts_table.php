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
        Schema::create('gcash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name', 100);
            $table->string('account_number', 20)->unique();
            
            // Limits
            $table->decimal('daily_limit', 20, 2)->default(999999.99);
            $table->decimal('monthly_limit', 20, 2)->default(9999999.99);
            
            // Tracking
            $table->decimal('daily_received', 20, 2)->default(0);
            $table->decimal('monthly_received', 20, 2)->default(0);
            $table->date('last_reset_date')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gcash_accounts');
    }
};
