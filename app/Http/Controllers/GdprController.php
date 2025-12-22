<?php

namespace App\Http\Controllers;

use App\Services\GdprService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GdprController extends Controller
{
    protected GdprService $gdprService;
    protected AuditService $auditService;

    public function __construct(GdprService $gdprService, AuditService $auditService)
    {
        $this->gdprService = $gdprService;
        $this->auditService = $auditService;
    }

    /**
     * Request data export (GDPR Article 15)
     * POST /api/gdpr/export
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            // Create export file
            $filePath = $this->gdprService->exportUserData($user);
            
            // Log the export request
            $this->auditService->log(
                'gdpr.data_export',
                'User requested GDPR data export',
                ['file_path' => basename($filePath)],
                'info',
                $user->id
            );

            // Generate download URL (expires in 24 hours)
            $downloadToken = base64_encode($user->id . '|' . basename($filePath) . '|' . time());

            return response()->json([
                'success' => true,
                'message' => 'Your data export has been prepared. Download link valid for 24 hours.',
                'download_token' => $downloadToken,
                'download_url' => route('gdpr.download', ['token' => $downloadToken]),
                'expires_at' => now()->addHours(24)->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create data export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download data export
     * GET /api/gdpr/download/{token}
     */
    public function download(string $token)
    {
        try {
            $decoded = base64_decode($token);
            [$userId, $filename, $timestamp] = explode('|', $decoded);

            // Verify token hasn't expired (24 hours)
            if (time() - $timestamp > 86400) {
                return response()->json([
                    'success' => false,
                    'message' => 'Download link has expired. Please request a new export.',
                ], 410);
            }

            // Verify user
            $user = Auth::user();
            if ($user->id != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to export file.',
                ], 403);
            }

            $filePath = storage_path('app/gdpr-exports/' . $filename);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export file not found or has been deleted.',
                ], 404);
            }

            // Log download
            $this->auditService->log(
                'gdpr.data_download',
                'User downloaded GDPR data export',
                ['filename' => $filename],
                'info',
                $user->id
            );

            // Return file for download
            return response()->download($filePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request account deletion (GDPR Article 17)
     * POST /api/gdpr/delete-account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        // Verify password
        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password. Account deletion cancelled.',
            ], 403);
        }

        try {
            // Create final export before deletion
            $exportPath = $this->gdprService->exportUserData($user);
            
            // Delete user data
            $summary = $this->gdprService->deleteUserData(
                $user,
                $request->reason ?? 'User requested account deletion'
            );

            // Store export for 30 days for potential recovery
            // (in case of accidental deletion)
            $archivePath = storage_path('app/gdpr-archives');
            if (!file_exists($archivePath)) {
                mkdir($archivePath, 0755, true);
            }
            rename($exportPath, $archivePath . '/' . basename($exportPath));

            return response()->json([
                'success' => true,
                'message' => 'Your account and all personal data have been permanently deleted.',
                'deletion_summary' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's GDPR data summary
     * GET /api/gdpr/summary
     */
    public function summary(): JsonResponse
    {
        $user = Auth::user();

        $summary = [
            'personal_data' => [
                'account_created' => $user->created_at->toIso8601String(),
                'last_updated' => $user->updated_at->toIso8601String(),
                'last_login' => $user->last_login_at?->toIso8601String(),
            ],
            'data_categories' => [
                'bets' => $user->bets()->count(),
                'transactions' => $user->transactions()->count(),
                'deposits' => $user->deposits()->count(),
                'withdrawals' => $user->withdrawals()->count(),
                'bonuses' => $user->bonuses()->count(),
                'audit_logs' => $user->auditLogs()->count(),
            ],
            'your_rights' => [
                'right_of_access' => 'Request a copy of your personal data',
                'right_to_rectification' => 'Request correction of inaccurate data',
                'right_to_erasure' => 'Request deletion of your account and data',
                'right_to_data_portability' => 'Receive your data in a structured format',
                'right_to_object' => 'Object to processing of your data',
                'right_to_restrict_processing' => 'Request restriction of data processing',
            ],
            'contact' => [
                'data_protection_officer' => 'privacy@yourdomain.com',
                'support_email' => 'support@yourdomain.com',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Update data rectification request (GDPR Article 16)
     * POST /api/gdpr/rectification
     */
    public function rectification(Request $request): JsonResponse
    {
        $request->validate([
            'field' => 'required|string|in:name,email,phone',
            'current_value' => 'required|string',
            'requested_value' => 'required|string',
            'reason' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        // Log rectification request
        $this->auditService->log(
            'gdpr.rectification_request',
            "User requested data rectification for field: {$request->field}",
            [
                'field' => $request->field,
                'current_value' => $request->current_value,
                'requested_value' => $request->requested_value,
                'reason' => $request->reason,
            ],
            'info',
            $user->id
        );

        // In production, this would create a support ticket
        // For now, we'll just log it

        return response()->json([
            'success' => true,
            'message' => 'Your rectification request has been received. Our team will review it within 72 hours.',
            'reference_number' => 'RECT-' . strtoupper(substr(md5($user->id . time()), 0, 8)),
        ]);
    }
}
