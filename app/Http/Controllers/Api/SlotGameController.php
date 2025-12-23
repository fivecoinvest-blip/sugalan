<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameProvider;
use App\Models\SlotGame;
use App\Models\SlotBet;
use App\Services\SoftAPIService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlotGameController extends Controller
{
    public function __construct(
        private SoftAPIService $softAPIService,
        private WalletService $walletService
    ) {}

    /**
     * Get all active providers
     */
    public function getProviders(): JsonResponse
    {
        $providers = GameProvider::active()
            ->ordered()
            ->withCount('activeGames')
            ->get();

        return response()->json([
            'providers' => $providers,
        ]);
    }

    /**
     * Get games by provider
     */
    public function getGamesByProvider(Request $request, $providerId): JsonResponse
    {
        $request->validate([
            'category' => 'nullable|string',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $provider = GameProvider::findOrFail($providerId);

        $query = SlotGame::where('provider_id', $providerId)
            ->active()
            ->with('provider');

        if ($request->filled('category')) {
            $query->category($request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        $games = $query->ordered()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'provider' => $provider,
            'games' => $games->items(),
            'pagination' => [
                'total' => $games->total(),
                'per_page' => $games->perPage(),
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
            ],
        ]);
    }

    /**
     * Get all slot games with filters
     */
    public function getAllGames(Request $request): JsonResponse
    {
        $request->validate([
            'provider_id' => 'nullable|exists:game_providers,id',
            'category' => 'nullable|string',
            'featured' => 'nullable|boolean',
            'new' => 'nullable|boolean',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SlotGame::active()->with('provider');

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        if ($request->filled('category')) {
            $query->category($request->category);
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        if ($request->boolean('new')) {
            $query->new();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        $games = $query->ordered()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'games' => $games->items(),
            'pagination' => [
                'total' => $games->total(),
                'per_page' => $games->perPage(),
                'current_page' => $games->currentPage(),
                'last_page' => $games->lastPage(),
            ],
        ]);
    }

    /**
     * Get game details
     */
    public function getGameDetails($gameId): JsonResponse
    {
        $game = SlotGame::with('provider')
            ->findOrFail($gameId);

        if (!$game->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Game is not available',
            ], 404);
        }

        return response()->json([
            'game' => $game,
        ]);
    }

    /**
     * Launch a slot game
     */
    public function launchGame(Request $request, $gameId): JsonResponse
    {
        $request->validate([
            'lang' => 'nullable|string|size:2',
            'currency' => 'nullable|string|size:3',
        ]);

        $user = Auth::user();
        $game = SlotGame::with('provider')->findOrFail($gameId);

        if (!$game->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Game is not available',
            ], 400);
        }

        // Check user balance
        $wallet = $this->walletService->getWallet($user->id);
        if ($wallet->real_balance <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance. Please deposit to play.',
            ], 400);
        }

        try {
            // Launch game via SoftAPI
            $response = $this->softAPIService->launchGame(
                $game->game_id,
                (string) $user->id,
                [
                    'lang' => $request->input('lang', 'en'),
                    'currency' => $request->input('currency', 'PHP'),
                    'return_url' => config('app.url') . '/slots',
                ]
            );

            if (!isset($response['success']) || !$response['success']) {
                Log::error('Game launch failed', [
                    'game_id' => $gameId,
                    'user_id' => $user->id,
                    'response' => $response,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to launch game. Please try again.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'game_url' => $response['game_url'] ?? $response['url'] ?? null,
                'game' => $game,
            ]);

        } catch (\Exception $e) {
            Log::error('Game launch exception', [
                'game_id' => $gameId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while launching the game.',
            ], 500);
        }
    }

    /**
     * Get user's slot bet history
     */
    public function getBetHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'game_id' => 'nullable|exists:slot_games,id',
            'status' => 'nullable|in:pending,completed,cancelled,refunded',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SlotBet::with(['slotGame', 'slotGame.provider'])
            ->forUser($user->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('game_id')) {
            $query->where('slot_game_id', $request->game_id);
        }

        if ($request->filled('status')) {
            $query->status($request->status);
        }

        $bets = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'bets' => $bets->items(),
            'pagination' => [
                'total' => $bets->total(),
                'per_page' => $bets->perPage(),
                'current_page' => $bets->currentPage(),
                'last_page' => $bets->lastPage(),
            ],
        ]);
    }

    /**
     * Get user's slot statistics
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();

        $stats = SlotBet::forUser($user->id)
            ->completed()
            ->selectRaw('
                COUNT(*) as total_bets,
                SUM(bet_amount) as total_wagered,
                SUM(win_amount) as total_won,
                SUM(payout) as net_profit
            ')
            ->first();

        return response()->json([
            'stats' => [
                'total_bets' => (int) $stats->total_bets,
                'total_wagered' => (float) $stats->total_wagered,
                'total_won' => (float) $stats->total_won,
                'net_profit' => (float) $stats->net_profit,
            ],
        ]);
    }
}
