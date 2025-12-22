<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Games\DiceGameService;
use App\Services\Games\HiLoGameService;
use App\Services\Games\MinesGameService;
use App\Services\Games\PlinkoGameService;
use App\Services\Games\KenoGameService;
use App\Services\Games\WheelGameService;
use App\Services\Games\CrashGameService;
use App\Services\Games\PumpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class GameController extends Controller
{
    public function __construct(
        private DiceGameService $diceGame,
        private HiLoGameService $hiloGame,
        private MinesGameService $minesGame,
        private PlinkoGameService $plinkoGame,
        private KenoGameService $kenoGame,
        private WheelGameService $wheelGame,
        private CrashGameService $crashGame,
        private PumpService $pumpGame
    ) {}

    // ===== DICE =====
    public function playDice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:1',
            'prediction' => 'required|in:over,under',
            'target' => 'required|numeric|min:1|max:98.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->diceGame->play(
                $user,
                $request->bet_amount,
                $request->prediction,
                $request->target
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ===== HI-LO =====
    public function startHilo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->hiloGame->start($user, $request->bet_amount);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function predictHilo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_id' => 'required|integer',
            'prediction' => 'required|in:high,low,higher,lower',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Normalize prediction (higher -> high, lower -> low)
            $prediction = $request->prediction;
            if ($prediction === 'higher') $prediction = 'high';
            if ($prediction === 'lower') $prediction = 'low';
            
            $result = $this->hiloGame->predict($user, $request->bet_id, $prediction);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cashoutHilo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->hiloGame->cashout($user, $request->bet_id);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ===== MINES =====
    public function startMines(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:1',
            'mine_count' => 'nullable|integer|min:1|max:24',
            'mines_count' => 'nullable|integer|min:1|max:24',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $mineCount = $request->mines_count ?? $request->mine_count ?? 5;
            $result = $this->minesGame->start($user, $request->bet_amount, $mineCount);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function revealMines(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_id' => 'required|integer',
            'position' => 'nullable|integer|min:0|max:24',
            'tile_index' => 'nullable|integer|min:0|max:24',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $position = $request->tile_index ?? $request->position;
            $result = $this->minesGame->reveal($user, $request->bet_id, $position);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cashoutMines(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->minesGame->cashout($user, $request->bet_id);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ===== PLINKO =====
    public function playPlinko(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:1',
            'risk_level' => 'nullable|in:low,medium,high',
            'rows' => 'nullable|in:8,12,16',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Validate risk_level if provided
        if ($request->has('risk_level') && !in_array($request->risk_level, ['low', 'medium', 'high'])) {
            return response()->json(['success' => false, 'message' => 'Invalid risk level'], 400);
        }

        // Validate rows if provided
        if ($request->has('rows') && !in_array($request->rows, [8, 12, 16])) {
            return response()->json(['success' => false, 'message' => 'Invalid row count'], 400);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->plinkoGame->play(
                $user,
                $request->bet_amount,
                $request->risk_level ?? $request->risk ?? 'low',
                $request->rows ?? 16
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ===== KENO =====
    public function playKeno(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:1',
            'selected_numbers' => 'required|array|min:1|max:10',
            'selected_numbers.*' => 'integer|min:1|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->kenoGame->play(
                $user,
                $request->bet_amount,
                $request->selected_numbers
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ===== WHEEL =====
    public function spinWheel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:1',
            'risk' => 'nullable|in:low,medium,high',
            'risk_level' => 'nullable|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $riskLevel = $request->risk_level ?? $request->risk ?? 'low';
            
            $result = $this->wheelGame->spin(
                $user,
                $request->bet_amount,
                $riskLevel
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getWheelConfig(Request $request): JsonResponse
    {
        try {
            $risk = $request->query('risk', 'low');
            $config = $this->wheelGame->getWheelConfig($risk);

            return response()->json(['success' => true, 'data' => $config]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ===== CRASH =====
    public function placeCrashBet(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_amount' => 'required|numeric|min:1',
            'auto_cashout' => 'nullable|numeric|min:1.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->crashGame->placeBet(
                $user,
                $request->bet_amount,
                $request->auto_cashout
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cashoutCrash(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bet_id' => 'required|integer',
            'current_multiplier' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->crashGame->cashout(
                $user,
                $request->bet_id,
                $request->current_multiplier
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCurrentCrashRound(): JsonResponse
    {
        try {
            $round = $this->crashGame->getCurrentRound();

            return response()->json(['success' => true, 'data' => $round]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==================== Pump Game Methods ====================

    public function placePumpBet(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validator = Validator::make($request->all(), [
                'bet_amount' => 'required|numeric|min:1',
                'target_multiplier' => 'nullable|numeric|min:1.01|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $bet = $this->pumpGame->placeBet(
                $user,
                $request->bet_amount,
                $request->target_multiplier
            );

            return response()->json(['success' => true, 'data' => $bet]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cashoutPump(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validator = Validator::make($request->all(), [
                'round_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $result = $this->pumpGame->cashOut($user, $request->round_id);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCurrentPumpRound(): JsonResponse
    {
        try {
            $round = $this->pumpGame->getCurrentRound();

            return response()->json(['success' => true, 'data' => $round]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
