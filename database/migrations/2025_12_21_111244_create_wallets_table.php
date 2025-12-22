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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            
            // Balances
            $table->decimal('real_balance', 20, 2)->default(0);
            $table->decimal('bonus_balance', 20, 2)->default(0);
            $table->decimal('locked_balance', 20, 2)->default(0);
            
            // Statistics
            $table->decimal('lifetime_deposits', 20, 2)->default(0);
            $table->decimal('lifetime_withdrawals', 20, 2)->default(0);
            $table->decimal('lifetime_wagered', 20, 2)->default(0);
            $table->decimal('lifetime_won', 20, 2)->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
