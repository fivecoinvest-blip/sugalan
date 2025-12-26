<?php

namespace App\Services;

use App\Models\SlotProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlotProviderService
{
    public function __construct(
        private SlotEncryptionService $encryptionService
    ) {}

    /**
     * Get active provider by code
     */
    public function getProvider(string $code): ?SlotProvider
    {
        return Cache::remember(
            "slot_provider:{$code}",
            now()->addHours(24),
            fn() => SlotProvider::where('code', $code)->where('is_active', true)->first()
        );
    }

    /**
     * Get all active providers
     */
    public function getActiveProviders(): array
    {
        return Cache::remember(
            'slot_providers:active',
            now()->addHours(24),
            fn() => SlotProvider::active()->get()->toArray()
        );
    }

    /**
     * Make game launch request (SEAMLESS)
     * Implements: POST {SERVER-URL}/game/v1
     * 
     * Request format:
     * {
     *   "agency_uid": "5d19788698c611ee9b610016...",
     *   "timestamp": "1631459081871",
     *   "payload": "(AES256EncryptionResult)"
     * }
     * 
     * Payload (encrypted) contains:
     * - timestamp, agency_uid, member_account, game_uid, credit_amount
     * - currency_code, language, home_url, platform, callback_url
     */
    public function makeGameLaunchRequest(
        SlotProvider $provider,
        string $endpoint,
        array $payloadData
    ): array {
        // Encrypt payload using AES-256
        $encryptedPayload = $this->encryptionService->encrypt($payloadData, $provider->aes_key);
        
        $url = rtrim($provider->api_url, '/') . '/' . ltrim($endpoint, '/');
        
        // Request body according to API spec
        $requestBody = [
            'agency_uid' => $provider->agency_uid,
            'timestamp' => (string) (now()->getPreciseTimestamp(3)), // Milliseconds
            'payload' => $encryptedPayload,
        ];
        
        Log::info('Slot Game Launch Request', [
            'provider' => $provider->code,
            'endpoint' => $endpoint,
            'url' => $url,
            'agency_uid' => $provider->agency_uid,
        ]);
        
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, $requestBody);
            
            if (!$response->successful()) {
                Log::error('Slot Game Launch API Error', [
                    'provider' => $provider->code,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                throw new \Exception("API request failed with status: " . $response->status());
            }
            
            $responseData = $response->json();
            
            Log::info('Slot Game Launch Response', [
                'provider' => $provider->code,
                'code' => $responseData['code'] ?? null,
                'msg' => $responseData['msg'] ?? null,
            ]);
            
            // Response format: {"code": 0, "msg": "", "payload": {"game_launch_url": "..."}}
            // If payload is encrypted, decrypt it
            if (isset($responseData['payload']) && is_string($responseData['payload'])) {
                try {
                    $decryptedPayload = $this->encryptionService->decrypt(
                        $responseData['payload'],
                        $provider->aes_key
                    );
                    $responseData['payload'] = $decryptedPayload;
                } catch (\Exception $e) {
                    // If decryption fails, assume payload is already JSON
                    Log::warning('Could not decrypt response payload, using as-is', [
                        'provider' => $provider->code,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            return $responseData;
            
        } catch (\Exception $e) {
            Log::error('Slot Game Launch Exception', [
                'provider' => $provider->code,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Make encrypted API request to provider
     */
    public function makeApiRequest(
        SlotProvider $provider,
        string $endpoint,
        array $data,
        string $method = 'POST'
    ): array {
        // Encrypt request data
        $encryptedData = $this->encryptionService->encrypt($data, $provider->aes_key);
        
        $url = rtrim($provider->api_url, '/') . '/' . ltrim($endpoint, '/');
        
        $requestData = [
            'data' => $encryptedData,
            'agencyUid' => $provider->agency_uid,
            'timestamp' => time(),
        ];
        
        // Generate signature
        $requestData['signature'] = $this->encryptionService->generateSignature(
            $encryptedData,
            $provider->aes_key,
            $requestData['timestamp']
        );
        
        Log::info('Slot API Request', [
            'provider' => $provider->code,
            'endpoint' => $endpoint,
            'url' => $url,
        ]);
        
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->$method($url, $requestData);
            
            if (!$response->successful()) {
                Log::error('Slot API Error', [
                    'provider' => $provider->code,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                throw new \Exception("API request failed: " . $response->status());
            }
            
            $responseData = $response->json();
            
            // Decrypt response if encrypted
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                $decryptedData = $this->encryptionService->decrypt(
                    $responseData['data'],
                    $provider->aes_key
                );
                
                $responseData['data'] = $decryptedData;
            }
            
            Log::info('Slot API Response', [
                'provider' => $provider->code,
                'success' => $responseData['success'] ?? false,
            ]);
            
            return $responseData;
            
        } catch (\Exception $e) {
            Log::error('Slot API Exception', [
                'provider' => $provider->code,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get transaction records from provider
     * Implements: POST {SERVER-URL}/game/transaction/list
     * 
     * @param SlotProvider $provider
     * @param int $fromDate Start timestamp in milliseconds
     * @param int $toDate End timestamp in milliseconds
     * @param int $pageNo Page number (1-based)
     * @param int $pageSize Page size (1-5000)
     * @return array Response with transaction records
     */
    public function getTransactionList(
        SlotProvider $provider,
        int $fromDate,
        int $toDate,
        int $pageNo = 1,
        int $pageSize = 100
    ): array {
        // Validate page size
        $pageSize = max(1, min(5000, $pageSize));
        
        // Prepare payload
        $payloadData = [
            'timestamp' => (string) (now()->getPreciseTimestamp(3)),
            'agency_uid' => $provider->agency_uid,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'page_no' => $pageNo,
            'page_size' => $pageSize,
        ];
        
        try {
            $response = $this->makeGameLaunchRequest(
                $provider,
                '/game/transaction/list',
                $payloadData
            );
            
            if (!isset($response['code']) || $response['code'] !== 0) {
                $errorMsg = $response['msg'] ?? 'Unknown error';
                throw new \Exception("Failed to fetch transactions: {$errorMsg}");
            }
            
            return $response['payload'] ?? [];
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction list', [
                'provider' => $provider->code,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate callback URL for provider
     */
    public function getCallbackUrl(SlotProvider $provider, string $endpoint): string
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/api/slots/callback/{$provider->code}/{$endpoint}";
    }

    /**
     * Generate player identifier with provider prefix
     */
    public function generatePlayerId(SlotProvider $provider, int $userId): string
    {
        return $provider->player_prefix . '_' . $userId;
    }

    /**
     * Parse player ID to extract user ID
     */
    public function parsePlayerId(SlotProvider $provider, string $playerId): ?int
    {
        $prefix = $provider->player_prefix;
        
        if (!str_starts_with($playerId, $prefix)) {
            return null;
        }
        
        // Extract numeric part after prefix (e.g., PHBET0002 -> 2)
        $numericPart = substr($playerId, strlen($prefix));
        
        // Handle zero-padded numbers (ltrim removes leading zeros)
        return is_numeric($numericPart) ? (int) ltrim($numericPart, '0') : null;
    }

    /**
     * Clear provider cache
     */
    public function clearCache(string $code): void
    {
        Cache::forget("slot_provider:{$code}");
        Cache::forget('slot_providers:active');
    }

    /**
     * Validate provider callback signature
     */
    public function validateCallbackSignature(
        SlotProvider $provider,
        string $data,
        string $signature,
        ?int $timestamp = null
    ): bool {
        // Validate timestamp if provided
        if ($timestamp !== null && !$this->encryptionService->validateTimestamp($timestamp)) {
            Log::warning('Invalid callback timestamp', [
                'provider' => $provider->code,
                'timestamp' => $timestamp,
            ]);
            return false;
        }
        
        // Verify signature
        return $this->encryptionService->verifySignature($data, $signature, $provider->aes_key, $timestamp);
    }
}
