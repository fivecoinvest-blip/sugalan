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
        Schema::create('slot_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('session_id')->constrained('slot_sessions');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->string('round_id', 100);
            $table->string('external_txn_id', 100)->unique();
            $table->enum('type', ['bet', 'win', 'rollback']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->json('game_data')->nullable();
            $table->enum('status', ['pending', 'completed', 'rolled_back'])->default('completed');
            $table->timestamps();

            $table->index('session_id');
            $table->index('user_id');
            $table->index('round_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_transactions');
    }
};
