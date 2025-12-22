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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->enum('type', ['manual', 'automatic'])->default('manual');
            
            // Limits
            $table->decimal('min_deposit', 20, 2)->default(0);
            $table->decimal('max_deposit', 20, 2)->default(999999.99);
            $table->decimal('min_withdrawal', 20, 2)->default(0);
            $table->decimal('max_withdrawal', 20, 2)->default(999999.99);
            
            // Status
            $table->boolean('is_enabled')->default(true);
            $table->boolean('supports_deposits')->default(true);
            $table->boolean('supports_withdrawals')->default(true);
            
            // Display
            $table->integer('display_order')->default(0);
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
        Schema::dropIfExists('payment_methods');
    }
};
