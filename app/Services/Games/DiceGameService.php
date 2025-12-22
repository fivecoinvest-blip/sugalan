<?php

namespace App\Services\Games;

use App\Models\User;
use App\Models\Bet;
use App\Services\ProvablyFairService;
use App\Services\WalletService;
use App\Services\BonusService;
use App\Services\VipService;
use Illuminate\Support\Facades\DB;

class DiceGameService
{
    public function __construct(
        private ProvablyFairService $provablyFair,
        private WalletService $wallet,
        private BonusService $bonusService,
        private VipService $vipService
    ) {}

    /**
     * Play dice game
     * User predicts if roll will be over/under target
     */
    public function play(User $user, float $betAmount, string $prediction, float $target): array
    {
        // Validate inputs
        if (!in_array($prediction, ['over', 'under'])) {
            throw new \Exception('Invalid prediction. Must be "over" or "under"');
        }

        if ($target < 1 || $target > 98.99) {
            throw new \Exception('Target must be between 1 and 98.99');
        }

        return DB::transaction(function () use ($user, $betAmount, $prediction, $target) {
            // Get active seed
            $seed = $this->provablyFair->getActiveSeed($user->id);
            $nonce = $seed->incrementNonce();

            // Generate provably fair result
            $hash = $this->provablyFair->generateResult(
                $seed->server_seed,
                $seed->client_seed,
                $nonce
            );
            
            $roll = $this->provablyFair->hashToRoll($hash);

            // Calculate multiplier based on target
            $winChance = $prediction === 'over' ? (100 - $target) : $target;
            $houseEdge = 1; // 1%
            $multiplier = (100 - $houseEdge) / $winChance;

            // Determine win/loss
            $isWin = ($prediction === 'over' && $roll > $target) || 
                     ($prediction === 'under' && $roll < $target);

            $payout = $isWin ? $betAmount * $multiplier : 0;
            $profit = $payout - $betAmount;

            // Deduct bet from wallet
            $balanceUsed = $this->wallet->deductBet($user, $betAmount);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'dice',
                'bet_amount' => $betAmount,
                'multiplier' => $multiplier,
                'payout' => $payout,
                'profit' => $profit,
                'server_seed_hash' => $seed->server_seed_hash,
                'client_seed' => $seed->client_seed,
                'nonce' => $nonce,
                'game_result' => [
                    'roll' => $roll,
                    'target' => $target,
                    'prediction' => $prediction,
                    'win_chance' => $winChance,
                ],
                'is_bonus_bet' => $balanceUsed['bonus_used'] > 0,
                'result' => $isWin ? 'win' : 'loss',
                'status' => 'completed',
            ]);

            // Credit win if applicable
            if ($isWin && $payout > 0) {
                $this->wallet->creditWin($user, $payout, $bet->is_bonus_bet);
            }

            // Update wagering progress for active bonuses
            $this->bonusService->updateWageringProgress($user, $betAmount);

            // Check for VIP upgrade
            $user->refresh(); // Refresh to get updated total_wagered
            $this->vipService->checkForUpgrade($user);

            return [
                'bet_id' => $bet->id,
                'result' => $roll,
                'roll' => $roll,
                'target' => $target,
                'prediction' => $prediction,
                'win_chance' => $winChance,
                'multiplier' => round($multiplier, 2),
                'bet_amount' => $betAmount,
                'payout' => round($payout, 2),
                'profit' => round($profit, 2),
                'is_win' => $isWin,
                'balance' => [
                    'real' => $user->wallet->real_balance,
                    'bonus' => $user->wallet->bonus_balance,
                ],
                'provably_fair' => [
                    'server_seed_hash' => $seed->server_seed_hash,
                    'client_seed' => $seed->client_seed,
                    'nonce' => $nonce,
                ],
            ];
        });
    }
}
