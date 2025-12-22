<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Get user profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['vipLevel', 'wallet']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'username' => $user->username,
                'phone_number' => $user->phone_number,
                'wallet_address' => $user->wallet_address,
                'telegram_username' => $user->telegram_username,
                'auth_method' => $user->auth_method,
                'vip_level' => $user->vipLevel,
                'wallet' => $user->wallet,
                'referral_code' => $user->referral_code,
                'total_wagered' => $user->total_wagered,
                'total_deposited' => $user->total_deposited,
                'total_withdrawn' => $user->total_withdrawn,
                'status' => $user->status,
                'phone_verified_at' => $user->phone_verified_at,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'username' => 'sometimes|string|max:50|unique:users,username,' . $user->id,
        ]);

        if ($request->has('username')) {
            // Guest users can set username once
            if ($user->auth_method === 'guest' && !$user->username_changed) {
                $user->username = $request->input('username');
                $user->username_changed = true;
            } elseif ($user->auth_method !== 'guest') {
                $user->username = $request->input('username');
            } else {
                throw ValidationException::withMessages([
                    'username' => ['Username can only be changed once for guest accounts'],
                ]);
            }
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->load(['vipLevel', 'wallet']),
        ]);
    }

    /**
     * Change password (phone auth only)
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->auth_method !== 'phone') {
            return response()->json([
                'success' => false,
                'message' => 'Password change is only available for phone authentication',
            ], 400);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get user statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'total_bets' => $user->bets()->count(),
            'total_wins' => $user->bets()->where('status', 'win')->count(),
            'total_losses' => $user->bets()->where('status', 'loss')->count(),
            'win_rate' => 0,
            'total_wagered' => $user->total_wagered,
            'total_profit' => $user->bets()->sum('profit'),
            'biggest_win' => $user->bets()->max('payout'),
            'favorite_game' => $user->bets()
                ->select('game_type')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('game_type')
                ->orderByDesc('count')
                ->first()?->game_type ?? null,
            'deposits' => [
                'total' => $user->total_deposited,
                'count' => $user->deposits()->where('status', 'approved')->count(),
            ],
            'withdrawals' => [
                'total' => $user->total_withdrawn,
                'count' => $user->withdrawals()->where('status', 'approved')->count(),
            ],
            'referrals' => [
                'total_referred' => $user->referrals()->count(),
                'total_earnings' => $user->referrals()->where('status', 'paid')->sum('reward_amount'),
            ],
            'vip_progress' => [
                'current_level' => $user->vipLevel->name,
                'total_wagered' => $user->total_wagered,
                'next_level_requirement' => $user->vipLevel->min_wager_requirement ?? null,
            ],
        ];

        // Calculate win rate
        if ($stats['total_bets'] > 0) {
            $stats['win_rate'] = round(($stats['total_wins'] / $stats['total_bets']) * 100, 2);
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
