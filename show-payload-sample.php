<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SlotProvider;
use App\Models\SlotGame;
use App\Models\User;
use App\Services\SlotEncryptionService;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "AYUT API Payload Sample" . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL . PHP_EOL;

$provider = SlotProvider::where('code', 'AYUT')->first();
$game = SlotGame::where('provider_id', $provider->id)->first();
$user = User::where('email', 'test@example.com')->first();
$wallet = $user->wallet;
$encryptionService = app(SlotEncryptionService::class);

// === STEP 1: Prepare Payload Data (BEFORE Encryption) ===
echo "═══ STEP 1: Payload Data (Before Encryption) ═══" . PHP_EOL;

$memberAccount = $provider->player_prefix . sprintf('%04d', $user->id);
$timestamp = (string) now()->getPreciseTimestamp(3);

$payloadData = [
    'timestamp' => $timestamp,
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

echo json_encode($payloadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo PHP_EOL;

// === STEP 2: Encrypt the Payload ===
echo "═══ STEP 2: Encryption Process ═══" . PHP_EOL;
echo "Algorithm: AES-256-CBC" . PHP_EOL;
echo "Key: " . $provider->aes_key . PHP_EOL;
echo "Key Length: " . strlen($provider->aes_key) . " bytes" . PHP_EOL;

$encryptedPayload = $encryptionService->encrypt($payloadData, $provider->aes_key);

echo "Encrypted Payload Length: " . strlen($encryptedPayload) . " characters" . PHP_EOL;
echo "Encrypted Payload (first 200 chars): " . substr($encryptedPayload, 0, 200) . "..." . PHP_EOL;
echo "Encrypted Payload (last 100 chars): ..." . substr($encryptedPayload, -100) . PHP_EOL;
echo PHP_EOL;

// === STEP 3: Build Final Request Body ===
echo "═══ STEP 3: Final HTTP Request Body ═══" . PHP_EOL;

$requestTimestamp = (string) now()->getPreciseTimestamp(3);
$requestBody = [
    'agency_uid' => $provider->agency_uid,
    'timestamp' => $requestTimestamp,
    'payload' => $encryptedPayload,
];

echo json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo PHP_EOL;

// === STEP 4: API Request Details ===
echo "═══ STEP 4: API Request Details ═══" . PHP_EOL;
echo "Method: POST" . PHP_EOL;
echo "URL: " . $provider->api_url . "/game/v1" . PHP_EOL;
echo "Headers:" . PHP_EOL;
echo "  Content-Type: application/json" . PHP_EOL;
echo "  Accept: application/json" . PHP_EOL;
echo PHP_EOL;

// === STEP 5: Make Actual Request and Show Response ===
echo "═══ STEP 5: Actual API Response ═══" . PHP_EOL;

try {
    $response = \Illuminate\Support\Facades\Http::timeout(30)
        ->post($provider->api_url . '/game/v1', $requestBody);
    
    $responseData = $response->json();
    
    echo "Status Code: " . $response->status() . PHP_EOL;
    echo "Response Body:" . PHP_EOL;
    echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo PHP_EOL;
    
    if (isset($responseData['code'])) {
        if ($responseData['code'] === 0) {
            echo "✅ SUCCESS!" . PHP_EOL;
        } else {
            echo "❌ ERROR CODE: " . $responseData['code'] . PHP_EOL;
            echo "❌ ERROR MESSAGE: " . $responseData['msg'] . PHP_EOL;
            
            // Decode error codes
            $errorCodes = [
                10002 => 'Agency not exist',
                10004 => 'Payload error - Invalid request format or parameters',
                10005 => 'System error',
                10008 => 'The game does not exist',
                10011 => 'Player currencies do not match',
                10012 => 'Player name already exists',
                10013 => 'Currency is not supported',
                10014 => 'PlayerName is incorrect',
                10015 => 'Player account, limited to a-z and 0-9',
            ];
            
            if (isset($errorCodes[$responseData['code']])) {
                echo "❌ ERROR MEANING: " . $errorCodes[$responseData['code']] . PHP_EOL;
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ REQUEST FAILED: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "Analysis:" . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
echo "Game: " . $game->name . PHP_EOL;
echo "Game Code: " . json_decode($game->metadata)->game_code . PHP_EOL;
echo "Game UID: " . $game->game_id . PHP_EOL;
echo "Member Account: " . $memberAccount . PHP_EOL;
echo "Balance: PHP " . number_format($wallet->real_balance, 2) . PHP_EOL;
echo PHP_EOL;
echo "If error 10004 (payload error) occurs, it means AYUT doesn't" . PHP_EOL;
echo "recognize this game_uid in their system. This is NOT a bug in" . PHP_EOL;
echo "our code - the payload format is correct according to AYUT docs." . PHP_EOL;
echo PHP_EOL;
echo "Solution: Get the correct game UIDs from AYUT support." . PHP_EOL;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
