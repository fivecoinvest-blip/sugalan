<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SlotProvider;
use App\Models\SlotGame;
use App\Models\User;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "Testing AES Encryption Modes (CBC vs ECB)" . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL . PHP_EOL;

$provider = SlotProvider::where('code', 'AYUT')->first();
$game = SlotGame::where('provider_id', $provider->id)->first();
$user = User::where('email', 'test@example.com')->first();
$wallet = $user->wallet;

$key = $provider->aes_key; // fd1e3a6a4b3dc050c7f9238c49bf5f56
$memberAccount = $provider->player_prefix . sprintf('%04d', $user->id);

$payloadData = [
    'timestamp' => (string) now()->getPreciseTimestamp(3),
    'agency_uid' => $provider->agency_uid,
    'member_account' => $memberAccount,
    'game_uid' => $game->game_id,
    'credit_amount' => (string) number_format($wallet->real_balance, 2, '.', ''),
    'currency_code' => 'PHP',
    'language' => 'en',
    'home_url' => config('app.frontend_url') . '/slots',
    'platform' => 1,
    'callback_url' => config('app.url') . '/api/slots/callback/' . $provider->code . '/callback',
];

$jsonData = json_encode($payloadData);

echo "Payload Data:" . PHP_EOL;
echo json_encode($payloadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL;

// Test 1: AES-256-CBC (Current Implementation)
echo "═══ Test 1: AES-256-CBC (Current) ═══" . PHP_EOL;
$iv = random_bytes(16);
$encryptedCBC = openssl_encrypt($jsonData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
$encryptedCBCBase64 = base64_encode($iv . $encryptedCBC);
echo "Encrypted: " . substr($encryptedCBCBase64, 0, 100) . "..." . PHP_EOL;
echo "Length: " . strlen($encryptedCBCBase64) . " characters" . PHP_EOL . PHP_EOL;

// Test 2: AES-256-ECB (AYUT Documentation Shows This)
echo "═══ Test 2: AES-256-ECB (From AYUT Docs) ═══" . PHP_EOL;
$encryptedECB = openssl_encrypt($jsonData, 'AES-256-ECB', $key, OPENSSL_RAW_DATA);
$encryptedECBBase64 = base64_encode($encryptedECB);
echo "Encrypted: " . substr($encryptedECBBase64, 0, 100) . "..." . PHP_EOL;
echo "Length: " . strlen($encryptedECBBase64) . " characters" . PHP_EOL . PHP_EOL;

// Test both with AYUT API
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "Testing with AYUT API" . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL . PHP_EOL;

foreach (['CBC' => $encryptedCBCBase64, 'ECB' => $encryptedECBBase64] as $mode => $encrypted) {
    echo "Testing AES-256-{$mode}:" . PHP_EOL;
    
    $requestBody = [
        'agency_uid' => $provider->agency_uid,
        'timestamp' => (string) now()->getPreciseTimestamp(3),
        'payload' => $encrypted,
    ];
    
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(30)
            ->post($provider->api_url . '/game/v1', $requestBody);
        
        $responseData = $response->json();
        
        if (isset($responseData['code'])) {
            if ($responseData['code'] === 0) {
                echo "✅ SUCCESS! AES-256-{$mode} is correct!" . PHP_EOL;
                echo "Response: " . json_encode($responseData, JSON_PRETTY_PRINT) . PHP_EOL;
                break;
            } else {
                echo "❌ Error {$responseData['code']}: {$responseData['msg']}" . PHP_EOL;
            }
        }
    } catch (Exception $e) {
        echo "❌ Request failed: " . $e->getMessage() . PHP_EOL;
    }
    
    echo PHP_EOL;
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "Note: AYUT documentation shows PHP examples using" . PHP_EOL;
echo "'AES-256-ECB' mode, not 'AES-256-CBC'." . PHP_EOL;
echo "ECB mode doesn't use an IV (Initialization Vector)." . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
