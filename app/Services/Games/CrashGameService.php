<?php

namespace App\Services\Games;

use App\Models\User;
use App\Models\Bet;
use App\Services\ProvablyFairService;
use App\Services\WalletService;
use App\Services\BonusService;
use App\Services\VipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Broadcast;

class CrashGameService
{
    public function __construct(
        private ProvablyFairService $provablyFair,
        private WalletService $wallet,
        private BonusService $bonusService,
        private VipService $vipService
    ) {}

    /**
     * Start crash game round
     * This would typically be triggered by a scheduler every ~20-30 seconds
     */
    public function startRound(): array
    {
        // Generate crash point for this round (using random seeds, not user-specific)
        $serverSeed = \Illuminate\Support\Str::random(64);
        $clientSeed = \Illuminate\Support\Str::random(16);
        $serverSeedHash = hash('sha256', $serverSeed);
        
        $hash = $this->provablyFair->generateResult($serverSeed, $clientSeed, 0);
        $crashMultiplier = $this->provablyFair->hashToCrashMultiplier($hash);

        $roundId = uniqid('crash_', true);
        
        $roundData = [
            'round_id' => $roundId,
            'crash_multiplier' => $crashMultiplier,
            'server_seed_hash' => $serverSeedHash,
            'start_time' => now()->timestamp,
            'status' => 'waiting', // waiting -> running -> crashed
            'bets' => [],
        ];

        Cache::put("crash_round_current", $roundData, 600);

        // Broadcast round started
        // broadcast(new CrashRoundStarted($roundData));

        return [
            'round_id' => $roundId,
            'status' => 'waiting',
            'server_seed_hash' => $serverSeedHash,
        ];
    }

    /**
     * Place bet on current round
     */
    public function placeBet(User $user, float $betAmount, ?float $autoCashout = null): array
    {
        $roundData = Cache::get('crash_round_current');
        if (!$roundData || $roundData['status'] !== 'waiting') {
            throw new \Exception('No active round to bet on');
        }

        return DB::transaction(function () use ($user, $betAmount, $autoCashout, $roundData) {
            // Deduct bet
            $balanceUsed = $this->wallet->deductBet($user, $betAmount);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'crash',
                'bet_amount' => $betAmount,
                'multiplier' => 1.00,
                'payout' => 0,
                'profit' => -$betAmount,
                'target' => $autoCashout,
                'server_seed_hash' => $roundData['server_seed_hash'],
                'client_seed' => 'round_' . $roundData['round_id'],
                'nonce' => 0,
                'game_result' => [
                    'round_id' => $roundData['round_id'],
                    'auto_cashout' => $autoCashout,
                    'cashed_out' => false,
                ],
                'is_bonus_bet' => $balanceUsed['bonus_used'] > 0,
                'status' => 'pending',
            ]);

            // Add bet to round
            $roundData['bets'][] = [
                'bet_id' => $bet->id,
                'user_id' => $user->id,
                'amount' => $betAmount,
                'auto_cashout' => $autoCashout,
                'cashed_out' => false,
            ];
            Cache::put('crash_round_current', $roundData, 600);

            return [
                'bet_id' => $bet->id,
                'round_id' => $roundData['round_id'],
                'bet_amount' => $betAmount,
                'auto_cashout' => $autoCashout,
            ];
        });
    }

    /**
     * Manual cashout
     */
    public function cashout(User $user, int $betId, float $currentMultiplier): array
    {
        return DB::transaction(function () use ($user, $betId, $currentMultiplier) {
            $bet = Bet::where('id', $betId)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$bet) {
                throw new \InvalidArgumentException('Bet not found or already cashed out');
            }

            $roundData = Cache::get('crash_round_current');
            if (!$roundData || ($roundData['status'] === 'crashed' || $roundData['status'] === 'ended')) {
                throw new \Exception('Round has ended');
            }

            // Verify multiplier hasn't crashed yet
            if ($currentMultiplier >= $roundData['crash_multiplier']) {
                throw new \Exception('Game already crashed');
            }

            $payout = $bet->bet_amount * $currentMultiplier;
            $profit = $payout - $bet->bet_amount;

            // Update bet
            $bet->update([
                'result' => 'win',
                'status' => 'completed',
                'multiplier' => $currentMultiplier,
                'payout' => $payout,
                'profit' => $profit,
                'game_result' => array_merge($bet->game_result, [
                    'cashed_out' => true,
                    'cashout_multiplier' => $currentMultiplier,
                    'crash_multiplier' => $roundData['crash_multiplier'],
                ]),
            ]);

            // Credit winnings
            $this->wallet->creditWin($user, $payout, $bet->is_bonus_bet);

            // Update wagering progress for active bonuses
            $this->bonusService->updateWageringProgress($user, $bet->bet_amount);

            // Check for VIP upgrade
            $user->refresh();
            $this->vipService->checkForUpgrade($user);

            // Update round data
            foreach ($roundData['bets'] as &$roundBet) {
                if ($roundBet['bet_id'] === $betId) {
                    $roundBet['cashed_out'] = true;
                    $roundBet['cashout_multiplier'] = $currentMultiplier;
                    break;
                }
            }
            Cache::put('crash_round_current', $roundData, 600);

            return [
                'bet_id' => $bet->id,
                'cashout_multiplier' => round($currentMultiplier, 2),
                'payout' => round($payout, 2),
                'profit' => round($profit, 2),
            ];
        });
    }

    /**
     * End round (called when crash happens)
     */
    public function endRound(): array
    {
        $roundData = Cache::get('crash_round_current');
        if (!$roundData) {
            throw new \Exception('No active round');
        }

        $roundData['status'] = 'crashed';
        $crashMultiplier = $roundData['crash_multiplier'];

        // Process all pending bets that didn't cash out
        foreach ($roundData['bets'] as $roundBet) {
            if (!$roundBet['cashed_out']) {
                DB::transaction(function () use ($roundBet, $crashMultiplier) {
                    $bet = Bet::find($roundBet['bet_id']);
                    if ($bet && $bet->status === 'pending') {
                        $bet->update([
                            'result' => 'loss',
                            'status' => 'completed',
                            'multiplier' => 0,
                            'payout' => 0,
                            'profit' => -$bet->bet_amount,
                            'game_result' => array_merge($bet->game_result, [
                                'crash_multiplier' => $crashMultiplier,
                                'cashed_out' => false,
                            ]),
                        ]);
                    }
                });
            }
        }

        // Archive round
        Cache::put("crash_round_{$roundData['round_id']}", $roundData, 86400);
        Cache::forget('crash_round_current');

        return [
            'round_id' => $roundData['round_id'],
            'crash_multiplier' => $crashMultiplier,
            'total_bets' => count($roundData['bets']),
        ];
    }

    /**
     * Get current round status
     */
    public function getCurrentRound(): ?array
    {
        $roundData = Cache::get('crash_round_current');
        
        if (!$roundData) {
            return null;
        }

        return [
            'round_id' => $roundData['round_id'],
            'status' => $roundData['status'],
            'server_seed_hash' => $roundData['server_seed_hash'],
            'total_bets' => count($roundData['bets']),
        ];
    }
}
