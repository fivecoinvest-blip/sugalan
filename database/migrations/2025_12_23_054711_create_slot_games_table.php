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
            $table->foreignId('provider_id')->constrained('game_providers')->onDelete('cascade');
            $table->string('game_code')->unique(); // Unique game identifier from provider
            $table->string('game_id'); // Provider's game ID
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // e.g., 'slots', 'table', 'fishing'
            $table->decimal('rtp', 5, 2)->nullable(); // Return to Player %
            $table->integer('volatility')->nullable(); // 1-5
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('supported_languages')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->json('metadata')->nullable(); // Additional game info
            $table->timestamps();
            
            $table->index('provider_id');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('category');
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
