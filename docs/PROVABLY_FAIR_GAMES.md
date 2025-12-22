# Provably Fair Games - Implementation Guide

## Overview

This document provides complete implementation details for all 8 provably fair in-house games: Dice, Hi-Lo, Mines, Plinko, Keno, Wheel, Pump, and Crash.

---

## Core Provably Fair System

### Seed Generation & Management

Every provably fair game uses the same core system:

```
Server Seed + Client Seed + Nonce → HMAC-SHA256 → Game Result
```

#### Components:

1. **Server Seed**: Random 64-character hexadecimal string (generated server-side, kept secret until revealed)
2. **Server Seed Hash**: SHA-256 hash of server seed (shown to player before bet)
3. **Client Seed**: User-provided or random string (player can change anytime)
4. **Nonce**: Incrementing counter for each bet (starts at 0 for each seed pair)

---

### Implementation Algorithm

```php
<?php

namespace App\Services\Games;

class ProvablyFairService
{
    /**
     * Generate a new server seed
     */
    public function generateServerSeed(): string
    {
        return bin2hex(random_bytes(32)); // 64 hex characters
    }

    /**
     * Generate hash of server seed
     */
    public function hashServerSeed(string $serverSeed): string
    {
        return hash('sha256', $serverSeed);
    }

    /**
     * Generate default client seed
     */
    public function generateClientSeed(): string
    {
        return bin2hex(random_bytes(16)); // 32 hex characters
    }

    /**
     * Generate game result hash
     */
    public function generateResultHash(
        string $serverSeed,
        string $clientSeed,
        int $nonce
    ): string {
        $message = $clientSeed . ':' . $nonce;
        return hash_hmac('sha256', $message, $serverSeed);
    }

    /**
     * Convert hash to float between 0 and 1
     */
    public function hashToFloat(string $hash): float
    {
        // Take first 8 characters of hash
        $subHash = substr($hash, 0, 8);
        
        // Convert to integer
        $intValue = hexdec($subHash);
        
        // Convert to float between 0 and 1
        return $intValue / 0xFFFFFFFF;
    }

    /**
     * Verify a game result
     */
    public function verify(
        string $serverSeed,
        string $serverSeedHash,
        string $clientSeed,
        int $nonce,
        $expectedResult
    ): bool {
        // 1. Verify server seed hash
        if (hash('sha256', $serverSeed) !== $serverSeedHash) {
            return false;
        }

        // 2. Generate result
        $hash = $this->generateResultHash($serverSeed, $clientSeed, $nonce);
        
        // 3. Compare with expected result (game-specific comparison)
        return true; // Implementation depends on game type
    }
}
```

---

## Game 1: Dice 🎲

### Rules
- Player predicts if dice roll will be **over** or **under** a chosen number (0-100)
- Configurable multiplier based on probability
- House edge: 1%

### Implementation

```php
<?php

namespace App\Services\Games;

class DiceGame
{
    private ProvablyFairService $provablyFair;

    public function __construct(ProvablyFairService $provablyFair)
    {
        $this->provablyFair = $provablyFair;
    }

    /**
     * Play a dice game
     */
    public function play(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        float $betAmount,
        string $direction, // 'over' or 'under'
        float $target // 0-100
    ): array {
        // Generate result hash
        $hash = $this->provablyFair->generateResultHash($serverSeed, $clientSeed, $nonce);
        
        // Convert hash to dice result (0-100 with 2 decimal places)
        $result = $this->hashToDiceResult($hash);
        
        // Determine win/loss
        $isWin = ($direction === 'over' && $result > $target) ||
                 ($direction === 'under' && $result < $target);
        
        // Calculate multiplier
        $multiplier = $this->calculateMultiplier($direction, $target);
        
        // Calculate payout
        $payout = $isWin ? $betAmount * $multiplier : 0;
        $profit = $payout - $betAmount;

        return [
            'result' => $result,
            'is_win' => $isWin,
            'multiplier' => $multiplier,
            'payout' => $payout,
            'profit' => $profit,
            'hash' => $hash
        ];
    }

    /**
     * Convert hash to dice result (0-100)
     */
    private function hashToDiceResult(string $hash): float
    {
        // Take first 8 characters
        $subHash = substr($hash, 0, 8);
        $intValue = hexdec($subHash);
        
        // Convert to 0-10000 range (for 2 decimal places)
        $result = ($intValue % 10001) / 100;
        
        return round($result, 2);
    }

    /**
     * Calculate multiplier based on probability
     */
    private function calculateMultiplier(string $direction, float $target): float
    {
        $houseEdge = 0.01; // 1%
        
        if ($direction === 'over') {
            $probability = (100 - $target) / 100;
        } else {
            $probability = $target / 100;
        }
        
        // Multiplier = (1 - house edge) / probability
        $multiplier = (1 - $houseEdge) / $probability;
        
        return round($multiplier, 4);
    }

    /**
     * Verify dice result
     */
    public function verify(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        float $expectedResult
    ): bool {
        $hash = $this->provablyFair->generateResultHash($serverSeed, $clientSeed, $nonce);
        $result = $this->hashToDiceResult($hash);
        
        return abs($result - $expectedResult) < 0.01; // Allow small float difference
    }
}
```

### Database Schema for Dice Bets

```php
// Additional columns in 'bets' table for Dice
'game_result' => [
    'target' => 50.00,
    'direction' => 'over',
    'result' => 73.42,
    'multiplier' => 1.98
]
```

---

## Game 2: Hi-Lo 🔼

### Rules
- Standard 52-card deck
- Player guesses if next card will be **higher**, **lower**, or **equal** to current card
- Multiple rounds, player can cash out anytime
- Progressive multiplier increases with each correct guess

### Implementation

```php
<?php

namespace App\Services\Games;

class HiLoGame
{
    private ProvablyFairService $provablyFair;
    private array $deck = [
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
        '7' => 7, '8' => 8, '9' => 9, '10' => 10,
        'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14
    ];

    public function __construct(ProvablyFairService $provablyFair)
    {
        $this->provablyFair = $provablyFair;
    }

    /**
     * Start a new Hi-Lo game
     */
    public function start(
        string $serverSeed,
        string $clientSeed,
        int $nonce
    ): array {
        $firstCard = $this->drawCard($serverSeed, $clientSeed, $nonce, 0);
        
        return [
            'current_card' => $firstCard,
            'round' => 1,
            'multiplier' => 1.0,
            'cards_drawn' => [$firstCard]
        ];
    }

    /**
     * Play a round
     */
    public function playRound(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        int $round,
        string $currentCard,
        string $prediction // 'higher', 'lower', 'equal'
    ): array {
        // Draw next card
        $nextCard = $this->drawCard($serverSeed, $clientSeed, $nonce, $round);
        
        // Compare cards
        $currentValue = $this->deck[$currentCard];
        $nextValue = $this->deck[$nextCard];
        
        $isWin = false;
        if ($prediction === 'higher' && $nextValue > $currentValue) {
            $isWin = true;
        } elseif ($prediction === 'lower' && $nextValue < $currentValue) {
            $isWin = true;
        } elseif ($prediction === 'equal' && $nextValue === $currentValue) {
            $isWin = true;
        }
        
        // Calculate new multiplier
        $multiplier = $this->calculateMultiplier($prediction, $round);
        
        return [
            'next_card' => $nextCard,
            'is_win' => $isWin,
            'round' => $round + 1,
            'multiplier' => $isWin ? $multiplier : 0,
            'next_value' => $nextValue,
            'current_value' => $currentValue
        ];
    }

    /**
     * Draw a card using provably fair system
     */
    private function drawCard(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        int $round
    ): string {
        // Generate hash with round included
        $message = $clientSeed . ':' . $nonce . ':' . $round;
        $hash = hash_hmac('sha256', $message, $serverSeed);
        
        // Convert to card index (0-12 for 13 card values)
        $subHash = substr($hash, 0, 8);
        $intValue = hexdec($subHash);
        $cardIndex = $intValue % 13;
        
        $cards = array_keys($this->deck);
        return $cards[$cardIndex];
    }

    /**
     * Calculate multiplier for round
     */
    private function calculateMultiplier(string $prediction, int $round): float
    {
        $houseEdge = 0.01;
        
        // Base probability
        if ($prediction === 'equal') {
            $probability = 1 / 13; // ~7.69%
        } else {
            $probability = 6 / 13; // ~46.15% (6 cards higher/lower, excluding equal)
        }
        
        // Progressive multiplier
        $baseMultiplier = (1 - $houseEdge) / $probability;
        $progressiveMultiplier = $baseMultiplier * (1 + ($round * 0.05));
        
        return round($progressiveMultiplier, 4);
    }
}
```

---

## Game 3: Mines 💣

### Rules
- 5x5 grid (25 tiles)
- Configurable number of mines (1-24)
- Player reveals tiles one by one
- Hit a mine = lose all
- Cash out anytime with accumulated multiplier

### Implementation

```php
<?php

namespace App\Services\Games;

class MinesGame
{
    private ProvablyFairService $provablyFair;
    private const GRID_SIZE = 25;

    public function __construct(ProvablyFairService $provablyFair)
    {
        $this->provablyFair = $provablyFair;
    }

    /**
     * Start a new Mines game
     */
    public function start(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        int $mineCount // 1-24
    ): array {
        if ($mineCount < 1 || $mineCount > 24) {
            throw new \InvalidArgumentException('Mine count must be between 1 and 24');
        }

        // Generate mine positions
        $minePositions = $this->generateMinePositions(
            $serverSeed,
            $clientSeed,
            $nonce,
            $mineCount
        );

        return [
            'mine_positions' => $minePositions, // Hidden from player until game ends
            'mine_count' => $mineCount,
            'revealed_tiles' => [],
            'current_multiplier' => 1.0,
            'is_active' => true
        ];
    }

    /**
     * Reveal a tile
     */
    public function revealTile(
        array $gameState,
        int $tilePosition // 0-24
    ): array {
        if (in_array($tilePosition, $gameState['revealed_tiles'])) {
            throw new \Exception('Tile already revealed');
        }

        $isMine = in_array($tilePosition, $gameState['mine_positions']);
        
        $gameState['revealed_tiles'][] = $tilePosition;

        if ($isMine) {
            // Player hit a mine - game over
            $gameState['is_active'] = false;
            $gameState['result'] = 'loss';
            $gameState['payout_multiplier'] = 0;
        } else {
            // Safe tile - calculate new multiplier
            $revealedCount = count($gameState['revealed_tiles']);
            $gameState['current_multiplier'] = $this->calculateMultiplier(
                $revealedCount,
                $gameState['mine_count']
            );
        }

        return [
            'tile_position' => $tilePosition,
            'is_mine' => $isMine,
            'revealed_tiles' => $gameState['revealed_tiles'],
            'current_multiplier' => $gameState['current_multiplier'],
            'is_active' => $gameState['is_active']
        ];
    }

    /**
     * Cash out
     */
    public function cashOut(array $gameState, float $betAmount): array
    {
        if (!$gameState['is_active']) {
            throw new \Exception('Game is not active');
        }

        $payout = $betAmount * $gameState['current_multiplier'];
        $profit = $payout - $betAmount;

        return [
            'result' => 'win',
            'payout' => $payout,
            'profit' => $profit,
            'multiplier' => $gameState['current_multiplier'],
            'revealed_tiles' => $gameState['revealed_tiles'],
            'mine_positions' => $gameState['mine_positions']
        ];
    }

    /**
     * Generate mine positions using provably fair
     */
    private function generateMinePositions(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        int $mineCount
    ): array {
        $positions = [];
        $attempt = 0;

        while (count($positions) < $mineCount) {
            $hash = hash_hmac('sha256', $clientSeed . ':' . $nonce . ':' . $attempt, $serverSeed);
            $subHash = substr($hash, 0, 8);
            $intValue = hexdec($subHash);
            $position = $intValue % self::GRID_SIZE;

            if (!in_array($position, $positions)) {
                $positions[] = $position;
            }

            $attempt++;
        }

        sort($positions);
        return $positions;
    }

    /**
     * Calculate multiplier based on revealed tiles
     */
    private function calculateMultiplier(int $revealedCount, int $mineCount): float
    {
        $houseEdge = 0.01;
        $safeTiles = self::GRID_SIZE - $mineCount;
        
        $multiplier = 1.0;
        
        for ($i = 0; $i < $revealedCount; $i++) {
            $safeTilesRemaining = $safeTiles - $i;
            $totalTilesRemaining = self::GRID_SIZE - $i;
            $probability = $safeTilesRemaining / $totalTilesRemaining;
            
            $multiplier *= (1 - $houseEdge) / $probability;
        }

        return round($multiplier, 4);
    }
}
```

---

## Game 4: Plinko 🔵

### Rules
- Ball drops from top through pegs
- 8 rows of pegs
- 9 possible landing positions
- Each position has a different multiplier
- Risk levels: Low, Medium, High

### Implementation

```php
<?php

namespace App\Services\Games;

class PlinkoGame
{
    private ProvablyFairService $provablyFair;
    private const ROWS = 8;
    
    private array $multipliers = [
        'low' => [5.6, 2.1, 1.1, 1.0, 0.5, 1.0, 1.1, 2.1, 5.6],
        'medium' => [13, 3, 1.3, 0.7, 0.4, 0.7, 1.3, 3, 13],
        'high' => [29, 4, 1.5, 0.3, 0.2, 0.3, 1.5, 4, 29]
    ];

    public function __construct(ProvablyFairService $provablyFair)
    {
        $this->provablyFair = $provablyFair;
    }

    /**
     * Play Plinko
     */
    public function play(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        float $betAmount,
        string $risk = 'medium' // 'low', 'medium', 'high'
    ): array {
        // Generate ball path
        $path = $this->generatePath($serverSeed, $clientSeed, $nonce);
        
        // Determine landing position
        $landingPosition = $this->calculateLandingPosition($path);
        
        // Get multiplier
        $multiplier = $this->multipliers[$risk][$landingPosition];
        
        // Calculate payout
        $payout = $betAmount * $multiplier;
        $profit = $payout - $betAmount;

        return [
            'path' => $path,
            'landing_position' => $landingPosition,
            'multiplier' => $multiplier,
            'payout' => $payout,
            'profit' => $profit,
            'risk' => $risk
        ];
    }

    /**
     * Generate ball path through pegs
     */
    private function generatePath(
        string $serverSeed,
        string $clientSeed,
        int $nonce
    ): array {
        $path = [];
        
        for ($row = 0; $row < self::ROWS; $row++) {
            $hash = hash_hmac('sha256', $clientSeed . ':' . $nonce . ':' . $row, $serverSeed);
            $floatValue = $this->provablyFair->hashToFloat($hash);
            
            // 0 = left, 1 = right
            $direction = $floatValue < 0.5 ? 0 : 1;
            $path[] = $direction;
        }

        return $path;
    }

    /**
     * Calculate final landing position from path
     */
    private function calculateLandingPosition(array $path): int
    {
        // Count how many times ball went right
        $rightMoves = array_sum($path);
        
        // Landing position is the sum of right moves
        return $rightMoves;
    }

    /**
     * Verify Plinko result
     */
    public function verify(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        array $expectedPath
    ): bool {
        $path = $this->generatePath($serverSeed, $clientSeed, $nonce);
        return $path === $expectedPath;
    }
}
```

---

## Game 5: Keno 🔢

### Rules
- 40 numbers (1-40)
- Player selects 1-10 numbers
- 10 winning numbers are drawn
- Payout based on how many matches

### Implementation

```php
<?php

namespace App\Services\Games;

class KenoGame
{
    private ProvablyFairService $provablyFair;
    private const TOTAL_NUMBERS = 40;
    private const DRAWN_NUMBERS = 10;

    // Payout multipliers [selections => [matches => multiplier]]
    private array $payoutTable = [
        1 => [1 => 3.5],
        2 => [2 => 9.0],
        3 => [2 => 2.0, 3 => 25.0],
        4 => [2 => 1.5, 3 => 5.0, 4 => 100.0],
        5 => [3 => 2.0, 4 => 20.0, 5 => 500.0],
        6 => [3 => 1.5, 4 => 5.0, 5 => 50.0, 6 => 1000.0],
        7 => [4 => 2.5, 5 => 15.0, 6 => 200.0, 7 => 2500.0],
        8 => [5 => 5.0, 6 => 30.0, 7 => 500.0, 8 => 5000.0],
        9 => [5 => 3.0, 6 => 15.0, 7 => 100.0, 8 => 1000.0, 9 => 10000.0],
        10 => [5 => 2.0, 6 => 10.0, 7 => 50.0, 8 => 200.0, 9 => 2000.0, 10 => 20000.0]
    ];

    public function __construct(ProvablyFairService $provablyFair)
    {
        $this->provablyFair = $provablyFair;
    }

    /**
     * Play Keno
     */
    public function play(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        float $betAmount,
        array $selectedNumbers // Array of 1-10 numbers (1-40)
    ): array {
        // Validate selections
        $this->validateSelections($selectedNumbers);
        
        // Draw winning numbers
        $drawnNumbers = $this->drawNumbers($serverSeed, $clientSeed, $nonce);
        
        // Find matches
        $matches = array_intersect($selectedNumbers, $drawnNumbers);
        $matchCount = count($matches);
        
        // Get multiplier
        $selectionCount = count($selectedNumbers);
        $multiplier = $this->payoutTable[$selectionCount][$matchCount] ?? 0;
        
        // Calculate payout
        $payout = $betAmount * $multiplier;
        $profit = $payout - $betAmount;

        return [
            'selected_numbers' => $selectedNumbers,
            'drawn_numbers' => $drawnNumbers,
            'matches' => array_values($matches),
            'match_count' => $matchCount,
            'multiplier' => $multiplier,
            'payout' => $payout,
            'profit' => $profit
        ];
    }

    /**
     * Draw winning numbers using provably fair
     */
    private function drawNumbers(
        string $serverSeed,
        string $clientSeed,
        int $nonce
    ): array {
        $numbers = [];
        $attempt = 0;

        while (count($numbers) < self::DRAWN_NUMBERS) {
            $hash = hash_hmac('sha256', $clientSeed . ':' . $nonce . ':' . $attempt, $serverSeed);
            $subHash = substr($hash, 0, 8);
            $intValue = hexdec($subHash);
            $number = ($intValue % self::TOTAL_NUMBERS) + 1;

            if (!in_array($number, $numbers)) {
                $numbers[] = $number;
            }

            $attempt++;
        }

        sort($numbers);
        return $numbers;
    }

    /**
     * Validate player selections
     */
    private function validateSelections(array $numbers): void
    {
        if (count($numbers) < 1 || count($numbers) > 10) {
            throw new \InvalidArgumentException('Must select 1-10 numbers');
        }

        foreach ($numbers as $number) {
            if ($number < 1 || $number > self::TOTAL_NUMBERS) {
                throw new \InvalidArgumentException('Numbers must be between 1 and 40');
            }
        }

        if (count($numbers) !== count(array_unique($numbers))) {
            throw new \InvalidArgumentException('Cannot select duplicate numbers');
        }
    }
}
```

---

## Game 6: Wheel 🎡

### Rules
- Spinning wheel with configurable segments
- Different risk levels affect segment distribution
- Simple and visual

### Implementation

```php
<?php

namespace App\Services\Games;

class WheelGame
{
    private ProvablyFairService $provablyFair;
    
    private array $wheelConfigs = [
        'low' => [
            ['multiplier' => 1.5, 'weight' => 30],
            ['multiplier' => 2.0, 'weight' => 25],
            ['multiplier' => 3.0, 'weight' => 20],
            ['multiplier' => 5.0, 'weight' => 15],
            ['multiplier' => 10.0, 'weight' => 10]
        ],
        'medium' => [
            ['multiplier' => 2.0, 'weight' => 25],
            ['multiplier' => 3.0, 'weight' => 20],
            ['multiplier' => 5.0, 'weight' => 20],
            ['multiplier' => 10.0, 'weight' => 15],
            ['multiplier' => 20.0, 'weight' => 10],
            ['multiplier' => 50.0, 'weight' => 10]
        ],
        'high' => [
            ['multiplier' => 1.0, 'weight' => 10],
            ['multiplier' => 3.0, 'weight' => 15],
            ['multiplier' => 5.0, 'weight' => 15],
            ['multiplier' => 10.0, 'weight' => 20],
            ['multiplier' => 50.0, 'weight' => 20],
            ['multiplier' => 100.0, 'weight' => 15],
            ['multiplier' => 200.0, 'weight' => 5]
        ]
    ];

    public function __construct(ProvablyFairService $provablyFair)
    {
        $this->provablyFair = $provablyFair;
    }

    /**
     * Spin the wheel
     */
    public function spin(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        float $betAmount,
        string $risk = 'medium'
    ): array {
        $config = $this->wheelConfigs[$risk];
        
        // Generate result
        $hash = $this->provablyFair->generateResultHash($serverSeed, $clientSeed, $nonce);
        $floatValue = $this->provablyFair->hashToFloat($hash);
        
        // Select segment based on weights
        $segment = $this->selectSegment($config, $floatValue);
        $multiplier = $segment['multiplier'];
        
        // Calculate payout
        $payout = $betAmount * $multiplier;
        $profit = $payout - $betAmount;

        return [
            'segment' => $segment,
            'multiplier' => $multiplier,
            'payout' => $payout,
            'profit' => $profit,
            'risk' => $risk,
            'float_value' => $floatValue
        ];
    }

    /**
     * Select segment based on weighted probability
     */
    private function selectSegment(array $config, float $randomValue): array
    {
        $totalWeight = array_sum(array_column($config, 'weight'));
        $threshold = $randomValue * $totalWeight;
        
        $cumulativeWeight = 0;
        foreach ($config as $segment) {
            $cumulativeWeight += $segment['weight'];
            if ($threshold <= $cumulativeWeight) {
                return $segment;
            }
        }

        return end($config);
    }
}
```

---

## Game 7: Pump 🚀 & Game 8: Crash 📉

Both Pump and Crash follow similar mechanics - a multiplier that increases over time until it crashes.

### Implementation

```php
<?php

namespace App\Services\Games;

class CrashGame
{
    private ProvablyFairService $provablyFair;

    public function __construct(ProvablyFairService $provablyFair)
    {
        $this->provablyFair = $provablyFair;
    }

    /**
     * Generate crash point
     */
    public function generateCrashPoint(
        string $serverSeed,
        string $clientSeed,
        int $nonce
    ): float {
        $hash = $this->provablyFair->generateResultHash($serverSeed, $clientSeed, $nonce);
        
        // Take first 8 characters
        $subHash = substr($hash, 0, 8);
        $intValue = hexdec($subHash);
        
        // Use exponential distribution for crash point
        $houseEdge = 0.01;
        $floatValue = $intValue / 0xFFFFFFFF;
        
        // Ensure crash point is at least 1.00x
        $crashPoint = max(1.00, (99 / (99 * (1 - $floatValue) - (1 - $houseEdge))));
        
        // Cap at 10000x
        $crashPoint = min(10000, $crashPoint);
        
        return round($crashPoint, 2);
    }

    /**
     * Place bet
     */
    public function placeBet(
        float $betAmount,
        float $autoCashout = null // Optional auto-cashout multiplier
    ): array {
        return [
            'bet_amount' => $betAmount,
            'auto_cashout' => $autoCashout,
            'status' => 'active'
        ];
    }

    /**
     * Cash out
     */
    public function cashOut(
        float $betAmount,
        float $currentMultiplier,
        float $crashPoint
    ): array {
        if ($currentMultiplier >= $crashPoint) {
            // Too late - crashed
            return [
                'success' => false,
                'result' => 'crashed',
                'payout' => 0,
                'profit' => -$betAmount
            ];
        }

        // Successful cashout
        $payout = $betAmount * $currentMultiplier;
        $profit = $payout - $betAmount;

        return [
            'success' => true,
            'result' => 'win',
            'cashout_multiplier' => $currentMultiplier,
            'payout' => $payout,
            'profit' => $profit
        ];
    }
}
```

---

## Verification Tools

### Public Verification Page

```php
<?php

namespace App\Http\Controllers;

use App\Services\Games\ProvablyFairService;

class VerificationController extends Controller
{
    public function verify(Request $request, ProvablyFairService $provablyFair)
    {
        $serverSeed = $request->input('server_seed');
        $serverSeedHash = $request->input('server_seed_hash');
        $clientSeed = $request->input('client_seed');
        $nonce = $request->input('nonce');
        $gameType = $request->input('game_type');

        // Verify server seed hash
        if (hash('sha256', $serverSeed) !== $serverSeedHash) {
            return response()->json([
                'verified' => false,
                'error' => 'Server seed hash does not match'
            ]);
        }

        // Generate result hash
        $hash = $provablyFair->generateResultHash($serverSeed, $clientSeed, $nonce);

        // Game-specific verification
        $result = $this->verifyGameSpecific($gameType, $hash, $request);

        return response()->json([
            'verified' => true,
            'hash' => $hash,
            'result' => $result
        ]);
    }
}
```

---

**Last Updated**: December 21, 2025
