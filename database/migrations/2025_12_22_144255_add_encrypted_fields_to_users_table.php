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
        Schema::table('users', function (Blueprint $table) {
            // Add encrypted columns for sensitive data
            $table->text('phone_encrypted')->nullable()->after('phone');
            $table->text('email_encrypted')->nullable()->after('email');
            
            // Index for encrypted lookups (we'll hash for searching)
            $table->string('phone_hash')->nullable()->after('phone_encrypted');
            $table->string('email_hash')->nullable()->after('email_encrypted');
            $table->index('phone_hash');
            $table->index('email_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_hash']);
            $table->dropIndex(['email_hash']);
            $table->dropColumn(['phone_encrypted', 'email_encrypted', 'phone_hash', 'email_hash']);
        });
    }
};
