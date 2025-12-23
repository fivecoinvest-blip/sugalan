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
        Schema::create('slot_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('slot_game_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id')->unique(); // Provider's transaction ID
            $table->string('round_id')->nullable(); // Round identifier
            $table->decimal('bet_amount', 15, 2);
            $table->decimal('win_amount', 15, 2)->default(0);
            $table->decimal('payout', 15, 2)->default(0); // Net result (win - bet)
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->enum('balance_type', ['real', 'bonus'])->default('real');
            $table->json('game_data')->nullable(); // Game-specific data from provider
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('slot_game_id');
            $table->index('transaction_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_bets');
    }
};
