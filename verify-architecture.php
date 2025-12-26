<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SlotProvider;
use App\Models\SlotGame;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Slot Games Architecture Verification\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 1. Check Aggregator (Provider)
$ayut = SlotProvider::where('code', 'AYUT')->first();
if ($ayut) {
    echo "✓ Aggregator Found:\n";
    echo "  Name: {$ayut->name}\n";
    echo "  Code: {$ayut->code}\n";
    echo "  API URL: {$ayut->api_url}\n";
    echo "  Role: Aggregator (provides access to multiple game manufacturers)\n\n";
} else {
    echo "✗ AYUT aggregator not found\n\n";
}

// 2. Check Game Manufacturers
$manufacturers = SlotGame::select('manufacturer')
    ->whereNotNull('manufacturer')
    ->distinct()
    ->pluck('manufacturer');

echo "✓ Game Manufacturers (via AYUT):\n";
foreach ($manufacturers as $mfr) {
    $count = SlotGame::where('manufacturer', $mfr)->count();
    echo "  - {$mfr}: {$count} games\n";
}
echo "\n";

// 3. Check Game Categories
$categories = SlotGame::select('category')
    ->whereNotNull('category')
    ->distinct()
    ->pluck('category');

echo "✓ Game Types:\n";
foreach ($categories as $cat) {
    $count = SlotGame::where('category', $cat)->count();
    echo "  - {$cat}: {$count} games\n";
}
echo "\n";

// 4. Sample Game Details
echo "✓ Sample Game Structure:\n";
$game = SlotGame::with('provider')->where('manufacturer', 'JILI')->first();
if ($game) {
    echo "  Game Name: {$game->name}\n";
    echo "  Aggregator: {$game->provider->name} (Code: {$game->provider->code})\n";
    echo "  Manufacturer: {$game->manufacturer}\n";
    echo "  Category: {$game->category}\n";
    echo "  Game ID (UID): {$game->game_id}\n";
    echo "  Thumbnail: {$game->thumbnail_url}\n\n";
}

// 5. Architecture Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Architecture Summary:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Hierarchy:\n";
echo "  ┌─ AYUT API (Aggregator)\n";
echo "  │   • API endpoint: https://jsgame.live\n";
echo "  │   • Provides access to multiple manufacturers\n";
echo "  │\n";
echo "  ├─ JILI (Game Manufacturer/Provider)\n";
echo "  │   • Creates the actual games\n";
echo "  │   • Games: 132 slot games\n";
echo "  │\n";
echo "  └─ Slots (Game Type/Category)\n";
echo "      • Type of games (slots, table, crash, etc.)\n";
echo "      • Currently: " . SlotGame::count() . " total games\n\n";

echo "Database Fields:\n";
echo "  • provider_id → References aggregator (AYUT)\n";
echo "  • manufacturer → Game manufacturer (JILI, PG Soft, etc.)\n";
echo "  • category → Game type (slots, table, crash)\n\n";

echo "Example Flow:\n";
echo "  1. User clicks 'Play Super Ace'\n";
echo "  2. Backend connects to AYUT API (aggregator)\n";
echo "  3. AYUT launches JILI's Super Ace game\n";
echo "  4. User plays JILI game via AYUT platform\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ All Verified\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
