<?php

namespace App\Services;

use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\SlotSession;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlotSessionService
{
    public function __construct(
        private SlotProviderService $providerService,
        private SlotGameService $gameService,
        private SlotEncryptionService $encryptionService
    ) {}

    /**
     * Create a new game session
     */
    public function createSession(
        User $user,
        SlotGame $game,
        bool $demoMode = false
    ): SlotSession {
        return DB::transaction(function () use ($user, $game, $demoMode) {
            // Get user's wallet
            $wallet = Wallet::where('user_id', $user->id)->first();
            
            if (!$wallet) {
                throw new \Exception('User wallet not found');
            }
            
            // Check if user has active session
            $activeSession = SlotSession::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->first();
            
            if ($activeSession) {
                // End previous session with current balance
                $activeSession->end($wallet->real_balance);
            }
            
            // Generate session token
            $sessionToken = $this->encryptionService->generateToken(128);
            
            // Create session
            $session = SlotSession::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'provider_id' => $game->provider_id,
                'session_token' => $sessionToken,
                'game_url' => '', // Will be updated after launch URL generation
                'initial_balance' => $demoMode ? 10000.00 : $wallet->real_balance,
                'final_balance' => $demoMode ? 10000.00 : $wallet->real_balance,
                'total_bets' => 0,
                'total_wins' => 0,
                'rounds_played' => 0,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => now()->addMinutes(
                    $game->provider->config['session_timeout'] ?? 30
                ),
            ]);
            
            // Generate launch URL
            $launchUrl = $this->gameService->generateLaunchUrl(
                $game,
                $user,
                $sessionToken,
                $demoMode
            );
            
            $session->update(['game_url' => $launchUrl]);
            
            Log::info('Slot session created', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'game_id' => $game->id,
                'demo_mode' => $demoMode,
            ]);
            
            return $session;
        });
    }

    /**
     * Get active session by token
     */
    public function getSessionByToken(string $token): ?SlotSession
    {
        return SlotSession::where('session_token', $token)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Get active session by UUID
     */
    public function getSessionByUuid(string $uuid): ?SlotSession
    {
        return SlotSession::where('uuid', $uuid)->first();
    }

    /**
     * Get user's active session
     */
    public function getUserActiveSession(User $user): ?SlotSession
    {
        return SlotSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Update session statistics
     */
    public function updateSessionStats(
        SlotSession $session,
        float $betAmount,
        float $winAmount,
        float $currentBalance
    ): void {
        $session->total_bets += $betAmount;
        $session->total_wins += $winAmount;
        $session->rounds_played += 1;
        $session->final_balance = $currentBalance;
        $session->save();
    }

    /**
     * End a session
     */
    public function endSession(SlotSession $session, float $finalBalance): void
    {
        $session->end($finalBalance);
        
        Log::info('Slot session ended', [
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'duration' => $session->started_at->diffInMinutes($session->ended_at),
            'rounds' => $session->rounds_played,
            'total_bets' => $session->total_bets,
            'total_wins' => $session->total_wins,
            'profit' => $session->final_balance - $session->initial_balance,
        ]);
    }

    /**
     * Expire old sessions
     */
    public function expireOldSessions(): int
    {
        $expired = SlotSession::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();
        
        $count = 0;
        
        foreach ($expired as $session) {
            $session->expire();
            $count++;
            
            Log::info('Slot session expired', [
                'session_id' => $session->id,
                'user_id' => $session->user_id,
            ]);
        }
        
        return $count;
    }

    /**
     * Get user's session history
     */
    public function getUserSessions(
        User $user,
        int $page = 1,
        int $perPage = 20
    ): array {
        $sessions = SlotSession::with(['game', 'provider'])
            ->where('user_id', $user->id)
            ->orderBy('started_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'data' => $sessions->items(),
            'pagination' => [
                'total' => $sessions->total(),
                'per_page' => $sessions->perPage(),
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'from' => $sessions->firstItem(),
                'to' => $sessions->lastItem(),
            ],
        ];
    }

    /**
     * Get session statistics
     */
    public function getSessionStats(SlotSession $session): array
    {
        $duration = $session->ended_at 
            ? $session->started_at->diffInMinutes($session->ended_at)
            : $session->started_at->diffInMinutes(now());
        
        $profit = $session->final_balance - $session->initial_balance;
        $roi = $session->total_bets > 0 
            ? (($profit / $session->total_bets) * 100)
            : 0;
        
        return [
            'session_id' => $session->uuid,
            'game' => $session->game->name,
            'provider' => $session->provider->name,
            'status' => $session->status,
            'duration_minutes' => $duration,
            'rounds_played' => $session->rounds_played,
            'initial_balance' => $session->initial_balance,
            'final_balance' => $session->final_balance,
            'total_bets' => $session->total_bets,
            'total_wins' => $session->total_wins,
            'profit' => $profit,
            'roi_percentage' => round($roi, 2),
            'started_at' => $session->started_at->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'expires_at' => $session->expires_at->toIso8601String(),
        ];
    }

    /**
     * Validate session is active and not expired
     */
    public function validateSession(SlotSession $session): bool
    {
        if ($session->status !== 'active') {
            return false;
        }
        
        if ($session->isExpired()) {
            $session->expire();
            return false;
        }
        
        return true;
    }

    /**
     * Extend session expiration
     */
    public function extendSession(SlotSession $session, int $minutes = 30): void
    {
        if ($session->status !== 'active') {
            throw new \Exception('Cannot extend inactive session');
        }
        
        $session->update([
            'expires_at' => now()->addMinutes($minutes),
        ]);
        
        Log::info('Slot session extended', [
            'session_id' => $session->id,
            'new_expiration' => $session->expires_at->toIso8601String(),
        ]);
    }
}
