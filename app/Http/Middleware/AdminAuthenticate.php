<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Authentication required.'
            ], 401);
        }

        // AdminUser model from admins table OR User model with admin role
        $isAdmin = ($user instanceof \App\Models\AdminUser) || 
                   (isset($user->role) && $user->role === 'admin');
        
        if (!$isAdmin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        return $next($request);
    }
}
