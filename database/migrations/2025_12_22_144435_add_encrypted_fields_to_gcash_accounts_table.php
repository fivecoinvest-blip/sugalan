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
        Schema::table('gcash_accounts', function (Blueprint $table) {
            // Add encrypted column for account number
            $table->text('account_number_encrypted')->nullable()->after('account_number');
            $table->string('account_number_hash')->nullable()->after('account_number_encrypted');
            $table->index('account_number_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gcash_accounts', function (Blueprint $table) {
            $table->dropIndex(['account_number_hash']);
            $table->dropColumn(['account_number_encrypted', 'account_number_hash']);
        });
    }
};
