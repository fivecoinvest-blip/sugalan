<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SlotProvider;
use App\Models\SlotGame;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Checking AYUT Provider Status\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Check provider
$provider = SlotProvider::where('code', 'AYUT')->first();

if (!$provider) {
    echo "❌ AYUT provider not found in database!\n";
    exit(1);
}

echo "Provider Information:\n";
echo "  Name: {$provider->name}\n";
echo "  Code: {$provider->code}\n";
echo "  Active: " . ($provider->is_active ? "YES ✓" : "NO ✗") . "\n";
echo "  API URL: {$provider->api_url}\n\n";

// Check games
$totalGames = SlotGame::where('provider_id', $provider->id)->count();
$activeGames = SlotGame::where('provider_id', $provider->id)->where('is_active', true)->count();

echo "Games Information:\n";
echo "  Total JILI Games: {$totalGames}\n";
echo "  Active Games: {$activeGames}\n\n";

// Check if provider is inactive
if (!$provider->is_active) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "❌ PROBLEM FOUND: Provider is INACTIVE!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "This is why games are not showing in frontend.\n";
    echo "The SlotGameService filters games by active providers.\n\n";
    echo "Fixing now...\n";
    
    $provider->is_active = true;
    $provider->save();
    
    echo "✓ Provider activated!\n\n";
    echo "Please clear Laravel cache:\n";
    echo "  php artisan cache:clear\n\n";
} else {
    echo "✓ Provider is active - this is not the issue.\n\n";
}

// Test service method
echo "Testing SlotGameService::getAllGames()...\n";
$service = app(App\Services\SlotGameService::class);
$games = $service->getAllGames();

echo "  Games returned: " . count($games) . "\n";

if (count($games) === 0) {
    echo "\n❌ Service returns 0 games even though database has {$activeGames} active games!\n";
    echo "This suggests the cache might need clearing or provider was inactive.\n";
} else {
    echo "\n✓ Service is working correctly!\n";
    echo "\nSample games:\n";
    foreach (array_slice($games, 0, 3) as $game) {
        echo "  - {$game['name']}\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Diagnostic Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
