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
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Game Details
            $table->enum('game_type', ['dice', 'hilo', 'mines', 'plinko', 'keno', 'wheel', 'pump', 'crash']);
            $table->string('game_id', 50);
            
            // Bet Details
            $table->decimal('bet_amount', 20, 2);
            $table->enum('balance_type', ['real', 'bonus'])->default('real');
            $table->boolean('is_bonus_bet')->default(false);
            $table->decimal('multiplier', 10, 4)->default(1.0000);
            $table->decimal('payout', 20, 2)->default(0);
            $table->decimal('profit', 20, 2)->default(0);
            
            // Result
            $table->enum('result', ['win', 'loss', 'push'])->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->json('game_result');
            
            // Provably Fair
            $table->string('server_seed_hash', 64);
            $table->string('client_seed', 64);
            $table->unsignedInteger('nonce');
            $table->string('server_seed', 64)->nullable();
            $table->timestamp('revealed_at')->nullable();
            
            // Bonus Wagering
            $table->foreignId('bonus_id')->nullable()->constrained('bonuses')->onDelete('set null');
            $table->decimal('wagering_contribution', 20, 2)->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('game_type');
            $table->index('created_at');
            $table->index(['user_id', 'game_type']);
            $table->index(['user_id', 'created_at']);
            $table->index('bonus_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
