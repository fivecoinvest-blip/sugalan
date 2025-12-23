<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required_without:email|string',
            'email' => 'required_without:username|string',
            'password' => 'required|string',
        ]);

        // Support login with either username or email
        $admin = AdminUser::where(function ($query) use ($request) {
            if ($request->filled('username')) {
                $query->where('username', $request->username);
            } else {
                $query->where('email', $request->email);
            }
        })
            ->where('is_active', true)
            ->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // IP whitelist check
        $ipWhitelist = $admin->ip_whitelist ?? [];
        if (!empty($ipWhitelist) && !in_array($request->ip(), $ipWhitelist)) {
            return response()->json([
                'message' => 'Access denied from this IP address'
            ], 403);
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($admin);

        // Update last login
        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'full_name' => $admin->full_name,
                'email' => $admin->email,
                'role' => $admin->role,
                'permissions' => $admin->permissions,
            ]
        ]);
    }

    /**
     * Get authenticated admin profile
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'admin' => [
                'id' => $request->user()->id,
                'username' => $request->user()->username,
                'full_name' => $request->user()->full_name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'permissions' => $request->user()->permissions,
                'last_login_at' => $request->user()->last_login_at,
                'last_login_ip' => $request->user()->last_login_ip,
            ]
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Refresh admin token
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            
            return response()->json([
                'token' => $token
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Token refresh failed'
            ], 401);
        }
    }
}
