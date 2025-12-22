<?php

namespace App\Services\Games;

use App\Models\User;
use App\Models\Bet;
use App\Services\ProvablyFairService;
use App\Services\WalletService;
use App\Services\BonusService;
use App\Services\VipService;
use Illuminate\Support\Facades\DB;

class PlinkoGameService
{
    // Multipliers for different row counts
    private const MULTIPLIERS = [
        8 => [
            'low' => [5.6, 2.1, 1.1, 1, 0.5, 1, 1.1, 2.1, 5.6],
            'medium' => [13, 3, 1.3, 0.7, 0.4, 0.7, 1.3, 3, 13],
            'high' => [29, 4, 1.5, 0.3, 0.2, 0.3, 1.5, 4, 29],
        ],
        12 => [
            'low' => [10, 3, 1.6, 1.2, 1.1, 1, 0.5, 1, 1.1, 1.2, 1.6, 3, 10],
            'medium' => [33, 11, 4, 2, 1.1, 1, 0.3, 1, 1.1, 2, 4, 11, 33],
            'high' => [170, 24, 8.1, 2, 0.7, 0.2, 0.2, 0.2, 0.7, 2, 8.1, 24, 170],
        ],
        16 => [
            'low' => [16, 9, 2, 1.4, 1.4, 1.2, 1.1, 1, 0.5, 1, 1.1, 1.2, 1.4, 1.4, 2, 9, 16],
            'medium' => [110, 41, 10, 5, 3, 1.5, 1, 0.5, 0.3, 0.5, 1, 1.5, 3, 5, 10, 41, 110],
            'high' => [1000, 130, 26, 9, 4, 2, 0.2, 0.2, 0.2, 0.2, 0.2, 2, 4, 9, 26, 130, 1000],
        ],
    ];

    public function __construct(
        private ProvablyFairService $provablyFair,
        private WalletService $wallet,
        private BonusService $bonusService,
        private VipService $vipService
    ) {}

    /**
     * Play Plinko
     */
    public function play(User $user, float $betAmount, string $risk = 'low', int $rows = 16): array
    {
        if (!in_array($risk, ['low', 'medium', 'high'])) {
            throw new \InvalidArgumentException('Risk must be "low", "medium", or "high"');
        }

        if (!in_array($rows, [8, 12, 16])) {
            throw new \InvalidArgumentException('Rows must be 8, 12, or 16');
        }

        return DB::transaction(function () use ($user, $betAmount, $risk, $rows) {
            // Get active seed
            $seed = $this->provablyFair->getActiveSeed($user->id);
            $nonce = $seed->incrementNonce();

            // Generate provably fair result
            $hash = $this->provablyFair->generateResult($seed->server_seed, $seed->client_seed, $nonce);
            $slot = $this->provablyFair->hashToPlinkoSlot($hash, $rows);

            // Get multiplier for the slot
            $multiplier = self::MULTIPLIERS[$rows][$risk][$slot];
            $payout = $betAmount * $multiplier;
            $profit = $payout - $betAmount;

            // Deduct bet
            $balanceUsed = $this->wallet->deductBet($user, $betAmount);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'plinko',
                'bet_amount' => $betAmount,
                'multiplier' => $multiplier,
                'payout' => $payout,
                'profit' => $profit,
                'server_seed_hash' => $seed->server_seed_hash,
                'client_seed' => $seed->client_seed,
                'nonce' => $nonce,
                'game_result' => [
                    'slot' => $slot,
                    'risk' => $risk,
                    'rows' => $rows,
                ],
                'is_bonus_bet' => $balanceUsed['bonus_used'] > 0,
                'result' => $profit > 0 ? 'win' : 'loss',
                'status' => 'completed',
            ]);

            // Credit win if applicable
            if ($payout > 0) {
                $this->wallet->creditWin($user, $payout, $bet->is_bonus_bet);
            }

            // Update wagering progress for active bonuses
            $this->bonusService->updateWageringProgress($user, $betAmount);

            // Check for VIP upgrade
            $user->refresh();
            $this->vipService->checkForUpgrade($user);

            return [
                'bet_id' => $bet->id,
                'result_slot' => $slot,
                'slot' => $slot,
                'risk' => $risk,
                'rows' => $rows,
                'multiplier' => $multiplier,
                'bet_amount' => $betAmount,
                'payout' => round($payout, 2),
                'profit' => round($profit, 2),
                'is_win' => $profit > 0,
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
