<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameProvider;
use App\Models\SlotGame;
use App\Models\SlotBet;
use App\Services\SoftAPIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SlotGameManagementController extends Controller
{
    public function __construct(
        private SoftAPIService $softAPIService
    ) {}

    /**
     * Get all providers (admin)
     */
    public function getProviders(): JsonResponse
    {
        $providers = GameProvider::withCount('slotGames')
            ->ordered()
            ->get();

        return response()->json([
            'providers' => $providers,
        ]);
    }

    /**
     * Create or update provider
     */
    public function saveProvider(Request $request, $id = null): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'brand_id' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($id) {
            $provider = GameProvider::findOrFail($id);
            $provider->update($request->all());
            $message = 'Provider updated successfully';
        } else {
            $provider = GameProvider::create($request->all());
            $message = 'Provider created successfully';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'provider' => $provider,
        ]);
    }

    /**
     * Delete provider
     */
    public function deleteProvider($id): JsonResponse
    {
        $provider = GameProvider::findOrFail($id);
        $provider->delete();

        return response()->json([
            'success' => true,
            'message' => 'Provider deleted successfully',
        ]);
    }

    /**
     * Get all slot games (admin)
     */
    public function getGames(Request $request): JsonResponse
    {
        $query = SlotGame::with('provider');

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('game_code', 'like', "%{$search}%");
            });
        }

        $games = $query->orderBy('created_at', 'desc')
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
     * Save slot game (create or update)
     */
    public function saveGame(Request $request, $id = null): JsonResponse
    {
        $request->validate([
            'provider_id' => 'required|exists:game_providers,id',
            'game_code' => 'required|string|max:255',
            'game_id' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'thumbnail_url' => 'nullable|url',
            'banner_url' => 'nullable|url',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'rtp' => 'nullable|numeric|min:0|max:100',
            'volatility' => 'nullable|integer|min:1|max:5',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($id) {
            $game = SlotGame::findOrFail($id);
            $game->update($request->all());
            $message = 'Game updated successfully';
        } else {
            $game = SlotGame::create($request->all());
            $message = 'Game created successfully';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'game' => $game->load('provider'),
        ]);
    }

    /**
     * Toggle game active status
     */
    public function toggleGameStatus($id): JsonResponse
    {
        $game = SlotGame::findOrFail($id);
        $game->update(['is_active' => !$game->is_active]);

        return response()->json([
            'success' => true,
            'message' => $game->is_active ? 'Game activated' : 'Game deactivated',
            'game' => $game,
        ]);
    }

    /**
     * Delete game
     */
    public function deleteGame($id): JsonResponse
    {
        $game = SlotGame::findOrFail($id);
        $game->delete();

        return response()->json([
            'success' => true,
            'message' => 'Game deleted successfully',
        ]);
    }

    /**
     * Sync games from provider API
     */
    public function syncGames(Request $request, $providerId): JsonResponse
    {
        $request->validate([
            'brand_id' => 'required|string',
        ]);

        $provider = GameProvider::findOrFail($providerId);

        try {
            $response = $this->softAPIService->getGamesByProvider($request->brand_id);

            if (!isset($response['success']) || !$response['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch games from provider',
                ], 500);
            }

            $games = $response['games'] ?? [];
            $syncedCount = 0;

            foreach ($games as $gameData) {
                SlotGame::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'game_code' => $gameData['code'] ?? $gameData['id'],
                    ],
                    [
                        'game_id' => $gameData['id'],
                        'name' => $gameData['name'],
                        'name_en' => $gameData['name_en'] ?? $gameData['name'],
                        'thumbnail_url' => $gameData['thumbnail'] ?? null,
                        'banner_url' => $gameData['banner'] ?? null,
                        'category' => $gameData['category'] ?? 'slots',
                        'rtp' => $gameData['rtp'] ?? null,
                        'is_active' => true,
                        'metadata' => $gameData,
                    ]
                );
                $syncedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$syncedCount} games successfully",
                'synced_count' => $syncedCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing games: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get slot statistics (admin)
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $stats = DB::table('slot_bets')
            ->selectRaw('
                COUNT(*) as total_bets,
                SUM(bet_amount) as total_wagered,
                SUM(win_amount) as total_won,
                SUM(payout) as house_profit,
                COUNT(DISTINCT user_id) as unique_players
            ')
            ->first();

        $topGames = SlotBet::with('slotGame')
            ->selectRaw('
                slot_game_id,
                COUNT(*) as play_count,
                SUM(bet_amount) as total_wagered,
                SUM(win_amount) as total_won
            ')
            ->groupBy('slot_game_id')
            ->orderBy('play_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'statistics' => [
                'total_bets' => (int) $stats->total_bets,
                'total_wagered' => (float) $stats->total_wagered,
                'total_won' => (float) $stats->total_won,
                'house_profit' => (float) $stats->house_profit,
                'unique_players' => (int) $stats->unique_players,
            ],
            'top_games' => $topGames,
        ]);
    }

    /**
     * Get bet history (admin)
     */
    public function getBetHistory(Request $request): JsonResponse
    {
        $query = SlotBet::with(['user', 'slotGame', 'slotGame.provider'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('game_id')) {
            $query->where('slot_game_id', $request->game_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bets = $query->paginate($request->input('per_page', 50));

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
}
