<?php

namespace App\Http\Controllers;

use App\Services\SlotGameService;
use App\Services\SlotProviderService;
use App\Services\SlotSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SlotGameController extends Controller
{
    public function __construct(
        private SlotGameService $gameService,
        private SlotProviderService $providerService,
        private SlotSessionService $sessionService
    ) {}

    /**
     * Get all active providers
     *
     * @return JsonResponse
     */
    public function getProviders(): JsonResponse
    {
        try {
            $providers = $this->providerService->getActiveProviders();
            
            return response()->json([
                'success' => true,
                'data' => $providers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch providers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all games or games by provider
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getGames(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'provider' => 'nullable|string|exists:slot_providers,code',
                'category' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            $providerCode = $request->input('provider');
            $category = $request->input('category');
            
            if ($providerCode) {
                $games = $this->gameService->getProviderGames($providerCode, $category);
            } else {
                $games = $this->gameService->getAllGames($category);
            }
            
            return response()->json([
                'success' => true,
                'data' => $games,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch games',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get game categories
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $providerCode = $request->input('provider');
            $categories = $this->gameService->getCategories($providerCode);
            
            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get popular games
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPopularGames(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 10);
            $games = $this->gameService->getPopularGames($limit);
            
            return response()->json([
                'success' => true,
                'data' => $games,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular games',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search games
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchGames(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'required|string|min:2',
                'provider' => 'nullable|string|exists:slot_providers,code',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            $query = $request->input('q');
            $providerCode = $request->input('provider');
            
            $games = $this->gameService->searchGames($query, $providerCode);
            
            return response()->json([
                'success' => true,
                'data' => $games,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search games',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Launch a game
     *
     * @param Request $request
     * @param int $gameId
     * @return JsonResponse
     */
    public function launchGame(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), [
                'demo_mode' => 'nullable|boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            $game = $this->gameService->getGame($gameId);
            
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game not found',
                ], 404);
            }
            
            if (!$game->is_active || !$game->provider->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game is currently unavailable',
                ], 400);
            }
            
            $demoMode = $request->input('demo_mode', false);
            
            // Create session and get launch URL
            $session = $this->sessionService->createSession($user, $game, $demoMode);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $session->uuid,
                    'game_url' => $session->game_url,
                    'expires_at' => $session->expires_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to launch game',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active session
     *
     * @return JsonResponse
     */
    public function getActiveSession(): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $this->sessionService->getUserActiveSession($user);
            
            if (!$session) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->sessionService->getSessionStats($session),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get session history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSessionHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $result = $this->sessionService->getUserSessions($user, $page, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * End active session
     *
     * @return JsonResponse
     */
    public function endSession(): JsonResponse
    {
        try {
            $user = Auth::user();
            $session = $this->sessionService->getUserActiveSession($user);
            
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active session found',
                ], 404);
            }
            
            $this->sessionService->endSession($session, $session->final_balance);
            
            return response()->json([
                'success' => true,
                'message' => 'Session ended successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to end session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync games from provider
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function syncGames(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'provider' => 'required|string|exists:slot_providers,code',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            $provider = $this->providerService->getProvider($request->input('provider'));
            
            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not found',
                ], 404);
            }
            
            $count = $this->gameService->syncGames($provider);
            
            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$count} games",
                'data' => ['count' => $count],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync games',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
