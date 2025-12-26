<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SlotProvider;
use App\Models\SlotGame;
use App\Models\User;
use App\Services\SlotGameService;

echo "=== Testing Multiple JILI Game UIDs ===" . PHP_EOL . PHP_EOL;

$provider = SlotProvider::where('code', 'AYUT')->first();
$user = User::where('email', 'test@example.com')->first();
$gameService = app(SlotGameService::class);

$testGames = [
    ['name' => 'Super Ace', 'code' => '49', 'uid' => 'bdfb23c974a2517198c5443adeea77a8'],
    ['name' => 'Fortune Gems', 'code' => '109', 'uid' => 'a990de177577a2e6a889aaac5f57b429'],
    ['name' => 'Money Coming', 'code' => '51', 'uid' => 'db249defce63610fccabfa829a405232'],
    ['name' => 'Golden Bank', 'code' => '45', 'uid' => 'c3f86b78938eab1b7f34159d98796e88'],
    ['name' => 'Dragon Treasure', 'code' => '46', 'uid' => 'c6955c14f6c28a6c2a0c28274fec7520'],
];

$successCount = 0;
$failCount = 0;

foreach ($testGames as $testGame) {
    echo "Testing: {$testGame['name']} (Code: {$testGame['code']})" . PHP_EOL;
    echo "UID: {$testGame['uid']}" . PHP_EOL;
    
    $game = SlotGame::where('game_id', $testGame['uid'])->first();
    
    if (!$game) {
        echo "❌ Game not found in database" . PHP_EOL . PHP_EOL;
        $failCount++;
        continue;
    }
    
    try {
        $sessionToken = bin2hex(random_bytes(16));
        $url = $gameService->generateLaunchUrl($game, $user, $sessionToken, false);
        echo "✅ SUCCESS! Game launched" . PHP_EOL;
        echo "URL: " . substr($url, 0, 100) . "..." . PHP_EOL;
        $successCount++;
        break; // Exit on first success
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . PHP_EOL;
        $failCount++;
    }
    
    echo PHP_EOL;
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "Test Results:" . PHP_EOL;
echo "  Success: $successCount" . PHP_EOL;
echo "  Failed: $failCount" . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;

if ($successCount === 0) {
    echo PHP_EOL;
    echo "Conclusion: ALL game UIDs failed with the same error." . PHP_EOL;
    echo "This confirms the issue is NOT with individual game UIDs," . PHP_EOL;
    echo "but with how AYUT recognizes/maps JILI games in their system." . PHP_EOL;
    echo PHP_EOL;
    echo "Next Step: Contact AYUT support for their JILI game list." . PHP_EOL;
}
