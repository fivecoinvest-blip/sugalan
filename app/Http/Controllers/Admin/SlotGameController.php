<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlotGameController extends Controller
{
    /**
     * List all slot games with filters and pagination
     */
    public function index(Request $request)
    {
        $query = SlotGame::with('provider:id,name');

        // Filter by provider
        if ($request->has('provider') && $request->provider) {
            $query->where('provider_id', $request->provider);
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search by game name or game_id
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('game_id', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->input('per_page', 50);
        $games = $query->orderBy('name', 'asc')->paginate($perPage);

        // Format response
        $data = $games->map(function ($game) {
            return [
                'id' => $game->id,
                'game_id' => $game->game_id,
                'name' => $game->name,
                'provider_id' => $game->provider_id,
                'provider_name' => $game->provider?->name,
                'manufacturer' => $game->manufacturer,
                'category' => $game->category,
                'thumbnail_url' => $game->thumbnail_url,
                'min_bet' => $game->min_bet,
                'max_bet' => $game->max_bet,
                'rtp' => $game->rtp,
                'is_active' => $game->is_active,
                'created_at' => $game->created_at,
                'updated_at' => $game->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $games->total(),
            'per_page' => $games->perPage(),
            'current_page' => $games->currentPage(),
            'last_page' => $games->lastPage(),
        ]);
    }

    /**
     * Get a specific game
     */
    public function show(SlotGame $game)
    {
        $game->load('provider:id,name,code');

        return response()->json([
            'success' => true,
            'data' => $game,
        ]);
    }

    /**
     * Update a game
     */
    public function update(Request $request, SlotGame $game)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:100',
            'min_bet' => 'sometimes|numeric|min:0',
            'max_bet' => 'sometimes|numeric|min:0',
            'rtp' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $game->update($request->only([
                'name',
                'category',
                'min_bet',
                'max_bet',
                'rtp',
                'is_active',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Game updated successfully',
                'data' => $game,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update game: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a game
     */
    public function destroy(SlotGame $game)
    {
        try {
            $game->delete();

            return response()->json([
                'success' => true,
                'message' => 'Game deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete game: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update games
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'game_ids' => 'required|array',
            'game_ids.*' => 'exists:slot_games,id',
            'action' => 'required|in:activate,deactivate,delete',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $gameIds = $request->game_ids;
            $action = $request->action;
            $count = 0;

            switch ($action) {
                case 'activate':
                    $count = SlotGame::whereIn('id', $gameIds)->update(['is_active' => true]);
                    break;
                case 'deactivate':
                    $count = SlotGame::whereIn('id', $gameIds)->update(['is_active' => false]);
                    break;
                case 'delete':
                    $count = SlotGame::whereIn('id', $gameIds)->delete();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "{$count} games {$action}d successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
