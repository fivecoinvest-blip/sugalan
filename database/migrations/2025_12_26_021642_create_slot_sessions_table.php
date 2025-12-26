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
        Schema::create('slot_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('game_id')->constrained('slot_games');
            $table->foreignId('provider_id')->constrained('slot_providers');
            $table->string('session_token', 500);
            $table->text('game_url')->nullable();
            $table->decimal('initial_balance', 15, 2);
            $table->decimal('final_balance', 15, 2)->nullable();
            $table->decimal('total_bets', 15, 2)->default(0);
            $table->decimal('total_wins', 15, 2)->default(0);
            $table->integer('rounds_played')->default(0);
            $table->enum('status', ['active', 'ended', 'expired'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_sessions');
    }
};
