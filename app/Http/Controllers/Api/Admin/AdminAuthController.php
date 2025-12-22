<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminAuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Admin login with email/password
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');
            
            // Attempt to authenticate with admin guard
            if (!$token = auth('admin')->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            $admin = auth('admin')->user();

            // Check if admin is active
            if (!$admin->is_active) {
                auth('admin')->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Your admin account is not active',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Admin login successful',
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'username' => $admin->username,
                        'full_name' => $admin->full_name,
                        'email' => $admin->email,
                        'role' => $admin->role,
                        'permissions' => $admin->permissions,
                    ],
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth('admin')->factory()->getTTL() * 60,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current admin user
     */
    public function me()
    {
        try {
            $admin = auth('admin')->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                    'full_name' => $admin->full_name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'permissions' => $admin->permissions,
                    'is_active' => $admin->is_active,
                    'last_login_at' => $admin->last_login_at,
                    'created_at' => $admin->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Logout admin
     */
    public function logout()
    {
        try {
            auth('admin')->logout();

            return response()->json([
                'success' => true,
                'message' => 'Admin successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh admin token
     */
    public function refresh()
    {
        try {
            $token = auth('admin')->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth('admin')->factory()->getTTL() * 60,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }
}
