<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoftAPIService
{
    private string $token;
    private string $secret;
    private string $baseUrl;
    private bool $encryptionEnabled;

    public function __construct()
    {
        $this->token = config('services.softapi.token');
        $this->secret = config('services.softapi.secret');
        $this->baseUrl = config('services.softapi.base_url');
        $this->encryptionEnabled = config('services.softapi.encryption_enabled', true);
    }

    /**
     * Encrypt payload using AES-256-ECB
     */
    private function encrypt(string $data): string
    {
        if (!$this->encryptionEnabled) {
            return $data;
        }

        $encrypted = openssl_encrypt(
            $data,
            'AES-256-ECB',
            $this->secret,
            OPENSSL_RAW_DATA
        );

        return base64_encode($encrypted);
    }

    /**
     * Decrypt payload using AES-256-ECB
     */
    private function decrypt(string $encrypted): string
    {
        if (!$this->encryptionEnabled) {
            return $encrypted;
        }

        $decoded = base64_decode($encrypted);
        
        $decrypted = openssl_decrypt(
            $decoded,
            'AES-256-ECB',
            $this->secret,
            OPENSSL_RAW_DATA
        );

        return $decrypted;
    }

    /**
     * Make API request to SoftAPI
     */
    private function makeRequest(string $endpoint, array $payload = []): array
    {
        $url = $this->baseUrl . $endpoint;

        // Add token to payload
        $payload['token'] = $this->token;

        // Encrypt payload if enabled
        if ($this->encryptionEnabled) {
            $jsonPayload = json_encode($payload);
            $encryptedPayload = $this->encrypt($jsonPayload);
            $requestData = ['data' => $encryptedPayload];
        } else {
            $requestData = $payload;
        }

        try {
            $response = Http::timeout(30)->post($url, $requestData);

            if (!$response->successful()) {
                Log::error('SoftAPI request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'API request failed',
                    'status' => $response->status(),
                ];
            }

            $responseData = $response->json();

            // Decrypt response if needed
            if ($this->encryptionEnabled && isset($responseData['data'])) {
                $decrypted = $this->decrypt($responseData['data']);
                $responseData = json_decode($decrypted, true);
            }

            return $responseData;

        } catch (\Exception $e) {
            Log::error('SoftAPI exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get list of available providers
     */
    public function getProviders(): array
    {
        // Note: Provider list comes from a different endpoint
        // This would need to be implemented based on SoftAPI documentation
        return $this->makeRequest('/providers');
    }

    /**
     * Get games from a specific provider
     */
    public function getGamesByProvider(string $brandId): array
    {
        return $this->makeRequest('/games', [
            'brand_id' => $brandId,
        ]);
    }

    /**
     * Launch a game for a user
     */
    public function launchGame(string $gameId, string $userId, array $options = []): array
    {
        $payload = array_merge([
            'game_id' => $gameId,
            'user_id' => $userId,
            'lang' => $options['lang'] ?? 'en',
            'currency' => $options['currency'] ?? 'PHP',
            'return_url' => $options['return_url'] ?? config('app.url'),
        ], $options);

        return $this->makeRequest('/game/launch', $payload);
    }

    /**
     * Get user balance
     */
    public function getBalance(string $userId): array
    {
        return $this->makeRequest('/balance', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Process debit (place bet)
     */
    public function debit(string $userId, float $amount, string $transactionId, array $metadata = []): array
    {
        $payload = array_merge([
            'user_id' => $userId,
            'amount' => $amount,
            'transaction_id' => $transactionId,
        ], $metadata);

        return $this->makeRequest('/transaction/debit', $payload);
    }

    /**
     * Process credit (win)
     */
    public function credit(string $userId, float $amount, string $transactionId, array $metadata = []): array
    {
        $payload = array_merge([
            'user_id' => $userId,
            'amount' => $amount,
            'transaction_id' => $transactionId,
        ], $metadata);

        return $this->makeRequest('/transaction/credit', $payload);
    }

    /**
     * Rollback transaction
     */
    public function rollback(string $transactionId): array
    {
        return $this->makeRequest('/transaction/rollback', [
            'transaction_id' => $transactionId,
        ]);
    }

    /**
     * Verify callback signature
     */
    public function verifyCallbackSignature(array $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $this->secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get game details
     */
    public function getGameDetails(string $gameId): array
    {
        return $this->makeRequest('/game/details', [
            'game_id' => $gameId,
        ]);
    }
}
