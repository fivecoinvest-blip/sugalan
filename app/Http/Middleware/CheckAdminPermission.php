<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = $request->user();

        if (!$admin || !($admin instanceof \App\Models\AdminUser)) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 401);
        }

        // Super admin has all permissions
        if ($admin->role === 'super_admin') {
            return $next($request);
        }

        // Check if admin has required permission
        $permissions = $admin->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            return response()->json([
                'message' => 'Forbidden. Insufficient permissions.',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
