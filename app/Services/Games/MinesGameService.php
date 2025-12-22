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

class MinesGameService
{
    private const GRID_SIZE = 5; // 5x5 grid = 25 tiles

    public function __construct(
        private ProvablyFairService $provablyFair,
        private WalletService $wallet,
        private BonusService $bonusService,
        private VipService $vipService
    ) {}

    /**
     * Start new Mines game
     */
    public function start(User $user, float $betAmount, int $mineCount): array
    {
        if ($mineCount < 1 || $mineCount > 24) {
            throw new \InvalidArgumentException('Mine count must be between 1 and 24');
        }

        return DB::transaction(function () use ($user, $betAmount, $mineCount) {
            // Deduct bet
            $balanceUsed = $this->wallet->deductBet($user, $betAmount);

            // Get active seed
            $seed = $this->provablyFair->getActiveSeed($user->id);
            $nonce = $seed->incrementNonce();

            // Generate mine positions
            $hash = $this->provablyFair->generateResult($seed->server_seed, $seed->client_seed, $nonce);
            $minePositions = $this->provablyFair->hashToMinesPositions($hash, self::GRID_SIZE, $mineCount);

            // Create bet record
            $bet = Bet::create([
                'user_id' => $user->id,
                'game_type' => 'mines',
                'bet_amount' => $betAmount,
                'multiplier' => 1.00,
                'payout' => 0,
                'profit' => -$betAmount,
                'server_seed_hash' => $seed->server_seed_hash,
                'client_seed' => $seed->client_seed,
                'nonce' => $nonce,
                'game_result' => [
                    'mine_count' => $mineCount,
                    'revealed_tiles' => [],
                    'gems_found' => 0,
                ],
                'is_bonus_bet' => $balanceUsed['bonus_used'] > 0,
                'status' => 'pending',
            ]);

            // Cache game state (don't reveal mine positions yet)
            $gameState = [
                'bet_id' => $bet->id,
                'mine_positions' => $minePositions,
                'mine_count' => $mineCount,
                'revealed_tiles' => [],
                'gems_found' => 0,
                'multiplier' => 1.00,
            ];
            Cache::put("mines_game_{$user->id}_{$bet->id}", $gameState, 3600);

            return [
                'bet_id' => $bet->id,
                'grid_size' => self::GRID_SIZE,
                'mine_count' => $mineCount,
                'mines_count' => $mineCount,
                'revealed_tiles' => [],
                'current_multiplier' => 1.00,
                'total_tiles' => self::GRID_SIZE * self::GRID_SIZE,
                'multiplier' => 1.00,
                'balance' => [
                    'real' => $user->wallet->real_balance,
                    'bonus' => $user->wallet->bonus_balance,
                ],
            ];
        });
    }

    /**
     * Reveal a tile
     */
    public function reveal(User $user, int $betId, int $position): array
    {
        if ($position < 0 || $position >= (self::GRID_SIZE * self::GRID_SIZE)) {
            throw new \InvalidArgumentException('Invalid tile position');
        }

        return DB::transaction(function () use ($user, $betId, $position) {
            $bet = Bet::where('id', $betId)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();
                
            if (!$bet) {
                throw new \InvalidArgumentException('Bet not found or game already ended');
            }

            $gameState = Cache::get("mines_game_{$user->id}_{$betId}");
            if (!$gameState) {
                throw new \Exception('Game session expired');
            }

            if (in_array($position, $gameState['revealed_tiles'])) {
                throw new \InvalidArgumentException('Tile already revealed');
            }

            $isMine = in_array($position, $gameState['mine_positions']);

            if ($isMine) {
                // Hit a mine - game over
                $bet->update([
                    'result' => 'loss',
                    'status' => 'completed',
                    'payout' => 0,
                    'profit' => -$bet->bet_amount,
                    'game_result' => [
                        'mine_count' => $gameState['mine_count'],
                        'revealed_tiles' => array_merge($gameState['revealed_tiles'], [$position]),
                        'gems_found' => $gameState['gems_found'],
                        'hit_mine_at' => $position,
                        'mine_positions' => $gameState['mine_positions'],
                    ],
                ]);

                Cache::forget("mines_game_{$user->id}_{$betId}");

                return [
                    'bet_id' => $bet->id,
                    'position' => $position,
                    'is_mine' => true,
                    'game_over' => true,
                    'gems_found' => $gameState['gems_found'],
                    'mine_positions' => $gameState['mine_positions'],
                    'balance' => [
                        'real' => $user->wallet->real_balance,
                        'bonus' => $user->wallet->bonus_balance,
                    ],
                ];
            } else {
                // Found a gem
                $gameState['revealed_tiles'][] = $position;
                $gameState['gems_found']++;

                // Calculate multiplier
                $totalTiles = self::GRID_SIZE * self::GRID_SIZE;
                $safeTiles = $totalTiles - $gameState['mine_count'];
                $tilesRevealed = count($gameState['revealed_tiles']);
                
                // Progressive multiplier based on risk
                $gameState['multiplier'] = $this->calculateMultiplier($safeTiles, $tilesRevealed);

                // Update bet
                $bet->update([
                    'multiplier' => $gameState['multiplier'],
                    'game_result' => [
                        'mine_count' => $gameState['mine_count'],
                        'revealed_tiles' => $gameState['revealed_tiles'],
                        'gems_found' => $gameState['gems_found'],
                    ],
                ]);

                Cache::put("mines_game_{$user->id}_{$betId}", $gameState, 3600);

                return [
                    'bet_id' => $bet->id,
                    'position' => $position,
                    'is_mine' => false,
                    'game_over' => false,
                    'gems_found' => $gameState['gems_found'],
                    'revealed_tiles' => $gameState['revealed_tiles'],
                    'multiplier' => round($gameState['multiplier'], 2),
                    'current_multiplier' => round($gameState['multiplier'], 2),
                    'potential_payout' => round($bet->bet_amount * $gameState['multiplier'], 2),
                    'can_cashout' => true,
                    'balance' => [
                        'real' => $user->wallet->real_balance,
                        'bonus' => $user->wallet->bonus_balance,
                    ],
                ];
            }
        });
    }

    /**
     * Cash out
     */
    public function cashout(User $user, int $betId): array
    {
        return DB::transaction(function () use ($user, $betId) {
            $bet = Bet::where('id', $betId)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $gameState = Cache::get("mines_game_{$user->id}_{$betId}");
            if (!$gameState || $gameState['gems_found'] === 0) {
                throw new \Exception('Must reveal at least one gem before cashing out');
            }

            $payout = $bet->bet_amount * $gameState['multiplier'];
            $profit = $payout - $bet->bet_amount;

            $bet->update([
                'result' => 'win',
                'status' => 'completed',
                'payout' => $payout,
                'profit' => $profit,
                'multiplier' => $gameState['multiplier'],
                'game_result' => array_merge($bet->game_result, [
                    'result' => 'cashout',
                    'mine_positions' => $gameState['mine_positions'],
                ]),
            ]);

            $this->wallet->creditWin($user, $payout, $bet->is_bonus_bet);
            
            // Update wagering progress for active bonuses
            $this->bonusService->updateWageringProgress($user, $bet->bet_amount);

            // Check for VIP upgrade
            $user->refresh();
            $this->vipService->checkForUpgrade($user);
            
            Cache::forget("mines_game_{$user->id}_{$betId}");

            return [
                'bet_id' => $bet->id,
                'gems_found' => $gameState['gems_found'],
                'final_multiplier' => round($gameState['multiplier'], 2),
                'multiplier' => round($gameState['multiplier'], 2),
                'payout' => round($payout, 2),
                'profit' => round($profit, 2),
                'mine_positions' => $gameState['mine_positions'],
                'balance' => [
                    'real' => $user->wallet->real_balance,
                    'bonus' => $user->wallet->bonus_balance,
                ],
            ];
        });
    }

    /**
     * Calculate multiplier based on tiles revealed
     */
    private function calculateMultiplier(int $safeTiles, int $tilesRevealed): float
    {
        // Multiplier should increase as you reveal more tiles
        // Each safe tile revealed increases the multiplier
        $multiplier = 1.0;
        $houseEdge = 0.03; // 3% house edge

        for ($i = 0; $i < $tilesRevealed; $i++) {
            // Progressive multiplier: (totalSafeTiles / remainingSafeTiles)
            $remainingSafe = $safeTiles - $i;
            $multiplier *= (($safeTiles + 3.0) / $remainingSafe) * (1 - $houseEdge);
        }

        return $multiplier;
    }
}
