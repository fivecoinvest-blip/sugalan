<?php

namespace App\Services\Games;

use App\Models\Bet;
use App\Models\User;
use App\Services\ProvablyFairService;
use App\Services\WalletService;
use App\Services\VipService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PumpService
{
    protected $provablyFairService;
    protected $walletService;
    protected $vipService;

    public function __construct(
        ProvablyFairService $provablyFairService,
        WalletService $walletService,
        VipService $vipService
    ) {
        $this->provablyFairService = $provablyFairService;
        $this->walletService = $walletService;
        $this->vipService = $vipService;
    }

    /**
     * Get current pump round information
     */
    public function getCurrentRound()
    {
        $roundId = Cache::get('pump_current_round_id');
        
        if (!$roundId) {
            // Start new round
            return $this->startNewRound();
        }

        $roundData = Cache::get("pump_round_{$roundId}");
        
        if (!$roundData) {
            return $this->startNewRound();
        }

        return $roundData;
    }

    /**
     * Start a new pump round
     */
    public function startRound()
    {
        return $this->startNewRound();
    }

    /**
     * Start a new pump round (internal)
     */
    protected function startNewRound()
    {
        $roundId = Str::uuid()->toString();
        
        // Generate burst point (1.00 - 50.00x with exponential distribution)
        $burstPoint = $this->generateBurstPoint();
        
        // Generate server seed for provably fair
        $serverSeed = $this->provablyFairService->generateServerSeed();
        $serverSeedHash = hash('sha256', $serverSeed);
        
        $roundData = [
            'round_id' => $roundId,
            'status' => 'waiting', // waiting, pumping, burst
            'start_time' => null,
            'burst_point' => $burstPoint,
            'current_multiplier' => 1.00,
            'server_seed' => $serverSeed,
            'server_seed_hash' => $serverSeedHash,
            'bets' => [],
            'active_players' => 0,
        ];

        Cache::put('pump_current_round_id', $roundId, now()->addMinutes(10));
        Cache::put("pump_round_{$roundId}", $roundData, now()->addMinutes(10));
        Cache::put("pump_round_{$roundId}_bets", [], now()->addMinutes(10));

        return $roundData;
    }

    /**
     * Generate burst point using exponential distribution
     * Most rounds burst between 1x-3x, rare high multipliers up to 50x
     */
    protected function generateBurstPoint()
    {
        // Exponential distribution with lambda = 1.5
        // House edge: 1%
        $random = mt_rand() / mt_getrandmax();
        $burstPoint = -log($random) / 1.5;
        
        // Apply house edge (99% RTP)
        $burstPoint = $burstPoint * 0.99;
        
        // Clamp between 1.00 and 50.00
        $burstPoint = max(1.00, min(50.00, $burstPoint));
        
        return round($burstPoint, 2);
    }

    /**
     * Place a bet for the current round
     */
    public function placeBet(User $user, float $betAmount)
    {
        // Validation
        if ($betAmount < 1) {
            throw new \Exception('Minimum bet is ₱1');
        }

        $currentRound = $this->getCurrentRound();

        // Can only bet during waiting phase
        if ($currentRound['status'] !== 'waiting') {
            throw new \Exception('Cannot place bet during active round');
        }

        return DB::transaction(function () use ($user, $betAmount, $currentRound) {
            // Lock real balance
            $this->walletService->lockBalance($user, $betAmount);

            // Get or create seed for user
            $seed = $this->provablyFairService->getOrCreateSeed($user);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'pump',
                'bet_amount' => $betAmount,
                'status' => 'pending',
                'multiplier' => null,
                'payout' => 0,
                'profit' => -$betAmount,
                'seed_id' => $seed->id,
                'client_seed' => $seed->client_seed,
                'server_seed_hash' => $currentRound['server_seed_hash'],
                'nonce' => $seed->nonce,
                'game_data' => [
                    'round_id' => $currentRound['round_id'],
                    'cashed_out' => false,
                    'cashout_multiplier' => null,
                ],
            ]);

            // Add bet to round cache
            $bets = Cache::get("pump_round_{$currentRound['round_id']}_bets", []);
            $bets[$user->id] = [
                'bet_id' => $bet->id,
                'user_id' => $user->id,
                'username' => $user->name ?? $user->phone ?? 'Player',
                'bet_amount' => $betAmount,
                'cashed_out' => false,
                'cashout_multiplier' => null,
                'payout' => 0,
            ];
            Cache::put("pump_round_{$currentRound['round_id']}_bets", $bets, now()->addMinutes(10));

            // Update round data
            $currentRound['active_players'] = count($bets);
            Cache::put("pump_round_{$currentRound['round_id']}", $currentRound, now()->addMinutes(10));

            return [
                'bet_id' => $bet->id,
                'round_id' => $currentRound['round_id'],
                'bet_amount' => $betAmount,
                'server_seed_hash' => $currentRound['server_seed_hash'],
            ];
        });
    }

    /**
     * Cash out from current round
     */
    public function cashOut(User $user, string $roundId)
    {
        $roundData = Cache::get("pump_round_{$roundId}");
        
        if (!$roundData) {
            throw new \Exception('Round not found');
        }

        if ($roundData['status'] !== 'pumping') {
            throw new \Exception('Round is not active');
        }

        $bets = Cache::get("pump_round_{$roundId}_bets", []);
        
        if (!isset($bets[$user->id])) {
            throw new \Exception('No active bet found');
        }

        if ($bets[$user->id]['cashed_out']) {
            throw new \Exception('Already cashed out');
        }

        return DB::transaction(function () use ($user, $roundId, $roundData, &$bets) {
            $betData = $bets[$user->id];
            $currentMultiplier = $roundData['current_multiplier'];
            
            // Calculate payout
            $payout = $betData['bet_amount'] * $currentMultiplier;
            
            // Update bet record
            $bet = Bet::find($betData['bet_id']);
            $bet->update([
                'status' => 'won',
                'multiplier' => $currentMultiplier,
                'payout' => $payout,
                'profit' => $payout - $betData['bet_amount'],
                'game_data' => array_merge($bet->game_data ?? [], [
                    'cashed_out' => true,
                    'cashout_multiplier' => $currentMultiplier,
                ]),
            ]);

            // Process payout (unlock + credit)
            $this->walletService->unlockBalance($user, $betData['bet_amount']);
            $this->walletService->addBalance($user, $payout, 'game_win', "Pump cashout at {$currentMultiplier}x");

            // Update wagering for bonuses and VIP
            $this->walletService->updateWagering($user, $betData['bet_amount']);
            
            // Check VIP upgrade
            $this->vipService->checkAndUpgradeVip($user);

            // Update cache
            $bets[$user->id]['cashed_out'] = true;
            $bets[$user->id]['cashout_multiplier'] = $currentMultiplier;
            $bets[$user->id]['payout'] = $payout;
            Cache::put("pump_round_{$roundId}_bets", $bets, now()->addMinutes(10));

            return [
                'success' => true,
                'multiplier' => $currentMultiplier,
                'payout' => $payout,
                'profit' => $payout - $betData['bet_amount'],
            ];
        });
    }

    /**
     * Start pumping (server-side simulation)
     * This would typically be called by a scheduled job or real-time server
     */
    public function startPumping(string $roundId)
    {
        $roundData = Cache::get("pump_round_{$roundId}");
        
        if (!$roundData || $roundData['status'] !== 'waiting') {
            return false;
        }

        $roundData['status'] = 'pumping';
        $roundData['start_time'] = now()->timestamp;
        $roundData['current_multiplier'] = 1.00;
        
        Cache::put("pump_round_{$roundId}", $roundData, now()->addMinutes(10));
        
        return true;
    }

    /**
     * Update current multiplier (called periodically during pumping)
     */
    public function updateMultiplier(string $roundId, float $elapsed)
    {
        $roundData = Cache::get("pump_round_{$roundId}");
        
        if (!$roundData || $roundData['status'] !== 'pumping') {
            return null;
        }

        // Multiplier grows at 0.3x per second (slower than crash)
        $multiplier = 1.00 + ($elapsed * 0.3);
        $roundData['current_multiplier'] = round($multiplier, 2);

        // Check if we've reached burst point
        if ($multiplier >= $roundData['burst_point']) {
            return $this->burstPump($roundId);
        }

        Cache::put("pump_round_{$roundId}", $roundData, now()->addMinutes(10));
        
        return $roundData;
    }

    /**
     * Burst the pump (end round)
     */
    public function burstPump(string $roundId)
    {
        $roundData = Cache::get("pump_round_{$roundId}");
        
        if (!$roundData) {
            return null;
        }

        return DB::transaction(function () use ($roundId, $roundData) {
            $roundData['status'] = 'burst';
            $roundData['current_multiplier'] = $roundData['burst_point'];
            
            // Process all remaining bets (those who didn't cash out)
            $bets = Cache::get("pump_round_{$roundId}_bets", []);
            
            foreach ($bets as $userId => $betData) {
                if (!$betData['cashed_out']) {
                    $bet = Bet::find($betData['bet_id']);
                    $user = User::find($userId);
                    
                    if ($bet && $user) {
                        // Lost bet - unlock balance (already deducted)
                        $this->walletService->unlockBalance($user, $betData['bet_amount']);
                        
                        // Update bet status
                        $bet->update([
                            'status' => 'lost',
                            'multiplier' => $roundData['burst_point'],
                            'payout' => 0,
                            'profit' => -$betData['bet_amount'],
                            'game_data' => array_merge($bet->game_data ?? [], [
                                'burst_point' => $roundData['burst_point'],
                            ]),
                        ]);

                        // Still count wagering for bonuses/VIP even on loss
                        $this->walletService->updateWagering($user, $betData['bet_amount']);
                        $this->vipService->checkAndUpgradeVip($user);
                    }
                }
            }

            // Save final round state
            Cache::put("pump_round_{$roundId}", $roundData, now()->addHours(1));
            
            // Start new round after 5 seconds
            Cache::forget('pump_current_round_id');
            
            return $roundData;
        });
    }

    /**
     * Get active players in current round
     */
    public function getActivePlayers(string $roundId)
    {
        $bets = Cache::get("pump_round_{$roundId}_bets", []);
        return array_values($bets);
    }

    /**
     * Get recent rounds for history
     */
    public function getRecentRounds(int $limit = 10)
    {
        return Bet::where('game_type', 'pump')
            ->where('status', '!=', 'pending')
            ->select('game_data->round_id as round_id', 'multiplier')
            ->groupBy('game_data->round_id', 'multiplier')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($bet) {
                return [
                    'multiplier' => $bet->multiplier,
                ];
            });
    }
}
