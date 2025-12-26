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
        Schema::create('slot_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('slot_providers');
            $table->string('game_id', 100);
            $table->string('name', 255);
            $table->string('category', 50)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->decimal('min_bet', 15, 2)->default(1.00);
            $table->decimal('max_bet', 15, 2)->default(10000.00);
            $table->decimal('rtp', 5, 2)->nullable();
            $table->string('volatility', 20)->nullable();
            $table->integer('lines')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'game_id']);
            $table->index('provider_id');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_games');
    }
};
