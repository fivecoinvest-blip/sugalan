<?php

namespace App\Services;

use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlotGameService
{
    public function __construct(
        private SlotProviderService $providerService
    ) {}

    /**
     * Sync games from provider API
     */
    public function syncGames(SlotProvider $provider): int
    {
        try {
            $response = $this->providerService->makeApiRequest(
                $provider,
                '/api/games',
                ['agencyUid' => $provider->agency_uid]
            );
            
            if (!isset($response['success']) || !$response['success']) {
                throw new \Exception('Failed to fetch games from provider');
            }
            
            $games = $response['data']['games'] ?? [];
            $synced = 0;
            
            foreach ($games as $gameData) {
                SlotGame::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'game_id' => $gameData['gameId'],
                    ],
                    [
                        'name' => $gameData['name'],
                        'category' => $gameData['category'] ?? 'slots',
                        'thumbnail_url' => $gameData['thumbnail'] ?? null,
                        'min_bet' => $gameData['minBet'] ?? 1.00,
                        'max_bet' => $gameData['maxBet'] ?? 10000.00,
                        'rtp' => $gameData['rtp'] ?? null,
                        'volatility' => $gameData['volatility'] ?? null,
                        'lines' => $gameData['lines'] ?? null,
                        'is_active' => $gameData['isActive'] ?? true,
                        'metadata' => $gameData['metadata'] ?? [],
                    ]
                );
                
                $synced++;
            }
            
            // Clear games cache
            $this->clearCache($provider->code);
            
            Log::info('Synced slot games', [
                'provider' => $provider->code,
                'count' => $synced,
            ]);
            
            return $synced;
            
        } catch (\Exception $e) {
            Log::error('Failed to sync slot games', [
                'provider' => $provider->code,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get active games for a provider
     */
    public function getProviderGames(string $providerCode, ?string $category = null): array
    {
        $cacheKey = "slot_games:{$providerCode}" . ($category ? ":{$category}" : '');
        
        return Cache::remember(
            $cacheKey,
            now()->addHours(12),
            function () use ($providerCode, $category) {
                $query = SlotGame::with('provider')
                    ->whereHas('provider', fn($q) => $q->where('code', $providerCode)->where('is_active', true))
                    ->where('is_active', true);
                
                if ($category) {
                    $query->where('category', $category);
                }
                
                return $query->orderBy('name')->get()->toArray();
            }
        );
    }

    /**
     * Get all active games from all providers
     */
    public function getAllGames(?string $category = null): array
    {
        $cacheKey = 'slot_games:all' . ($category ? ":{$category}" : '');
        
        return Cache::remember(
            $cacheKey,
            now()->addHours(12),
            function () use ($category) {
                $query = SlotGame::with('provider')
                    ->whereHas('provider', fn($q) => $q->where('is_active', true))
                    ->where('is_active', true);
                
                if ($category) {
                    $query->where('category', $category);
                }
                
                return $query->orderBy('name')->get()->toArray();
            }
        );
    }

    /**
     * Get game by ID
     */
    public function getGame(int $gameId): ?SlotGame
    {
        return SlotGame::with('provider')->find($gameId);
    }

    /**
     * Generate game launch URL (SEAMLESS)
     * Implements: POST {SERVER-URL}/game/v1
     */
    public function generateLaunchUrl(
        SlotGame $game,
        User $user,
        string $sessionToken,
        bool $demoMode = false
    ): string {
        $provider = $game->provider;
        $config = $provider->config ?? [];
        $walletMode = $config['wallet_mode'] ?? 'seamless';
        
        if ($walletMode === 'transfer') {
            return $this->generateTransferLaunchUrl($game, $user, $sessionToken, $demoMode);
        }
        
        return $this->generateSeamlessLaunchUrl($game, $user, $sessionToken, $demoMode);
    }

    /**
     * Generate game launch URL (SEAMLESS mode)
     * Implements: POST {SERVER-URL}/game/v1
     */
    private function generateSeamlessLaunchUrl(
        SlotGame $game,
        User $user,
        string $sessionToken,
        bool $demoMode = false
    ): string {
        $provider = $game->provider;
        $wallet = $user->wallet;
        
        if (!$wallet) {
            throw new \Exception('User wallet not found');
        }
        
        // Generate player account name with customizable prefix
        $memberAccount = $provider->player_prefix . sprintf('%04d', $user->id);
        
        try {
            // Prepare payload parameters (to be encrypted with AES-256)
            $payloadData = [
                'timestamp' => (string) (now()->getPreciseTimestamp(3)), // Milliseconds
                'agency_uid' => $provider->agency_uid,
                'member_account' => $memberAccount,
                'game_uid' => $game->game_id,
                'credit_amount' => (string) ($demoMode ? '10000' : number_format($wallet->real_balance, 2, '.', '')),
                'currency_code' => 'PHP',
                'language' => 'en',
                'home_url' => config('app.frontend_url') . '/slots',
                'platform' => 1, // 1=web, 2=H5
                'callback_url' => $this->providerService->getCallbackUrl($provider, 'bet'),
            ];
            
            // Log the payload being sent
            Log::info('Game Launch Payload (unencrypted)', [
                'provider' => $provider->code,
                'payload' => $payloadData
            ]);
            
            $response = $this->providerService->makeGameLaunchRequest(
                $provider,
                '/game/v1',
                $payloadData
            );
            
            // Response format: {"code": 0, "msg": "", "payload": {"game_launch_url": "..."}}
            if (!isset($response['code']) || $response['code'] !== 0) {
                $errorMsg = $response['msg'] ?? 'Unknown error';
                throw new \Exception("Provider returned error: {$errorMsg} (code: {$response['code']})");
            }
            
            if (!isset($response['payload']['game_launch_url'])) {
                throw new \Exception('Invalid response: missing game_launch_url');
            }
            
            return $response['payload']['game_launch_url'];
            
        } catch (\Exception $e) {
            Log::error('Failed to generate seamless launch URL', [
                'game_id' => $game->id,
                'game_uid' => $game->game_id,
                'user_id' => $user->id,
                'provider' => $provider->code,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate game launch URL (TRANSFER mode)
     * Implements: POST {SERVER-URL}/game/v2
     * 
     * Transfer mode: Deposit/withdrawal to provider-side wallet
     * - credit_amount > 0: Deposit (transfer to provider)
     * - credit_amount < 0: Withdrawal (withdraw from provider)
     * - credit_amount = 0: Query only (no transfer)
     */
    private function generateTransferLaunchUrl(
        SlotGame $game,
        User $user,
        string $sessionToken,
        bool $demoMode = false
    ): string {
        $provider = $game->provider;
        $wallet = $user->wallet;
        
        if (!$wallet) {
            throw new \Exception('User wallet not found');
        }
        
        // Generate player account name with customizable prefix
        $memberAccount = $provider->player_prefix . sprintf('%04d', $user->id);
        
        // Generate unique transfer ID
        $transferId = 'TXN_' . strtoupper(uniqid()) . '_' . time();
        
        // Initial deposit amount (transfer player's balance to provider)
        $transferAmount = $demoMode ? 10000.00 : $wallet->real_balance;
        
        try {
            // Prepare payload parameters (to be encrypted with AES-256)
            $payloadData = [
                'timestamp' => (string) (now()->getPreciseTimestamp(3)), // Milliseconds
                'agency_uid' => $provider->agency_uid,
                'member_account' => $memberAccount,
                'game_uid' => $game->game_id ?? '', // Optional for transfer mode
                'credit_amount' => (string) number_format($transferAmount, 2, '.', ''), // > 0 = deposit
                'currency_code' => 'PHP',
                'language' => 'en',
                'home_url' => config('app.frontend_url') . '/slots',
                'platform' => 1, // 1=web, 2=H5
                'transfer_id' => $transferId,
            ];
            
            $response = $this->providerService->makeGameLaunchRequest(
                $provider,
                '/game/v2', // Transfer mode uses v2
                $payloadData
            );
            
            // Response format: {"code": 0, "msg": "", "payload": {...}}
            if (!isset($response['code']) || $response['code'] !== 0) {
                $errorMsg = $response['msg'] ?? 'Unknown error';
                throw new \Exception("Provider returned error: {$errorMsg} (code: {$response['code']})");
            }
            
            $payload = $response['payload'];
            
            // Validate response
            if (!isset($payload['game_launch_url'])) {
                throw new \Exception('Invalid response: missing game_launch_url');
            }
            
            if (!isset($payload['transfer_status']) || $payload['transfer_status'] != 1) {
                $status = $payload['transfer_status'] ?? 'unknown';
                throw new \Exception("Transfer failed with status: {$status}");
            }
            
            // Record transfer transaction
            $this->recordTransferTransaction(
                $user,
                $game,
                $provider,
                $transferId,
                $transferAmount,
                $payload
            );
            
            Log::info('Transfer mode game launch successful', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'transfer_id' => $transferId,
                'transfer_amount' => $transferAmount,
                'transaction_id' => $payload['transaction_id'] ?? null,
            ]);
            
            return $payload['game_launch_url'];
            
        } catch (\Exception $e) {
            Log::error('Failed to generate transfer launch URL', [
                'game_id' => $game->id,
                'game_uid' => $game->game_id,
                'user_id' => $user->id,
                'provider' => $provider->code,
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Record transfer transaction
     */
    private function recordTransferTransaction(
        User $user,
        SlotGame $game,
        $provider,
        string $transferId,
        float $amount,
        array $responsePayload
    ): void {
        DB::transaction(function () use ($user, $game, $provider, $transferId, $amount, $responsePayload) {
            $wallet = $user->wallet;
            $balanceBefore = $wallet->real_balance;
            
            // Deduct transfer amount from wallet (depositing to provider)
            if ($amount > 0) {
                $wallet->real_balance -= $amount;
                $wallet->save();
            }
            
            // Create core transaction record
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'transfer_out',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->real_balance,
                'description' => "Transfer to {$provider->name} - {$game->name}",
                'metadata' => [
                    'transfer_id' => $transferId,
                    'transaction_id' => $responsePayload['transaction_id'] ?? null,
                    'game_id' => $game->id,
                    'provider_id' => $provider->id,
                    'before_amount' => $responsePayload['before_amount'] ?? null,
                    'after_amount' => $responsePayload['after_amount'] ?? null,
                ],
            ]);
        });
    }


    /**
     * Get game categories
     */
    public function getCategories(?string $providerCode = null): array
    {
        $cacheKey = 'slot_categories' . ($providerCode ? ":{$providerCode}" : '');
        
        return Cache::remember(
            $cacheKey,
            now()->addHours(24),
            function () use ($providerCode) {
                $query = SlotGame::query()
                    ->whereHas('provider', fn($q) => $q->where('is_active', true))
                    ->where('is_active', true);
                
                if ($providerCode) {
                    $query->whereHas('provider', fn($q) => $q->where('code', $providerCode));
                }
                
                return $query->distinct()->pluck('category')->filter()->values()->toArray();
            }
        );
    }

    /**
     * Search games
     */
    public function searchGames(string $query, ?string $providerCode = null): array
    {
        $gamesQuery = SlotGame::with('provider')
            ->whereHas('provider', fn($q) => $q->where('is_active', true))
            ->where('is_active', true)
            ->where('name', 'LIKE', "%{$query}%");
        
        if ($providerCode) {
            $gamesQuery->whereHas('provider', fn($q) => $q->where('code', $providerCode));
        }
        
        return $gamesQuery->orderBy('name')->limit(50)->get()->toArray();
    }

    /**
     * Clear games cache
     */
    public function clearCache(?string $providerCode = null): void
    {
        if ($providerCode) {
            Cache::forget("slot_games:{$providerCode}");
            Cache::forget("slot_categories:{$providerCode}");
        } else {
            Cache::forget('slot_games:all');
            Cache::forget('slot_categories');
        }
    }

    /**
     * Get popular games (most played)
     */
    public function getPopularGames(int $limit = 10): array
    {
        return Cache::remember(
            "slot_games:popular:{$limit}",
            now()->addHours(1),
            function () use ($limit) {
                return SlotGame::with('provider')
                    ->whereHas('provider', fn($q) => $q->where('is_active', true))
                    ->where('is_active', true)
                    ->withCount('sessions')
                    ->orderBy('sessions_count', 'desc')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            }
        );
    }
}
