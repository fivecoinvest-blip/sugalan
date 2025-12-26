<?php

/**
 * Test Script for AYUT Slot Provider Integration
 * Tests the "Get Game URL (SEAMLESS)" endpoint
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Services\SlotGameService;
use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  AYUT Slot Provider - Game Launch API Test                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    // 1. Get AYUT Provider
    echo "1. Fetching AYUT provider...\n";
    $provider = SlotProvider::where('code', 'AYUT')->first();
    
    if (!$provider) {
        throw new Exception("AYUT provider not found. Run: php artisan db:seed --class=AyutSlotProviderSeeder");
    }
    
    echo "   ✓ Provider: {$provider->name}\n";
    echo "   ✓ API URL: {$provider->api_url}\n";
    echo "   ✓ Agency UID: {$provider->agency_uid}\n";
    echo "   ✓ Player Prefix: {$provider->player_prefix}\n\n";
    
    // 2. Check for test game
    echo "2. Checking for games...\n";
    $game = SlotGame::where('provider_id', $provider->id)
        ->where('is_active', true)
        ->first();
    
    if (!$game) {
        echo "   ⚠ No games found for AYUT provider\n";
        echo "   → Please sync games first:\n";
        echo "     • Via Admin Panel: /admin/slots/providers → Click 'Sync Games'\n";
        echo "     • Via API: POST /api/admin/slots/providers/{$provider->id}/sync\n\n";
        exit(1);
    }
    
    echo "   ✓ Test Game: {$game->name}\n";
    echo "   ✓ Game UID: {$game->game_id}\n\n";
    
    // 3. Get or create test user
    echo "3. Setting up test user...\n";
    $user = User::where('phone', '+639123456789')->first();
    
    if (!$user) {
        echo "   → Creating test user...\n";
        $user = User::create([
            'phone' => '+639123456789',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        
        // Create wallet
        $user->wallet()->create([
            'real_balance' => 1000.00,
            'bonus_balance' => 0.00,
        ]);
    }
    
    echo "   ✓ User ID: {$user->id}\n";
    echo "   ✓ Member Account: {$provider->player_prefix}" . sprintf('%04d', $user->id) . "\n";
    echo "   ✓ Balance: ₱" . number_format($user->wallet->real_balance, 2) . "\n\n";
    
    // 4. Test API Request Format
    echo "4. API Request Structure:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Endpoint: POST {$provider->api_url}/game/v1\n\n";
    
    echo "Request Body:\n";
    $timestamp = (string) (now()->getPreciseTimestamp(3));
    $memberAccount = $provider->player_prefix . sprintf('%04d', $user->id);
    
    echo json_encode([
        'agency_uid' => $provider->agency_uid,
        'timestamp' => $timestamp,
        'payload' => '(AES256 Encrypted JSON)',
    ], JSON_PRETTY_PRINT) . "\n\n";
    
    echo "Payload (Before Encryption):\n";
    $payloadData = [
        'timestamp' => $timestamp,
        'agency_uid' => $provider->agency_uid,
        'member_account' => $memberAccount,
        'game_uid' => $game->game_id,
        'credit_amount' => number_format($user->wallet->real_balance, 2, '.', ''),
        'currency_code' => 'PHP',
        'language' => 'en',
        'home_url' => config('app.frontend_url', 'http://localhost:3000') . '/slots',
        'platform' => 1,
        'callback_url' => config('app.url') . "/api/slots/callback/{$provider->code}/callback",
    ];
    echo json_encode($payloadData, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "Expected Response:\n";
    echo json_encode([
        'code' => 0,
        'msg' => '',
        'payload' => [
            'game_launch_url' => 'https://example.com/game/...',
        ],
    ], JSON_PRETTY_PRINT) . "\n\n";
    
    // 5. Attempt actual API call
    echo "5. Testing Live API Call...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $gameService = app(SlotGameService::class);
    $sessionToken = bin2hex(random_bytes(32));
    
    try {
        $launchUrl = $gameService->generateLaunchUrl(
            $game,
            $user,
            $sessionToken,
            false // Real money mode
        );
        
        echo "   ✓ SUCCESS! Game URL generated\n";
        echo "   ✓ Launch URL: {$launchUrl}\n\n";
        
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║  ✓ Integration Test PASSED                                   ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Next Steps:\n";
        echo "• Copy the launch URL and open in browser\n";
        echo "• Test game play with real money balance\n";
        echo "• Monitor callback requests in logs\n\n";
        
    } catch (Exception $e) {
        echo "   ✗ API Call Failed\n";
        echo "   Error: {$e->getMessage()}\n\n";
        
        echo "Troubleshooting:\n";
        echo "• Verify API URL is accessible: {$provider->api_url}\n";
        echo "• Check agency_uid and aes_key are correct\n";
        echo "• Ensure game_uid exists in provider's system\n";
        echo "• Check Laravel logs: storage/logs/laravel.log\n\n";
        
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║  ✗ Integration Test FAILED                                   ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
        
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n✗ Test Failed: {$e->getMessage()}\n\n";
    exit(1);
}
