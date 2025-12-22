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
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Bonus Details
            $table->enum('type', ['signup', 'reload', 'promotional', 'referral', 'cashback']);
            $table->string('name', 100)->nullable();
            $table->decimal('amount', 20, 2);
            
            // Wagering
            $table->decimal('wagering_requirement', 20, 2);
            $table->decimal('wagering_progress', 20, 2)->default(0);
            $table->integer('wagering_multiplier')->default(30);
            
            // Game Contributions
            $table->json('game_contributions')->nullable();
            $table->decimal('max_bet_amount', 20, 2)->nullable();
            
            // Status
            $table->enum('status', ['active', 'completed', 'expired', 'forfeited', 'cancelled'])->default('active');
            
            // Expiration
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Metadata
            $table->text('terms_conditions')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonuses');
    }
};
