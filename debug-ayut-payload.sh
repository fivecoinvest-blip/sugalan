#!/bin/bash
# Debug AYUT API Payload

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "AYUT API Payload Debug"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

php artisan tinker --execute="
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\User;
use App\Services\SlotEncryptionService;

\$provider = SlotProvider::where('code', 'AYUT')->first();
\$game = SlotGame::where('provider_id', \$provider->id)->first();
\$user = User::where('email', 'test@example.com')->first();
\$wallet = \$user->wallet;

echo '=== Provider Info ===' . PHP_EOL;
echo 'Agency UID: ' . \$provider->agency_uid . PHP_EOL;
echo 'AES Key: ' . \$provider->aes_key . PHP_EOL;
echo 'AES Key Length: ' . strlen(\$provider->aes_key) . ' characters' . PHP_EOL;
echo '' . PHP_EOL;

echo '=== Game Info ===' . PHP_EOL;
echo 'Game: ' . \$game->name . PHP_EOL;
echo 'Game UID: ' . \$game->game_id . PHP_EOL;
echo 'Game UID Length: ' . strlen(\$game->game_id) . ' characters' . PHP_EOL;
echo '' . PHP_EOL;

echo '=== User Info ===' . PHP_EOL;
echo 'User ID: ' . \$user->id . PHP_EOL;
echo 'Member Account: ' . \$provider->player_prefix . sprintf('%04d', \$user->id) . PHP_EOL;
echo 'Balance: PHP ' . number_format(\$wallet->real_balance, 2) . PHP_EOL;
echo '' . PHP_EOL;

echo '=== Payload Data (Before Encryption) ===' . PHP_EOL;
\$memberAccount = \$provider->player_prefix . sprintf('%04d', \$user->id);
\$payloadData = [
    'timestamp' => (string) (now()->getPreciseTimestamp(3)),
    'agency_uid' => \$provider->agency_uid,
    'member_account' => \$memberAccount,
    'game_uid' => \$game->game_id,
    'credit_amount' => (string) number_format(\$wallet->real_balance, 2, '.', ''),
    'currency_code' => 'PHP',
    'language' => 'en',
    'home_url' => config('app.frontend_url') . '/slots',
    'platform' => 1,
    'callback_url' => config('app.url') . '/api/slots/callback/' . \$provider->code . '/callback',
];

echo json_encode(\$payloadData, JSON_PRETTY_PRINT) . PHP_EOL;
echo '' . PHP_EOL;

echo '=== Encryption Test ===' . PHP_EOL;
\$encryptionService = app(SlotEncryptionService::class);
\$encrypted = \$encryptionService->encrypt(\$payloadData, \$provider->aes_key);
echo 'Encrypted Length: ' . strlen(\$encrypted) . ' characters' . PHP_EOL;
echo 'Encrypted (first 100 chars): ' . substr(\$encrypted, 0, 100) . '...' . PHP_EOL;
echo '' . PHP_EOL;

echo '=== Decryption Test ===' . PHP_EOL;
try {
    \$decrypted = \$encryptionService->decrypt(\$encrypted, \$provider->aes_key);
    echo '✓ Decryption successful' . PHP_EOL;
    echo 'Decrypted matches original: ' . (json_encode(\$decrypted) === json_encode(\$payloadData) ? 'Yes' : 'No') . PHP_EOL;
} catch (\Exception \$e) {
    echo '❌ Decryption failed: ' . \$e->getMessage() . PHP_EOL;
}
echo '' . PHP_EOL;

echo '=== Full Request Body ===' . PHP_EOL;
\$requestBody = [
    'agency_uid' => \$provider->agency_uid,
    'timestamp' => (string) (now()->getPreciseTimestamp(3)),
    'payload' => \$encrypted,
];
echo json_encode(\$requestBody, JSON_PRETTY_PRINT) . PHP_EOL;
echo '' . PHP_EOL;

echo '=== API Request Details ===' . PHP_EOL;
echo 'URL: ' . \$provider->api_url . '/game/v1' . PHP_EOL;
echo 'Method: POST' . PHP_EOL;
echo 'Content-Type: application/json' . PHP_EOL;
echo '' . PHP_EOL;

echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
echo 'Checklist:' . PHP_EOL;
echo '1. AES key should be 32 characters (256 bits)' . PHP_EOL;
echo '2. Game UID should be exactly as provided by AYUT' . PHP_EOL;
echo '3. Member account format: prefix + 4-digit user ID' . PHP_EOL;
echo '4. All string values should be properly formatted' . PHP_EOL;
echo '5. Timestamp should be in milliseconds' . PHP_EOL;
echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
"
