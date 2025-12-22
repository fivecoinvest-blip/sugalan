<?php

namespace App\Http\Controllers;

use App\Services\ResponsibleGamingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ResponsibleGamingController extends Controller
{
    protected ResponsibleGamingService $service;

    public function __construct(ResponsibleGamingService $service)
    {
        $this->service = $service;
    }

    /**
     * Get responsible gaming settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $this->service->getSettings($user);

        return response()->json([
            'status' => 'success',
            'data' => $settings,
        ]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $statistics = $this->service->getStatistics($user);

        return response()->json([
            'status' => 'success',
            'data' => $statistics,
        ]);
    }

    /**
     * Set deposit limits
     */
    public function setDepositLimits(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'daily' => 'nullable|numeric|min:0',
            'weekly' => 'nullable|numeric|min:0',
            'monthly' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $limits = $validator->validated();

        // Ensure weekly >= daily and monthly >= weekly
        if (isset($limits['daily']) && isset($limits['weekly']) && $limits['weekly'] < $limits['daily']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Weekly limit must be greater than or equal to daily limit.',
            ], 422);
        }

        if (isset($limits['weekly']) && isset($limits['monthly']) && $limits['monthly'] < $limits['weekly']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Monthly limit must be greater than or equal to weekly limit.',
            ], 422);
        }

        $settings = $this->service->setDepositLimits($user, $limits);

        return response()->json([
            'status' => 'success',
            'message' => 'Deposit limits updated successfully',
            'data' => $settings,
        ]);
    }

    /**
     * Set wager limits
     */
    public function setWagerLimits(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'daily' => 'nullable|numeric|min:0',
            'weekly' => 'nullable|numeric|min:0',
            'monthly' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $limits = $validator->validated();

        $settings = $this->service->setWagerLimits($user, $limits);

        return response()->json([
            'status' => 'success',
            'message' => 'Wager limits updated successfully',
            'data' => $settings,
        ]);
    }

    /**
     * Set loss limits
     */
    public function setLossLimits(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'daily' => 'nullable|numeric|min:0',
            'weekly' => 'nullable|numeric|min:0',
            'monthly' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $limits = $validator->validated();

        $settings = $this->service->setLossLimits($user, $limits);

        return response()->json([
            'status' => 'success',
            'message' => 'Loss limits updated successfully',
            'data' => $settings,
        ]);
    }

    /**
     * Set session limits
     */
    public function setSessionLimits(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'duration_minutes' => 'nullable|integer|min:15|max:1440',
            'reality_check_interval' => 'nullable|integer|min:15|max:240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $durationMinutes = $request->input('duration_minutes', 0);
        $realityCheckInterval = $request->input('reality_check_interval', 60);

        $settings = $this->service->setSessionLimits($user, $durationMinutes, $realityCheckInterval);

        return response()->json([
            'status' => 'success',
            'message' => 'Session limits updated successfully',
            'data' => $settings,
        ]);
    }

    /**
     * Enable self-exclusion
     */
    public function enableSelfExclusion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:temporary,permanent',
            'duration_days' => 'required_if:type,temporary|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Verify password
        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid password',
            ], 401);
        }

        $type = $request->type;
        $endDate = null;

        if ($type === 'temporary') {
            $endDate = now()->addDays($request->duration_days);
        }

        $settings = $this->service->enableSelfExclusion($user, $type, $endDate, $request->reason);

        return response()->json([
            'status' => 'success',
            'message' => 'Self-exclusion enabled successfully',
            'data' => $settings,
            'warning' => 'Your account has been locked. You will not be able to play until the exclusion period ends.',
        ]);
    }

    /**
     * Request self-exclusion removal
     */
    public function requestSelfExclusionRemoval(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        try {
            $this->service->requestSelfExclusionRemoval($user, $request->reason);

            return response()->json([
                'status' => 'success',
                'message' => 'Self-exclusion removal request submitted. Our team will review your request.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Enable cool-off period
     */
    public function enableCoolOff(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'required|integer|in:24,48,72,168', // 1 day, 2 days, 3 days, 1 week
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $settings = $this->service->enableCoolOff($user, $request->hours);

        return response()->json([
            'status' => 'success',
            'message' => 'Cool-off period enabled successfully',
            'data' => $settings,
        ]);
    }

    /**
     * Check if user can play
     */
    public function checkPlayability(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->service->canPlay($user);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Start session
     */
    public function startSession(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user can play
        $playability = $this->service->canPlay($user);

        if (!$playability['allowed']) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not allowed to play at this time',
                'restrictions' => $playability['restrictions'],
            ], 403);
        }

        $settings = $this->service->startSession($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Session started',
            'data' => $settings,
        ]);
    }

    /**
     * Reality check
     */
    public function realityCheck(Request $request): JsonResponse
    {
        $user = $request->user();
        $statistics = $this->service->getStatistics($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Reality check',
            'data' => [
                'session_duration' => $statistics['session']['minutes_elapsed'] ?? 0,
                'deposits_today' => $statistics['limits']['deposit']['daily']['used'] ?? 0,
                'wagers_today' => $statistics['limits']['wager']['daily']['used'] ?? 0,
                'losses_today' => $statistics['limits']['loss']['daily']['current'] ?? 0,
                'limits_approaching' => $this->checkApproachingLimits($statistics),
            ],
        ]);
    }

    /**
     * Check if limits are approaching
     */
    private function checkApproachingLimits(array $statistics): array
    {
        $warnings = [];

        // Check deposit limits (warn at 80%)
        foreach (['daily', 'weekly', 'monthly'] as $period) {
            $limit = $statistics['limits']['deposit'][$period]['limit'] ?? null;
            $used = $statistics['limits']['deposit'][$period]['used'] ?? 0;

            if ($limit && ($used / $limit) >= 0.8) {
                $warnings[] = [
                    'type' => 'deposit',
                    'period' => $period,
                    'percentage' => round(($used / $limit) * 100),
                    'remaining' => $statistics['limits']['deposit'][$period]['remaining'],
                ];
            }
        }

        // Check session duration (warn at 80%)
        $sessionLimit = $statistics['session']['duration_limit'] ?? null;
        $elapsed = $statistics['session']['minutes_elapsed'] ?? 0;

        if ($sessionLimit && ($elapsed / $sessionLimit) >= 0.8) {
            $warnings[] = [
                'type' => 'session',
                'percentage' => round(($elapsed / $sessionLimit) * 100),
                'remaining_minutes' => $sessionLimit - $elapsed,
            ];
        }

        return $warnings;
    }
}
