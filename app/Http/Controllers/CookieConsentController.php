<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;

class CookieConsentController extends Controller
{
    /**
     * Get current cookie consent preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $consent = $request->cookie('cookie_consent');
        
        if (!$consent) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'hasConsent' => false,
                    'preferences' => null,
                ],
            ]);
        }

        // Decode consent preferences
        $preferences = json_decode($consent, true);

        return response()->json([
            'status' => 'success',
            'data' => [
                'hasConsent' => true,
                'preferences' => $preferences,
            ],
        ]);
    }

    /**
     * Save cookie consent preferences
     */
    public function savePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'essential' => 'required|boolean',
            'functional' => 'required|boolean',
            'analytics' => 'required|boolean',
            'marketing' => 'required|boolean',
        ]);

        // Essential cookies are always required
        $validated['essential'] = true;

        // Create consent cookie (1 year expiry)
        $consent = json_encode([
            'essential' => true,
            'functional' => $validated['functional'],
            'analytics' => $validated['analytics'],
            'marketing' => $validated['marketing'],
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0',
        ]);

        $cookie = Cookie::make(
            'cookie_consent',
            $consent,
            60 * 24 * 365, // 1 year in minutes
            '/',
            null,
            true, // secure (HTTPS only)
            true, // httpOnly
            false,
            'strict' // sameSite
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Cookie preferences saved',
            'data' => [
                'preferences' => json_decode($consent, true),
            ],
        ])->cookie($cookie);
    }

    /**
     * Accept all cookies
     */
    public function acceptAll(Request $request): JsonResponse
    {
        $consent = json_encode([
            'essential' => true,
            'functional' => true,
            'analytics' => true,
            'marketing' => true,
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0',
        ]);

        $cookie = Cookie::make(
            'cookie_consent',
            $consent,
            60 * 24 * 365, // 1 year
            '/',
            null,
            true,
            true,
            false,
            'strict'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'All cookies accepted',
            'data' => [
                'preferences' => json_decode($consent, true),
            ],
        ])->cookie($cookie);
    }

    /**
     * Reject non-essential cookies
     */
    public function rejectAll(Request $request): JsonResponse
    {
        $consent = json_encode([
            'essential' => true,
            'functional' => false,
            'analytics' => false,
            'marketing' => false,
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0',
        ]);

        $cookie = Cookie::make(
            'cookie_consent',
            $consent,
            60 * 24 * 365, // 1 year
            '/',
            null,
            true,
            true,
            false,
            'strict'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Non-essential cookies rejected',
            'data' => [
                'preferences' => json_decode($consent, true),
            ],
        ])->cookie($cookie);
    }

    /**
     * Clear cookie consent (for testing or user request)
     */
    public function clearConsent(Request $request): JsonResponse
    {
        $cookie = Cookie::forget('cookie_consent');

        return response()->json([
            'status' => 'success',
            'message' => 'Cookie consent cleared',
        ])->cookie($cookie);
    }
}
