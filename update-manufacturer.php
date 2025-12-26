<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SlotGame;
use Illuminate\Support\Facades\DB;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Updating Manufacturer Field\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

DB::transaction(function() {
    $games = SlotGame::all();
    $updated = 0;
    
    foreach ($games as $game) {
        if (!empty($game->metadata['manufacturer'])) {
            $game->manufacturer = $game->metadata['manufacturer'];
            $game->save();
            $updated++;
        }
    }
    
    echo "✓ Updated {$updated} games with manufacturer field\n\n";
});

// Summary
$jiliGames = SlotGame::where('manufacturer', 'JILI')->count();
$totalGames = SlotGame::count();

echo "Summary:\n";
echo "  Total games: {$totalGames}\n";
echo "  JILI games: {$jiliGames}\n\n";

echo "Sample games:\n";
$samples = SlotGame::where('manufacturer', 'JILI')->limit(5)->get();
foreach ($samples as $game) {
    echo "  - {$game->name} (Manufacturer: {$game->manufacturer}, Aggregator: AYUT)\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
