<?php

namespace App\Services;

use App\Models\Seed;
use Illuminate\Support\Str;

class ProvablyFairService
{
    /**
     * Generate game result hash using HMAC-SHA256
     */
    public function generateResult(string $serverSeed, string $clientSeed, int $nonce): string
    {
        $message = $clientSeed . ':' . $nonce;
        return hash_hmac('sha256', $message, $serverSeed);
    }

    /**
     * Get active seed for user or create new one
     */
    public function getActiveSeed(int $userId): Seed
    {
        $seed = Seed::where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$seed) {
            $seed = $this->createNewSeed($userId);
        }

        return $seed;
    }

    /**
     * Create new seed pair
     */
    public function createNewSeed(int $userId): Seed
    {
        $serverSeed = Str::random(64);
        
        return Seed::create([
            'user_id' => $userId,
            'server_seed' => $serverSeed,
            'server_seed_hash' => hash('sha256', $serverSeed),
            'client_seed' => Str::random(16),
            'nonce' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Rotate seed (reveal current and create new)
     */
    public function rotateSeed(int $userId, ?string $newClientSeed = null): array
    {
        $currentSeed = $this->getActiveSeed($userId);
        $currentSeed->reveal();

        $newSeed = $this->createNewSeed($userId);
        
        if ($newClientSeed) {
            $newSeed->update(['client_seed' => $newClientSeed]);
        }

        return [
            'revealed_seed' => [
                'server_seed' => $currentSeed->server_seed,
                'client_seed' => $currentSeed->client_seed,
                'nonce' => $currentSeed->nonce,
            ],
            'new_seed' => [
                'server_seed_hash' => $newSeed->server_seed_hash,
                'client_seed' => $newSeed->client_seed,
                'nonce' => $newSeed->nonce,
            ],
        ];
    }

    /**
     * Verify game result
     */
    public function verifyResult(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        string $expectedHash
    ): bool {
        $calculatedHash = $this->generateResult($serverSeed, $clientSeed, $nonce);
        return $calculatedHash === $expectedHash;
    }

    /**
     * Convert hash to number (0-1 range)
     */
    public function hashToNumber(string $hash): float
    {
        // Take first 8 characters and convert to decimal
        $hex = substr($hash, 0, 8);
        $decimal = hexdec($hex);
        return $decimal / 0xFFFFFFFF;
    }

    /**
     * Convert hash to roll (0-99.99 range for dice)
     */
    public function hashToRoll(string $hash): float
    {
        return round($this->hashToNumber($hash) * 100, 2);
    }

    /**
     * Convert hash to mines positions
     */
    public function hashToMinesPositions(string $hash, int $gridSize, int $mineCount): array
    {
        $positions = [];
        $totalCells = $gridSize * $gridSize;
        $seed = hexdec(substr($hash, 0, 8));
        
        for ($i = 0; $i < $mineCount; $i++) {
            do {
                $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                $position = $seed % $totalCells;
            } while (in_array($position, $positions));
            
            $positions[] = $position;
        }
        
        return $positions;
    }

    /**
     * Convert hash to plinko slot (0-16 for standard plinko)
     */
    public function hashToPlinkoSlot(string $hash, int $rows = 16): int
    {
        $number = $this->hashToNumber($hash);
        
        // Simulate ball drop with binomial distribution
        $slot = 0;
        for ($i = 0; $i < $rows; $i++) {
            $bit = ($number * pow(2, $i + 1)) % 1;
            $slot += $bit > 0.5 ? 1 : 0;
        }
        
        return $slot;
    }

    /**
     * Convert hash to keno numbers
     */
    public function hashToKenoNumbers(string $hash, int $count = 20, int $max = 40): array
    {
        $numbers = [];
        $seed = hexdec(substr($hash, 0, 8));
        
        for ($i = 0; $i < $count; $i++) {
            do {
                $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                $number = ($seed % $max) + 1;
            } while (in_array($number, $numbers));
            
            $numbers[] = $number;
        }
        
        sort($numbers);
        return $numbers;
    }

    /**
     * Convert hash to wheel segment (0-36 for roulette-style)
     */
    public function hashToWheelSegment(string $hash, int $segments): int
    {
        $number = $this->hashToNumber($hash);
        return (int) floor($number * $segments);
    }

    /**
     * Convert hash to crash multiplier
     */
    public function hashToCrashMultiplier(string $hash): float
    {
        $number = $this->hashToNumber($hash);
        
        // Use exponential distribution for realistic crash multipliers
        // Most crashes between 1x-2x, rare high multipliers
        $e = 2.71828;
        $houseEdge = 0.01;
        
        if ($number === 0) {
            $number = 0.0000001;
        }
        
        $multiplier = (1 - $houseEdge) / (1 - $number);
        
        // Cap at reasonable maximum
        return min(round($multiplier, 2), 1000000);
    }

    /**
     * Convert hash to HiLo card (1-13)
     */
    public function hashToCard(string $hash): array
    {
        $number = $this->hashToNumber($hash);
        $cardValue = (int) floor($number * 13) + 1;
        
        $cardNames = [
            1 => 'A', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7',
            8 => '8', 9 => '9', 10 => '10', 11 => 'J', 12 => 'Q', 13 => 'K'
        ];
        
        // Determine suit based on next part of hash
        $suitHash = substr($hash, 8, 2);
        $suitNumber = hexdec($suitHash) % 4;
        $suits = ['♠', '♥', '♦', '♣'];
        
        return [
            'value' => $cardValue,
            'rank' => $cardNames[$cardValue],
            'suit' => $suits[$suitNumber],
        ];
    }

    /**
     * Convert hash to dice result (0-100)
     */
    public function hashToDiceResult(string $hash): float
    {
        $hex = substr($hash, 0, 8);
        $decimal = hexdec($hex);
        return round(($decimal % 10001) / 100, 2);
    }

    /**
     * Convert hash to float (0-1)
     */
    public function hashToFloat(string $hash): float
    {
        return $this->hashToNumber($hash);
    }

    /**
     * Convert hash to plinko path
     */
    public function hashToPlinkoPath(string $hash, int $rows): array
    {
        $path = [];
        $number = $this->hashToNumber($hash);
        
        for ($i = 0; $i < $rows; $i++) {
            $bit = ($number * pow(2, $i + 1)) % 1;
            $path[] = $bit > 0.5 ? 1 : 0;
        }
        
        return $path;
    }

    /**
     * Get plinko multipliers based on risk and rows
     */
    public function getPlinkoMultipliers(string $risk, int $rows): array
    {
        $multipliers = [
            'low' => [
                8 => [5.6, 2.1, 1.1, 1.0, 0.5, 1.0, 1.1, 2.1, 5.6],
                12 => [10, 3, 1.6, 1.4, 1.1, 1.0, 0.5, 1.0, 1.1, 1.4, 1.6, 3, 10],
                16 => [16, 9, 2, 1.4, 1.4, 1.2, 1.1, 1.0, 0.5, 1.0, 1.1, 1.2, 1.4, 1.4, 2, 9, 16],
            ],
            'medium' => [
                8 => [13, 3, 1.3, 0.7, 0.4, 0.7, 1.3, 3, 13],
                12 => [33, 11, 4, 2, 1.1, 0.6, 0.3, 0.6, 1.1, 2, 4, 11, 33],
                16 => [110, 41, 10, 5, 3, 1.5, 1.0, 0.5, 0.3, 0.5, 1.0, 1.5, 3, 5, 10, 41, 110],
            ],
            'high' => [
                8 => [29, 4, 1.5, 0.3, 0.2, 0.3, 1.5, 4, 29],
                12 => [170, 24, 8.1, 2, 0.7, 0.2, 0.2, 0.2, 0.7, 2, 8.1, 24, 170],
                16 => [1000, 130, 26, 9, 4, 2, 0.2, 0.2, 0.2, 0.2, 0.2, 2, 4, 9, 26, 130, 1000],
            ],
        ];

        return $multipliers[$risk][$rows] ?? [];
    }

    /**
     * Get wheel configuration for risk level
     */
    public function getWheelConfig(string $risk): array
    {
        $configs = [
            'low' => [
                ['multiplier' => 1.2, 'weight' => 100, 'color' => 'blue'],
                ['multiplier' => 1.5, 'weight' => 80, 'color' => 'green'],
                ['multiplier' => 2.0, 'weight' => 50, 'color' => 'yellow'],
                ['multiplier' => 3.0, 'weight' => 20, 'color' => 'orange'],
                ['multiplier' => 5.0, 'weight' => 5, 'color' => 'red'],
            ],
            'medium' => [
                ['multiplier' => 1.5, 'weight' => 100, 'color' => 'blue'],
                ['multiplier' => 2.0, 'weight' => 70, 'color' => 'green'],
                ['multiplier' => 3.0, 'weight' => 40, 'color' => 'yellow'],
                ['multiplier' => 5.0, 'weight' => 15, 'color' => 'orange'],
                ['multiplier' => 10.0, 'weight' => 5, 'color' => 'red'],
            ],
            'high' => [
                ['multiplier' => 2.0, 'weight' => 100, 'color' => 'blue'],
                ['multiplier' => 3.0, 'weight' => 60, 'color' => 'green'],
                ['multiplier' => 5.0, 'weight' => 30, 'color' => 'yellow'],
                ['multiplier' => 10.0, 'weight' => 10, 'color' => 'orange'],
                ['multiplier' => 50.0, 'weight' => 2, 'color' => 'red'],
            ],
        ];

        return $configs[$risk] ?? $configs['medium'];
    }

    /**
     * Select wheel segment based on float value
     */
    public function selectWheelSegment(array $config, float $floatValue): array
    {
        $totalWeight = array_sum(array_column($config, 'weight'));
        $threshold = $floatValue * $totalWeight;
        $cumulativeWeight = 0;

        foreach ($config as $index => $segment) {
            $cumulativeWeight += $segment['weight'];
            if ($threshold <= $cumulativeWeight) {
                $segment['index'] = $index;
                return $segment;
            }
        }

        $lastSegment = end($config);
        $lastSegment['index'] = count($config) - 1;
        return $lastSegment;
    }
}

