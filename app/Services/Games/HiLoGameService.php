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

class HiLoGameService
{
    public function __construct(
        private ProvablyFairService $provablyFair,
        private WalletService $wallet,
        private BonusService $bonusService,
        private VipService $vipService
    ) {}

    /**
     * Start new Hi-Lo game
     */
    public function start(User $user, float $betAmount): array
    {
        return DB::transaction(function () use ($user, $betAmount) {
            // Deduct bet
            $balanceUsed = $this->wallet->deductBet($user, $betAmount);

            // Get active seed
            $seed = $this->provablyFair->getActiveSeed($user->id);
            $nonce = $seed->incrementNonce();

            // Generate first card
            $hash = $this->provablyFair->generateResult($seed->server_seed, $seed->client_seed, $nonce);
            $card = $this->provablyFair->hashToCard($hash);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'hilo',
                'bet_amount' => $betAmount,
                'multiplier' => 1.00,
                'payout' => 0,
                'profit' => -$betAmount,
                'server_seed_hash' => $seed->server_seed_hash,
                'client_seed' => $seed->client_seed,
                'nonce' => $nonce,
                'game_result' => [
                    'round' => 0,
                    'cards' => [$card],
                    'current_multiplier' => 1.00,
                ],
                'is_bonus_bet' => $balanceUsed['bonus_used'] > 0,
                'status' => 'pending',
            ]);

            // Cache game state
            $gameState = [
                'bet_id' => $bet->id,
                'current_card' => $card,
                'round' => 0,
                'multiplier' => 1.00,
                'cards_history' => [$card],
            ];
            Cache::put("hilo_game_{$user->id}_{$bet->id}", $gameState, 3600);

            return [
                'bet_id' => $bet->id,
                'current_card' => $card,
                'round' => 0,
                'multiplier' => 1.00,
                'can_cashout' => false,
            ];
        });
    }

    /**
     * Make prediction (high or low)
     */
    public function predict(User $user, int $betId, string $prediction): array
    {
        if (!in_array($prediction, ['high', 'low'])) {
            throw new \Exception('Invalid prediction. Must be "high" or "low"');
        }

        return DB::transaction(function () use ($user, $betId, $prediction) {
            $bet = Bet::where('id', $betId)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $gameState = Cache::get("hilo_game_{$user->id}_{$betId}");
            if (!$gameState) {
                throw new \Exception('Game session expired');
            }

            // Get seed and increment nonce
            $seed = $this->provablyFair->getActiveSeed($user->id);
            $nonce = $seed->incrementNonce();

            // Generate next card
            $hash = $this->provablyFair->generateResult($seed->server_seed, $seed->client_seed, $nonce);
            $newCard = $this->provablyFair->hashToCard($hash);

            $currentCard = $gameState['current_card'];
            $isCorrect = ($prediction === 'high' && $newCard['value'] > $currentCard['value']) ||
                        ($prediction === 'low' && $newCard['value'] < $currentCard['value']);

            if ($isCorrect) {
                // Win round - increase multiplier
                $gameState['round']++;
                $gameState['multiplier'] *= 1.5; // 1.5x per correct prediction
                $gameState['current_card'] = $newCard;
                $gameState['cards_history'][] = $newCard;

                // Update bet
                $bet->update([
                    'multiplier' => $gameState['multiplier'],
                    'game_result' => [
                        'round' => $gameState['round'],
                        'cards' => $gameState['cards_history'],
                        'current_multiplier' => $gameState['multiplier'],
                    ],
                ]);

                Cache::put("hilo_game_{$user->id}_{$betId}", $gameState, 3600);

                return [
                    'bet_id' => $bet->id,
                    'is_correct' => true,
                    'new_card' => $newCard,
                    'round' => $gameState['round'],
                    'multiplier' => round($gameState['multiplier'], 2),
                    'potential_payout' => round($bet->bet_amount * $gameState['multiplier'], 2),
                    'can_cashout' => true,
                ];
            } else {
                // Loss - game over
                $bet->update([
                    'result' => 'loss',
                    'status' => 'completed',
                    'payout' => 0,
                    'profit' => -$bet->bet_amount,
                    'game_result' => [
                        'round' => $gameState['round'],
                        'cards' => array_merge($gameState['cards_history'], [$newCard]),
                        'final_multiplier' => $gameState['multiplier'],
                        'result' => 'loss',
                    ],
                ]);

                Cache::forget("hilo_game_{$user->id}_{$betId}");

                return [
                    'bet_id' => $bet->id,
                    'is_correct' => false,
                    'new_card' => $newCard,
                    'game_over' => true,
                    'final_multiplier' => round($gameState['multiplier'], 2),
                    'payout' => 0,
                ];
            }
        });
    }

    /**
     * Cash out current winnings
     */
    public function cashout(User $user, int $betId): array
    {
        return DB::transaction(function () use ($user, $betId) {
            $bet = Bet::where('id', $betId)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $gameState = Cache::get("hilo_game_{$user->id}_{$betId}");
            if (!$gameState) {
                throw new \Exception('Game session expired');
            }

            if ($gameState['round'] === 0) {
                throw new \Exception('Cannot cashout before first prediction');
            }

            $payout = $bet->bet_amount * $gameState['multiplier'];
            $profit = $payout - $bet->bet_amount;

            // Update bet
            $bet->update([
                'result' => 'win',
                'status' => 'completed',
                'payout' => $payout,
                'profit' => $profit,
                'multiplier' => $gameState['multiplier'],
                'game_result' => array_merge($bet->game_result, ['result' => 'cashout']),
            ]);

            // Credit winnings
            $this->wallet->creditWin($user, $payout, $bet->is_bonus_bet);

            // Update wagering progress for active bonuses
            $this->bonusService->updateWageringProgress($user, $bet->bet_amount);

            // Check for VIP upgrade
            $user->refresh();
            $this->vipService->checkForUpgrade($user);

            Cache::forget("hilo_game_{$user->id}_{$betId}");

            return [
                'bet_id' => $bet->id,
                'rounds_won' => $gameState['round'],
                'final_multiplier' => round($gameState['multiplier'], 2),
                'payout' => round($payout, 2),
                'profit' => round($profit, 2),
            ];
        });
    }
}
