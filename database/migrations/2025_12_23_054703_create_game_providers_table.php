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
        Schema::create('game_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'jili', 'pgsoft'
            $table->string('name'); // e.g., 'JILI Games', 'PG Soft'
            $table->string('api_provider')->default('softapi'); // Integration provider
            $table->string('brand_id')->nullable(); // SoftAPI brand ID
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable(); // Additional provider info
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_providers');
    }
};
