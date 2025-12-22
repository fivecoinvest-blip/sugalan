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
        Schema::create('promotional_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('code')->unique();
            $table->enum('type', ['bonus', 'free_spins', 'cashback', 'reload', 'tournament'])->default('bonus');
            $table->decimal('value', 10, 2)->default(0); // Fixed amount for bonus/cashback
            $table->decimal('percentage', 5, 2)->nullable(); // Percentage for reload bonuses
            $table->decimal('min_deposit', 10, 2)->nullable(); // Minimum deposit required
            $table->decimal('max_bonus', 10, 2)->nullable(); // Maximum bonus cap
            $table->integer('wagering_multiplier')->default(25);
            $table->integer('min_vip_level')->default(1); // Bronze = 1
            $table->integer('max_vip_level')->nullable();
            $table->integer('max_claims_total')->nullable(); // Total claims across all users
            $table->integer('max_claims_per_user')->default(1);
            $table->integer('current_claims')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'paused', 'expired'])->default('active');
            $table->text('terms')->nullable();
            $table->json('config')->nullable(); // Additional configuration
            $table->timestamps();

            $table->index(['status', 'starts_at', 'expires_at']);
            $table->index('code');
        });

        // Pivot table for user campaign claims
        Schema::create('campaign_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotional_campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('bonus_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->index(['user_id', 'promotional_campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotional_campaigns');
    }
};
