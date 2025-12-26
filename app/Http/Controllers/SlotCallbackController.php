<?php

namespace App\Http\Controllers;

use App\Services\SlotProviderService;
use App\Services\SlotSessionService;
use App\Services\SlotWalletService;
use App\Services\SlotEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SlotCallbackController extends Controller
{
    public function __construct(
        private SlotProviderService $providerService,
        private SlotSessionService $sessionService,
        private SlotWalletService $walletService,
        private SlotEncryptionService $encryptionService
    ) {}

    /**
     * Handle unified bet/win callback from AYUT provider (SEAMLESS)
     * 
     * Implements: POST https://{callback_url}
     * 
     * Request format:
     * {
     *   "timestamp": "1631459081871",
     *   "payload": "(AES256EncryptionResult)"
     * }
     * 
     * Payload (encrypted) contains:
     * - serial_number: UUID for idempotency
     * - currency_code: Currency (e.g., USD, PHP)
     * - game_uid: Game UID
     * - member_account: Player account
     * - win_amount: WIN amount (negative = refund)
     * - bet_amount: BET amount (negative = refund)
     * - timestamp: Milliseconds timestamp
     * - game_round: Round identifier
     * - data: Sports event detailed data
     * 
     * Response format:
     * {
     *   "code": 0,
     *   "msg": "",
     *   "payload": "(AES256EncryptionResult)"
     * }
     * 
     * Response payload (encrypted) contains:
     * - credit_amount: Updated balance (credit_amount - bet_amount + win_amount)
     * - timestamp: Milliseconds timestamp
     *
     * @param Request $request
     * @param string $providerCode
     * @return JsonResponse
     */
    public function handleBet(Request $request, string $providerCode): JsonResponse
    {
        $startTime = microtime(true);
        
        Log::info('Slot Callback - Bet received', [
            'provider' => $providerCode,
            'payload' => $request->input('payload'),
            'timestamp' => $request->input('timestamp'),
            'all_input' => $request->all()
        ]);
        
        try {
            // Get provider
            $provider = $this->providerService->getProvider($providerCode);
            
            if (!$provider) {
                Log::error('Callback: Provider not found', ['code' => $providerCode]);
                return $this->ayutErrorResponse('Provider not found', 1);
            }
            
            // Get raw request data
            $timestamp = $request->input('timestamp');
            $encryptedPayload = $request->input('payload');
            
            if (!$timestamp || !$encryptedPayload) {
                Log::error('Callback: Missing required fields', [
                    'provider' => $providerCode,
                    'has_timestamp' => !empty($timestamp),
                    'has_payload' => !empty($encryptedPayload),
                ]);
                return $this->ayutErrorResponse('Missing required fields', 1);
            }
            
            // Decrypt payload
            try {
                $payload = $this->encryptionService->decrypt($encryptedPayload, $provider->aes_key);
            } catch (\Exception $e) {
                Log::error('Callback: Decryption failed', [
                    'provider' => $providerCode,
                    'error' => $e->getMessage(),
                ]);
                return $this->ayutErrorResponse('Decryption failed', 1);
            }
            
            // Log incoming callback
            Log::info('Slot Callback Received', [
                'provider' => $providerCode,
                'serial_number' => $payload['serial_number'] ?? null,
                'member_account' => $payload['member_account'] ?? null,
                'game_uid' => $payload['game_uid'] ?? null,
                'bet_amount' => $payload['bet_amount'] ?? null,
                'win_amount' => $payload['win_amount'] ?? null,
                'game_round' => $payload['game_round'] ?? null,
            ]);
            
            // Validate required fields
            $requiredFields = [
                'serial_number', 'currency_code', 'game_uid', 
                'member_account', 'win_amount', 'bet_amount', 
                'timestamp', 'game_round'
            ];
            
            foreach ($requiredFields as $field) {
                if (!isset($payload[$field])) {
                    Log::error('Callback: Missing payload field', [
                        'provider' => $providerCode,
                        'field' => $field,
                    ]);
                    return $this->ayutErrorResponse("Missing field: {$field}", 1);
                }
            }
            
            // Parse amounts (they come as strings)
            $betAmount = (float) $payload['bet_amount'];
            $winAmount = (float) $payload['win_amount'];
            
            // Extract user ID from member_account (format: {prefix}{user_id})
            $userId = $this->providerService->parsePlayerId($provider, $payload['member_account']);
            
            if (!$userId) {
                Log::error('Callback: Invalid member_account', [
                    'provider' => $providerCode,
                    'member_account' => $payload['member_account'],
                ]);
                return $this->ayutErrorResponse('Invalid member_account', 1);
            }
            
            // Process transaction with idempotency
            $result = $this->walletService->processSeamlessTransaction(
                $userId,
                $payload['serial_number'],
                $payload['game_uid'],
                $payload['game_round'],
                $betAmount,
                $winAmount,
                $payload['data'] ?? null
            );
            
            // Check if transaction was successful
            if (!$result['success']) {
                Log::warning('Callback: Transaction failed', [
                    'provider' => $providerCode,
                    'serial_number' => $payload['serial_number'],
                    'reason' => $result['message'] ?? 'Unknown',
                ]);
                return $this->ayutErrorResponse($result['message'] ?? 'Transaction failed', 1);
            }
            
            // Prepare response payload
            $responsePayload = [
                'credit_amount' => (string) number_format($result['balance'], 2, '.', ''),
                'timestamp' => (string) (now()->getPreciseTimestamp(3)),
            ];
            
            // Encrypt response payload
            $encryptedResponse = $this->encryptionService->encrypt($responsePayload, $provider->aes_key);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('Slot Callback Success', [
                'provider' => $providerCode,
                'serial_number' => $payload['serial_number'],
                'balance' => $result['balance'],
                'duration_ms' => $duration,
            ]);
            
            // Return AYUT format response
            return response()->json([
                'code' => 0,
                'msg' => '',
                'payload' => $encryptedResponse,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Callback: Exception', [
                'provider' => $providerCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->ayutErrorResponse('Internal error: ' . $e->getMessage(), 1);
        }
    }

    /**
     * Handle win callback (alias for handleBet for backward compatibility)
     */
    public function handleWin(Request $request, string $providerCode): JsonResponse
    {
        return $this->handleBet($request, $providerCode);
    }

    /**
     * Handle rollback callback
     */
    public function handleRollback(Request $request, string $providerCode): JsonResponse
    {
        try {
            $provider = $this->providerService->getProvider($providerCode);
            
            if (!$provider) {
                return $this->ayutErrorResponse('Provider not found', 1);
            }
            
            $encryptedPayload = $request->input('payload');
            
            if (!$encryptedPayload) {
                return $this->ayutErrorResponse('Missing payload', 1);
            }
            
            $payload = $this->encryptionService->decrypt($encryptedPayload, $provider->aes_key);
            
            Log::info('Slot Rollback Received', [
                'provider' => $providerCode,
                'serial_number' => $payload['serial_number'] ?? null,
            ]);
            
            // Rollback is handled automatically by idempotency in processSeamlessTransaction
            // If serial_number was already processed with negative amounts, it's a rollback
            
            $userId = $this->providerService->parsePlayerId($provider, $payload['member_account']);
            $balance = $this->walletService->getUserBalance($userId);
            
            $responsePayload = [
                'credit_amount' => (string) number_format($balance, 2, '.', ''),
                'timestamp' => (string) (now()->getPreciseTimestamp(3)),
            ];
            
            $encryptedResponse = $this->encryptionService->encrypt($responsePayload, $provider->aes_key);
            
            return response()->json([
                'code' => 0,
                'msg' => '',
                'payload' => $encryptedResponse,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Rollback callback failed', [
                'provider' => $providerCode,
                'error' => $e->getMessage(),
            ]);
            
            return $this->ayutErrorResponse($e->getMessage(), 1);
        }
    }

    /**
     * Handle balance check callback
     */
    public function handleBalanceCheck(Request $request, string $providerCode): JsonResponse
    {
        try {
            $provider = $this->providerService->getProvider($providerCode);
            
            if (!$provider) {
                return $this->ayutErrorResponse('Provider not found', 1);
            }
            
            $encryptedPayload = $request->input('payload');
            
            if (!$encryptedPayload) {
                return $this->ayutErrorResponse('Missing payload', 1);
            }
            
            $payload = $this->encryptionService->decrypt($encryptedPayload, $provider->aes_key);
            
            $userId = $this->providerService->parsePlayerId($provider, $payload['member_account']);
            
            if (!$userId) {
                return $this->ayutErrorResponse('Invalid member_account', 1);
            }
            
            $balance = $this->walletService->getUserBalance($userId);
            
            $responsePayload = [
                'credit_amount' => (string) number_format($balance, 2, '.', ''),
                'timestamp' => (string) (now()->getPreciseTimestamp(3)),
            ];
            
            $encryptedResponse = $this->encryptionService->encrypt($responsePayload, $provider->aes_key);
            
            return response()->json([
                'code' => 0,
                'msg' => '',
                'payload' => $encryptedResponse,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Balance check callback failed', [
                'provider' => $providerCode,
                'error' => $e->getMessage(),
            ]);
            
            return $this->ayutErrorResponse($e->getMessage(), 1);
        }
    }

    /**
     * Return AYUT format error response
     * 
     * @param string $message Error message
     * @param int $code Error code (0=success, 1=failure)
     * @return JsonResponse
     */
    private function ayutErrorResponse(string $message, int $code = 1): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg' => $message,
            'payload' => '',
        ]);
    }
}
