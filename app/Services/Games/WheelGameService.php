<?php

namespace App\Services\Games;

use App\Models\User;
use App\Models\Bet;
use App\Services\ProvablyFairService;
use App\Services\WalletService;
use App\Services\BonusService;
use App\Services\VipService;
use Illuminate\Support\Facades\DB;

class WheelGameService
{
    // Segment configuration for different risk levels
    private const SEGMENTS = [
        'low' => [
            'count' => 10,
            'multipliers' => [1.2, 1.2, 1.2, 1.5, 1.5, 1.5, 2, 2, 3, 5],
        ],
        'medium' => [
            'count' => 20,
            'multipliers' => [0, 0, 1.2, 1.2, 1.5, 1.5, 1.8, 1.8, 2, 2, 2.5, 2.5, 3, 3, 5, 5, 10, 10, 20, 50],
        ],
        'high' => [
            'count' => 30,
            'multipliers' => [0, 0, 0, 0, 0, 1, 1, 1.2, 1.2, 1.5, 1.5, 1.8, 1.8, 2, 2, 3, 3, 5, 5, 8, 8, 10, 10, 15, 20, 25, 50, 100, 200, 500],
        ],
    ];

    public function __construct(
        private ProvablyFairService $provablyFair,
        private WalletService $wallet,
        private BonusService $bonusService,
        private VipService $vipService
    ) {}

    /**
     * Spin the wheel
     */
    public function spin(User $user, float $betAmount, string $risk = 'low'): array
    {
        if (!in_array($risk, ['low', 'medium', 'high'])) {
            throw new \Exception('Risk must be "low", "medium", or "high"');
        }

        return DB::transaction(function () use ($user, $betAmount, $risk) {
            // Get active seed
            $seed = $this->provablyFair->getActiveSeed($user->id);
            $nonce = $seed->incrementNonce();

            // Generate provably fair result
            $hash = $this->provablyFair->generateResult($seed->server_seed, $seed->client_seed, $nonce);
            $segmentCount = self::SEGMENTS[$risk]['count'];
            $segment = $this->provablyFair->hashToWheelSegment($hash, $segmentCount);

            // Get multiplier
            $multiplier = self::SEGMENTS[$risk]['multipliers'][$segment];
            $payout = $betAmount * $multiplier;
            $profit = $payout - $betAmount;

            // Deduct bet
            $balanceUsed = $this->wallet->deductBet($user, $betAmount);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'wheel',
                'bet_amount' => $betAmount,
                'multiplier' => $multiplier,
                'payout' => $payout,
                'profit' => $profit,
                'server_seed_hash' => $seed->server_seed_hash,
                'client_seed' => $seed->client_seed,
                'nonce' => $nonce,
                'game_result' => [
                    'segment' => $segment,
                    'risk' => $risk,
                    'total_segments' => $segmentCount,
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
                'result_segment' => $segment,  // Add result_segment
                'segment' => $segment,
                'risk' => $risk,
                'multiplier' => $multiplier,
                'bet_amount' => $betAmount,
                'payout' => round($payout, 2),
                'profit' => round($profit, 2),
                'is_win' => $profit > 0,
                'balance' => [  // Add balance
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

    /**
     * Get wheel configuration for client
     */
    public function getWheelConfig(string $risk = 'low'): array
    {
        if (!isset(self::SEGMENTS[$risk])) {
            throw new \Exception('Invalid risk level');
        }

        $multipliers = self::SEGMENTS[$risk]['multipliers'];
        $totalSegments = count($multipliers);
        
        // Format segments with multiplier, color, and probability
        $segments = [];
        $colors = ['red', 'blue', 'green', 'yellow', 'purple', 'orange'];
        foreach ($multipliers as $index => $multiplier) {
            $segments[] = [
                'multiplier' => $multiplier,
                'color' => $colors[$index % count($colors)],
                'probability' => round(1 / $totalSegments, 4),
            ];
        }

        return [
            'risk' => $risk,
            'segments' => $segments,
            'segment_count' => $totalSegments,
        ];
    }
}
