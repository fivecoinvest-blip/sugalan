<?php

namespace App\Services\Games;

use App\Models\User;
use App\Models\Bet;
use App\Services\ProvablyFairService;
use App\Services\WalletService;
use App\Services\BonusService;
use App\Services\VipService;
use Illuminate\Support\Facades\DB;

class KenoGameService
{
    private const MAX_NUMBER = 40;
    private const DRAWN_COUNT = 10;

    // Payout multipliers based on selections and hits
    private const MULTIPLIERS = [
        1 => [0, 3.8],
        2 => [0, 1.5, 9],
        3 => [0, 1, 2.5, 25],
        4 => [0, 0.5, 2, 6, 50],
        5 => [0, 0.5, 1, 3, 12, 100],
        6 => [0, 0, 1, 2, 4, 20, 200],
        7 => [0, 0, 0.5, 1, 3, 8, 50, 400],
        8 => [0, 0, 0, 1, 2, 5, 15, 100, 800],
        9 => [0, 0, 0, 0.5, 2, 4, 10, 30, 200, 1500],
        10 => [0, 0, 0, 0, 1, 3, 7, 20, 60, 400, 3000],
    ];

    public function __construct(
        private ProvablyFairService $provablyFair,
        private WalletService $wallet,
        private BonusService $bonusService,
        private VipService $vipService
    ) {}

    /**
     * Play Keno
     */
    public function play(User $user, float $betAmount, array $selectedNumbers): array
    {
        // Validate selections
        $count = count($selectedNumbers);
        if ($count < 1 || $count > 10) {
            throw new \InvalidArgumentException('Must select between 1 and 10 numbers');
        }

        foreach ($selectedNumbers as $num) {
            if ($num < 1 || $num > self::MAX_NUMBER) {
                throw new \InvalidArgumentException("Number must be between 1 and " . self::MAX_NUMBER);
            }
        }

        if (count($selectedNumbers) !== count(array_unique($selectedNumbers))) {
            throw new \InvalidArgumentException('Cannot select duplicate numbers');
        }

        return DB::transaction(function () use ($user, $betAmount, $selectedNumbers, $count) {
            // Get active seed
            $seed = $this->provablyFair->getActiveSeed($user->id);
            $nonce = $seed->incrementNonce();

            // Generate provably fair result
            $hash = $this->provablyFair->generateResult($seed->server_seed, $seed->client_seed, $nonce);
            $drawnNumbers = $this->provablyFair->hashToKenoNumbers($hash, self::DRAWN_COUNT, self::MAX_NUMBER);

            // Calculate hits
            $hits = count(array_intersect($selectedNumbers, $drawnNumbers));

            // Get multiplier
            $multiplier = self::MULTIPLIERS[$count][$hits] ?? 0;
            $payout = $betAmount * $multiplier;
            $profit = $payout - $betAmount;

            // Deduct bet
            $balanceUsed = $this->wallet->deductBet($user, $betAmount);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'keno',
                'bet_amount' => $betAmount,
                'multiplier' => $multiplier,
                'payout' => $payout,
                'profit' => $profit,
                'server_seed_hash' => $seed->server_seed_hash,
                'client_seed' => $seed->client_seed,
                'nonce' => $nonce,
                'game_result' => [
                    'selected_numbers' => $selectedNumbers,
                    'drawn_numbers' => $drawnNumbers,
                    'hits' => $hits,
                    'selections_count' => $count,
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
                'selected_numbers' => $selectedNumbers,
                'drawn_numbers' => $drawnNumbers,
                'hits' => $hits,
                'matches' => $hits,
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
