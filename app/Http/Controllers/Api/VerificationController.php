<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProvablyFairService;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    protected $provablyFairService;

    public function __construct(ProvablyFairService $provablyFairService)
    {
        $this->provablyFairService = $provablyFairService;
    }

    /**
     * Verify a game result
     * 
     * This endpoint allows anyone to verify a game result independently
     * using the provably fair system
     */
    public function verify(Request $request)
    {
        $request->validate([
            'game_type' => 'required|string|in:dice,hilo,mines,plinko,keno,wheel,crash,pump',
            'server_seed' => 'required|string',
            'server_seed_hash' => 'required|string',
            'client_seed' => 'required|string',
            'nonce' => 'required|integer|min:0',
            'game_data' => 'nullable|array'
        ]);

        // Step 1: Verify server seed hash
        $computedHash = hash('sha256', $request->server_seed);
        if ($computedHash !== $request->server_seed_hash) {
            return response()->json([
                'verified' => false,
                'error' => 'Server seed hash does not match. Expected: ' . $computedHash
            ], 400);
        }

        // Step 2: Generate result hash using HMAC-SHA256
        $hash = $this->provablyFairService->generateResult(
            $request->server_seed,
            $request->client_seed,
            $request->nonce
        );

        // Step 3: Calculate game-specific result
        $result = $this->calculateGameResult(
            $request->game_type,
            $hash,
            $request->game_data ?? []
        );

        return response()->json([
            'verified' => true,
            'hash' => $hash,
            'result' => $result,
            'message' => 'Result verified successfully using provably fair system'
        ]);
    }

    /**
     * Calculate game-specific result from hash
     */
    protected function calculateGameResult(string $gameType, string $hash, array $gameData): array
    {
        switch ($gameType) {
            case 'dice':
                return $this->verifyDice($hash, $gameData);
            
            case 'hilo':
                return $this->verifyHiLo($hash, $gameData);
            
            case 'mines':
                return $this->verifyMines($hash, $gameData);
            
            case 'plinko':
                return $this->verifyPlinko($hash, $gameData);
            
            case 'keno':
                return $this->verifyKeno($hash, $gameData);
            
            case 'wheel':
                return $this->verifyWheel($hash, $gameData);
            
            case 'crash':
            case 'pump':
                return $this->verifyCrash($hash, $gameData);
            
            default:
                return ['error' => 'Unknown game type'];
        }
    }

    /**
     * Verify Dice result
     */
    protected function verifyDice(string $hash, array $gameData): array
    {
        $result = $this->provablyFairService->hashToDiceResult($hash);
        
        $prediction = $gameData['prediction'] ?? 'over';
        $target = $gameData['target'] ?? 50.00;
        
        $isWin = ($prediction === 'over' && $result > $target) || 
                 ($prediction === 'under' && $result < $target);
        
        $winChance = $prediction === 'over' ? (100 - $target) : $target;
        $multiplier = $winChance > 0 ? (99 / $winChance) : 0;

        return [
            'game' => 'dice',
            'result' => $result,
            'prediction' => $prediction,
            'target' => $target,
            'is_win' => $isWin,
            'win_chance' => round($winChance, 2),
            'multiplier' => round($multiplier, 4)
        ];
    }

    /**
     * Verify Hi-Lo result
     */
    protected function verifyHiLo(string $hash, array $gameData): array
    {
        $cardValue = $this->provablyFairService->hashToCard($hash);
        
        return [
            'game' => 'hilo',
            'card_rank' => $cardValue['rank'],
            'card_suit' => $cardValue['suit'],
            'card_value' => $cardValue['value'],
            'note' => 'Use this card value to determine win/loss against your prediction'
        ];
    }

    /**
     * Verify Mines result
     */
    protected function verifyMines(string $hash, array $gameData): array
    {
        $mineCount = $gameData['mine_count'] ?? 3;
        $gridSize = 25; // 5x5
        
        $minePositions = $this->provablyFairService->hashToMinesPositions($hash, $gridSize, $mineCount);
        
        return [
            'game' => 'mines',
            'mine_count' => $mineCount,
            'grid_size' => $gridSize,
            'mine_positions' => $minePositions,
            'note' => 'These positions (0-24) contain mines in a 5x5 grid'
        ];
    }

    /**
     * Verify Plinko result
     */
    protected function verifyPlinko(string $hash, array $gameData): array
    {
        $rows = $gameData['rows'] ?? 16;
        $risk = $gameData['risk'] ?? 'medium';
        
        $path = $this->provablyFairService->hashToPlinkoPath($hash, $rows);
        $finalPosition = array_sum($path);
        
        // Multipliers based on risk level
        $multipliers = $this->provablyFairService->getPlinkoMultipliers($risk, $rows);
        $multiplier = $multipliers[$finalPosition] ?? 0;

        return [
            'game' => 'plinko',
            'rows' => $rows,
            'risk' => $risk,
            'path' => $path,
            'final_position' => $finalPosition,
            'multiplier' => $multiplier
        ];
    }

    /**
     * Verify Keno result
     */
    protected function verifyKeno(string $hash, array $gameData): array
    {
        $drawnNumbers = $this->provablyFairService->hashToKenoNumbers($hash, 20, 40);
        
        $selectedNumbers = isset($gameData['selected_numbers']) 
            ? (is_string($gameData['selected_numbers']) 
                ? array_map('intval', explode(',', $gameData['selected_numbers']))
                : $gameData['selected_numbers'])
            : [];
        
        $hits = array_intersect($selectedNumbers, $drawnNumbers);
        
        return [
            'game' => 'keno',
            'drawn_numbers' => $drawnNumbers,
            'selected_numbers' => $selectedNumbers,
            'hits' => count($hits),
            'matching_numbers' => array_values($hits)
        ];
    }

    /**
     * Verify Wheel result
     */
    protected function verifyWheel(string $hash, array $gameData): array
    {
        $risk = $gameData['risk'] ?? 'medium';
        $floatValue = $this->provablyFairService->hashToFloat($hash);
        
        // Get wheel configuration for the risk level
        $config = $this->provablyFairService->getWheelConfig($risk);
        $segment = $this->provablyFairService->selectWheelSegment($config, $floatValue);

        return [
            'game' => 'wheel',
            'risk' => $risk,
            'float_value' => $floatValue,
            'segment_index' => $segment['index'] ?? 0,
            'multiplier' => $segment['multiplier'] ?? 1.0,
            'color' => $segment['color'] ?? 'unknown'
        ];
    }

    /**
     * Verify Crash/Pump result
     */
    protected function verifyCrash(string $hash, array $gameData): array
    {
        $crashMultiplier = $this->provablyFairService->hashToCrashMultiplier($hash);

        return [
            'game' => 'crash_pump',
            'crash_multiplier' => $crashMultiplier,
            'note' => 'This is the multiplier at which the round crashes'
        ];
    }

    /**
     * Get verification instructions
     * 
     * Returns step-by-step instructions for manual verification
     */
    public function instructions()
    {
        return response()->json([
            'title' => 'Provably Fair Verification Instructions',
            'overview' => 'All game results are generated using cryptographic hashing to ensure fairness',
            'steps' => [
                [
                    'step' => 1,
                    'title' => 'Verify Server Seed Hash',
                    'description' => 'Calculate SHA-256 hash of the revealed server seed',
                    'formula' => 'SHA256(server_seed) === server_seed_hash',
                    'note' => 'The server seed hash was shown to you before placing the bet'
                ],
                [
                    'step' => 2,
                    'title' => 'Generate Result Hash',
                    'description' => 'Calculate HMAC-SHA256 using server seed and client seed',
                    'formula' => 'HMAC-SHA256(client_seed:nonce, server_seed)',
                    'note' => 'This produces a deterministic result that cannot be manipulated'
                ],
                [
                    'step' => 3,
                    'title' => 'Convert Hash to Game Result',
                    'description' => 'Use game-specific conversion algorithm',
                    'examples' => [
                        'Dice' => 'parseInt(hash.substring(0, 8), 16) % 10001 / 100',
                        'Mines' => 'Generate positions using hash chunks',
                        'Plinko' => 'Generate path using hash bits',
                        'Keno' => 'Draw numbers using Fisher-Yates with hash',
                        'Crash' => 'exponential distribution using hash'
                    ]
                ],
                [
                    'step' => 4,
                    'title' => 'Compare Results',
                    'description' => 'The calculated result should exactly match the game outcome',
                    'note' => 'Any discrepancy means the result was not fair'
                ]
            ],
            'tools' => [
                'Online SHA-256 calculator' => 'https://emn178.github.io/online-tools/sha256.html',
                'Online HMAC calculator' => 'https://www.freeformatter.com/hmac-generator.html',
                'Our verification page' => url('/verify')
            ],
            'support' => 'If you have questions about verification, contact support@casino.com'
        ]);
    }
}
