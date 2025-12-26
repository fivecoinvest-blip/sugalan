#!/bin/bash
# Test JILI Game Launch through AYUT API

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Testing JILI Game Launch"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

php artisan tinker --execute="
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SlotGameService;

echo '1. Checking AYUT Provider...' . PHP_EOL;
\$provider = SlotProvider::where('code', 'AYUT')->first();
if (!\$provider) {
    echo '❌ AYUT provider not found!' . PHP_EOL;
    exit(1);
}
echo '✓ Provider: ' . \$provider->name . PHP_EOL;
echo '  API URL: ' . \$provider->api_url . PHP_EOL;
echo '  Status: ' . (\$provider->is_active ? 'Active' : 'Inactive') . PHP_EOL;
echo '' . PHP_EOL;

echo '2. Checking JILI Games...' . PHP_EOL;
\$game = SlotGame::where('provider_id', \$provider->id)
    ->where('is_active', true)
    ->first();
    
if (!\$game) {
    echo '❌ No active JILI games found!' . PHP_EOL;
    exit(1);
}
echo '✓ Test Game: ' . \$game->name . PHP_EOL;
echo '  Game ID: ' . \$game->game_id . PHP_EOL;
echo '  Category: ' . \$game->category . PHP_EOL;
\$metadata = json_decode(\$game->metadata, true);
echo '  Manufacturer: ' . (\$metadata['manufacturer'] ?? 'N/A') . PHP_EOL;
echo '  Game Code: ' . (\$metadata['game_code'] ?? 'N/A') . PHP_EOL;
echo '' . PHP_EOL;

echo '3. Checking Test User...' . PHP_EOL;
\$user = User::where('email', 'test@example.com')->first();
if (!\$user) {
    echo '  Creating test user...' . PHP_EOL;
    \$user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'phone' => '+639123456789',
        'email_verified_at' => now(),
    ]);
    echo '  ✓ Test user created' . PHP_EOL;
} else {
    echo '  ✓ Test user found: ' . \$user->name . PHP_EOL;
}
echo '' . PHP_EOL;

echo '4. Checking User Wallet...' . PHP_EOL;
\$wallet = Wallet::where('user_id', \$user->id)->first();
if (!\$wallet) {
    echo '  Creating wallet...' . PHP_EOL;
    \$wallet = Wallet::create([
        'user_id' => \$user->id,
        'real_balance' => 10000.00,
        'bonus_balance' => 0.00,
        'currency' => 'PHP',
    ]);
    echo '  ✓ Wallet created with PHP 10,000' . PHP_EOL;
} else {
    echo '  ✓ Wallet found' . PHP_EOL;
    echo '  Real Balance: PHP ' . number_format(\$wallet->real_balance, 2) . PHP_EOL;
    echo '  Bonus Balance: PHP ' . number_format(\$wallet->bonus_balance, 2) . PHP_EOL;
}
echo '' . PHP_EOL;

echo '5. Testing Game Launch...' . PHP_EOL;
echo '  Generating session token...' . PHP_EOL;
\$sessionToken = bin2hex(random_bytes(32));
echo '  Session Token: ' . substr(\$sessionToken, 0, 16) . '...' . PHP_EOL;
echo '  Calling SlotGameService::generateLaunchUrl()...' . PHP_EOL;
echo '' . PHP_EOL;

try {
    \$service = app(SlotGameService::class);
    \$launchUrl = \$service->generateLaunchUrl(\$game, \$user, \$sessionToken, false);
    
    echo '✓ Game Launch Successful!' . PHP_EOL;
    echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
    echo 'Launch URL: ' . \$launchUrl . PHP_EOL;
    echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
    echo '' . PHP_EOL;
    echo 'Session Details:' . PHP_EOL;
    echo '  Session Token: ' . \$sessionToken . PHP_EOL;
    echo '  Provider: ' . \$provider->name . PHP_EOL;
    echo '  Game: ' . \$game->name . PHP_EOL;
    echo '  Player: ' . \$provider->player_prefix . \$user->id . PHP_EOL;
    echo '' . PHP_EOL;
    echo '✅ ALL TESTS PASSED' . PHP_EOL;
    echo '' . PHP_EOL;
    echo 'You can now:' . PHP_EOL;
    echo '1. Copy the launch URL above and open in browser' . PHP_EOL;
    echo '2. Test via API: POST /api/slots/games/' . \$game->id . '/launch' . PHP_EOL;
    echo '3. Test in admin panel: /admin/slots/games' . PHP_EOL;
    
} catch (\Exception \$e) {
    echo '❌ Game Launch Failed!' . PHP_EOL;
    echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
    echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
    echo '' . PHP_EOL;
    echo 'Debug Info:' . PHP_EOL;
    echo '  File: ' . \$e->getFile() . PHP_EOL;
    echo '  Line: ' . \$e->getLine() . PHP_EOL;
    echo '' . PHP_EOL;
    echo 'Possible Issues:' . PHP_EOL;
    echo '1. AYUT API credentials incorrect' . PHP_EOL;
    echo '2. Network connectivity to https://jsgame.live' . PHP_EOL;
    echo '3. Encryption/decryption issues' . PHP_EOL;
    echo '4. Game UID not recognized by AYUT' . PHP_EOL;
    echo '' . PHP_EOL;
    echo 'Check logs: tail -f storage/logs/laravel.log' . PHP_EOL;
}
"
