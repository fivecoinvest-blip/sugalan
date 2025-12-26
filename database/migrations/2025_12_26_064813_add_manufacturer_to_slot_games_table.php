<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SlotGame;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Clarifies the architecture:
     * - provider_id = Aggregator (AYUT API)
     * - manufacturer = Game Provider (JILI, PG Soft, Pragmatic Play, etc.)
     * - category = Game Type (slots, table, crash, etc.)
     */
    public function up(): void
    {
        Schema::table('slot_games', function (Blueprint $table) {
            $table->string('manufacturer', 100)->nullable()->after('provider_id')
                ->comment('Game provider/manufacturer (JILI, PG Soft, etc.)');
            $table->index('manufacturer');
        });
        
        // Migrate existing data from metadata to manufacturer column
        $games = SlotGame::whereNotNull('metadata')->get();
        foreach ($games as $game) {
            if (isset($game->metadata['manufacturer'])) {
                $game->manufacturer = $game->metadata['manufacturer'];
                $game->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot_games', function (Blueprint $table) {
            $table->dropIndex(['manufacturer']);
            $table->dropColumn('manufacturer');
        });
    }
};
