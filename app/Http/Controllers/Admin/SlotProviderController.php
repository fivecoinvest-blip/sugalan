<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotProvider;
use App\Services\SlotProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlotProviderController extends Controller
{
    public function __construct(
        private SlotProviderService $providerService
    ) {}
    /**
     * Display a listing of slot providers
     */
    public function index()
    {
        $providers = SlotProvider::orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $providers,
        ]);
    }

    /**
     * Store a newly created provider
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:slot_providers,code',
            'name' => 'required|string|max:255',
            'api_url' => 'required|url|max:500',
            'agency_uid' => 'required|string|max:255',
            'aes_key' => 'required|string|min:32|max:255',
            'player_prefix' => 'required|string|max:50',
            'session_timeout_minutes' => 'required|integer|min:5|max:1440',
            'currency' => 'required|string|in:PHP,USD,EUR',
            'supports_seamless_wallet' => 'boolean',
            'supports_transfer_wallet' => 'boolean',
            'supports_demo_mode' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider = SlotProvider::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Provider created successfully',
            'data' => $provider,
        ], 201);
    }

    /**
     * Display the specified provider
     */
    public function show(SlotProvider $provider)
    {
        return response()->json([
            'success' => true,
            'data' => $provider,
        ]);
    }

    /**
     * Update the specified provider
     */
    public function update(Request $request, SlotProvider $provider)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'api_url' => 'sometimes|required|url|max:500',
            'agency_uid' => 'sometimes|required|string|max:255',
            'aes_key' => 'sometimes|required|string|min:32|max:255',
            'player_prefix' => 'sometimes|required|string|max:50',
            'session_timeout_minutes' => 'sometimes|required|integer|min:5|max:1440',
            'currency' => 'sometimes|required|string|in:PHP,USD,EUR',
            'supports_seamless_wallet' => 'boolean',
            'supports_transfer_wallet' => 'boolean',
            'supports_demo_mode' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Provider updated successfully',
            'data' => $provider,
        ]);
    }

    /**
     * Remove the specified provider
     */
    public function destroy(SlotProvider $provider)
    {
        $provider->delete();

        return response()->json([
            'success' => true,
            'message' => 'Provider deleted successfully',
        ]);
    }

    /**
     * Sync games from provider API
     */
    public function syncGames(SlotProvider $provider)
    {
        try {
            // This would call the provider's API to sync games
            // For now, we'll return a success message
            // In production, you would call the actual provider API here
            
            return response()->json([
                'success' => true,
                'message' => 'Game sync initiated. This process may take a few minutes.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync games: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction records from provider
     * 
     * @param Request $request
     * @param SlotProvider $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Request $request, SlotProvider $provider)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $fromDate = strtotime($request->input('from_date')) * 1000; // Convert to milliseconds
            $toDate = strtotime($request->input('to_date') . ' 23:59:59') * 1000;
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 100);

            $result = $this->providerService->getTransactionList(
                $provider,
                $fromDate,
                $toDate,
                $page,
                $perPage
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'total_count' => $result['total_count'] ?? 0,
                    'current_page' => $result['current_page'] ?? $page,
                    'page_size' => $result['page_size'] ?? $perPage,
                    'records' => $result['records'] ?? [],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions: ' . $e->getMessage(),
            ], 500);
        }
    }
}
