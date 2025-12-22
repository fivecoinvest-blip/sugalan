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
        Schema::create('vip_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->integer('level')->unique();
            
            // Requirements
            $table->decimal('min_wagered_amount', 20, 2)->default(0);
            $table->decimal('min_deposit_amount', 20, 2)->default(0);
            
            // Benefits
            $table->decimal('bonus_multiplier', 5, 2)->default(1.00);
            $table->decimal('wagering_reduction', 5, 2)->default(0.00);
            $table->decimal('cashback_percentage', 5, 2)->default(0.00);
            
            // Withdrawal Limits
            $table->decimal('withdrawal_limit_daily', 20, 2)->default(999999.99);
            $table->decimal('withdrawal_limit_weekly', 20, 2)->default(9999999.99);
            $table->decimal('withdrawal_limit_monthly', 20, 2)->default(99999999.99);
            
            // Processing Speed
            $table->integer('withdrawal_processing_hours')->default(24);
            
            // Display
            $table->string('color', 7)->nullable();
            $table->string('icon_url')->nullable();
            $table->text('description')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_levels');
    }
};
