<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SlotGame;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Testing Thumbnail URL Accessor\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Get first 3 games
$games = SlotGame::limit(3)->get();

echo "Sample Games with Thumbnail URLs:\n\n";

foreach ($games as $game) {
    echo "Game: {$game->name}\n";
    echo "  Raw DB value: {$game->getAttributes()['thumbnail_url']}\n";
    echo "  Via accessor: {$game->thumbnail_url}\n";
    echo "  Full URL: http://127.0.0.1:8000{$game->thumbnail_url}\n";
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Checking if image files exist:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$game = $games->first();
$rawPath = $game->getAttributes()['thumbnail_url'];
$publicPath = public_path('storage/' . $rawPath);

echo "Looking for: {$publicPath}\n";
echo "File exists: " . (file_exists($publicPath) ? "YES ✓" : "NO ✗") . "\n\n";

if (!file_exists($publicPath)) {
    // Check the actual directory
    $dir = dirname($publicPath);
    echo "Directory exists: " . (is_dir($dir) ? "YES ✓" : "NO ✗") . "\n";
    
    if (is_dir($dir)) {
        $files = scandir($dir);
        echo "Files in directory: " . count($files) . "\n";
        echo "First 5 files:\n";
        foreach (array_slice($files, 2, 5) as $file) {
            echo "  - {$file}\n";
        }
    }
}
