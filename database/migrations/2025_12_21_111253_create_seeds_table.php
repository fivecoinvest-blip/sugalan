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
        Schema::create('seeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Server Seed
            $table->string('server_seed', 64)->unique();
            $table->string('server_seed_hash', 64)->unique();
            
            // Client Seed
            $table->string('client_seed', 64);
            
            // Nonce
            $table->unsignedInteger('nonce')->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('revealed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('is_active');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seeds');
    }
};
