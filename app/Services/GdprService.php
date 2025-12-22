<?php

namespace App\Services;

use App\Models\User;
use App\Models\Bet;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Bonus;
use App\Models\Referral;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GdprService
{
    /**
     * Export all user data in compliance with GDPR Article 15
     * (Right of access by the data subject)
     * 
     * @param User $user
     * @return string Path to the exported ZIP file
     */
    public function exportUserData(User $user): string
    {
        Log::info('GDPR data export initiated', ['user_id' => $user->id]);

        $exportData = [
            'export_date' => now()->toIso8601String(),
            'user_id' => $user->id,
            'personal_information' => $this->getPersonalInformation($user),
            'wallet_information' => $this->getWalletInformation($user),
            'betting_history' => $this->getBettingHistory($user),
            'transaction_history' => $this->getTransactionHistory($user),
            'deposit_history' => $this->getDepositHistory($user),
            'withdrawal_history' => $this->getWithdrawalHistory($user),
            'bonus_history' => $this->getBonusHistory($user),
            'referral_information' => $this->getReferralInformation($user),
            'vip_information' => $this->getVipInformation($user),
            'audit_logs' => $this->getAuditLogs($user),
        ];

        // Create export directory
        $exportDir = storage_path('app/gdpr-exports');
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $filename = "user_{$user->id}_data_export_{$timestamp}";
        
        // Save JSON file
        $jsonPath = "{$exportDir}/{$filename}.json";
        file_put_contents($jsonPath, json_encode($exportData, JSON_PRETTY_PRINT));

        // Create human-readable HTML report
        $htmlPath = "{$exportDir}/{$filename}.html";
        file_put_contents($htmlPath, $this->generateHtmlReport($exportData));

        // Create README
        $readmePath = "{$exportDir}/{$filename}_README.txt";
        file_put_contents($readmePath, $this->generateReadme($user));

        // Create ZIP archive
        $zipPath = "{$exportDir}/{$filename}.zip";
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($jsonPath, 'data.json');
            $zip->addFile($htmlPath, 'report.html');
            $zip->addFile($readmePath, 'README.txt');
            $zip->close();

            // Clean up individual files
            unlink($jsonPath);
            unlink($htmlPath);
            unlink($readmePath);
        }

        Log::info('GDPR data export completed', [
            'user_id' => $user->id,
            'file_path' => $zipPath,
        ]);

        return $zipPath;
    }

    /**
     * Delete all user data in compliance with GDPR Article 17
     * (Right to erasure / "Right to be forgotten")
     * 
     * @param User $user
     * @param string $reason
     * @return array Deletion summary
     */
    public function deleteUserData(User $user, string $reason = 'User request'): array
    {
        Log::warning('GDPR data deletion initiated', [
            'user_id' => $user->id,
            'reason' => $reason,
        ]);

        $summary = [
            'user_id' => $user->id,
            'deletion_date' => now()->toIso8601String(),
            'reason' => $reason,
            'records_deleted' => [],
        ];

        DB::beginTransaction();

        try {
            // 1. Anonymize audit logs (retain for legal/security purposes)
            $auditCount = AuditLog::where('user_id', $user->id)
                ->update([
                    'user_id' => null,
                    'ip_address' => '[ANONYMIZED]',
                    'user_agent' => '[ANONYMIZED]',
                ]);
            $summary['records_deleted']['audit_logs'] = "{$auditCount} anonymized";

            // 2. Delete bets (or anonymize for regulatory requirements)
            $betCount = Bet::where('user_id', $user->id)->count();
            if (config('gdpr.retain_financial_records', true)) {
                Bet::where('user_id', $user->id)->update(['user_id' => null]);
                $summary['records_deleted']['bets'] = "{$betCount} anonymized";
            } else {
                Bet::where('user_id', $user->id)->delete();
                $summary['records_deleted']['bets'] = "{$betCount} deleted";
            }

            // 3. Anonymize transactions (retain for accounting)
            $transactionCount = Transaction::where('user_id', $user->id)->count();
            Transaction::where('user_id', $user->id)->update(['user_id' => null]);
            $summary['records_deleted']['transactions'] = "{$transactionCount} anonymized";

            // 4. Delete deposits/withdrawals personal data
            $depositCount = Deposit::where('user_id', $user->id)->count();
            Deposit::where('user_id', $user->id)->delete();
            $summary['records_deleted']['deposits'] = "{$depositCount} deleted";

            $withdrawalCount = Withdrawal::where('user_id', $user->id)->count();
            Withdrawal::where('user_id', $user->id)->delete();
            $summary['records_deleted']['withdrawals'] = "{$withdrawalCount} deleted";

            // 5. Delete bonuses
            $bonusCount = Bonus::where('user_id', $user->id)->count();
            Bonus::where('user_id', $user->id)->delete();
            $summary['records_deleted']['bonuses'] = "{$bonusCount} deleted";

            // 6. Delete referrals
            $referralCount = Referral::where('user_id', $user->id)
                ->orWhere('referee_id', $user->id)
                ->count();
            Referral::where('user_id', $user->id)
                ->orWhere('referee_id', $user->id)
                ->delete();
            $summary['records_deleted']['referrals'] = "{$referralCount} deleted";

            // 7. Delete wallet
            if ($user->wallet) {
                $user->wallet->delete();
                $summary['records_deleted']['wallet'] = "1 deleted";
            }

            // 8. Create final audit log before deletion
            AuditLog::create([
                'user_id' => null,
                'action' => 'gdpr.data_deletion',
                'description' => "User account deleted: {$reason}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => json_encode([
                    'original_user_id' => $user->id,
                    'deletion_summary' => $summary,
                ]),
            ]);

            // 9. Finally, delete the user account
            $user->delete();
            $summary['records_deleted']['user_account'] = "1 deleted";

            DB::commit();

            Log::warning('GDPR data deletion completed', $summary);

            return $summary;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('GDPR data deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get personal information
     */
    private function getPersonalInformation(User $user): array
    {
        return [
            'id' => $user->id,
            'phone' => $user->phone,
            'email' => $user->email,
            'name' => $user->name,
            'auth_method' => $user->auth_method,
            'metamask_address' => $user->metamask_address,
            'telegram_id' => $user->telegram_id,
            'referral_code' => $user->referral_code,
            'referred_by' => $user->referred_by,
            'is_guest' => $user->is_guest,
            'status' => $user->status,
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
        ];
    }

    /**
     * Get wallet information
     */
    private function getWalletInformation(User $user): ?array
    {
        if (!$user->wallet) {
            return null;
        }

        return [
            'real_balance' => (float) $user->wallet->real_balance,
            'bonus_balance' => (float) $user->wallet->bonus_balance,
            'locked_balance' => (float) $user->wallet->locked_balance,
            'total_deposited' => (float) $user->wallet->total_deposited,
            'total_withdrawn' => (float) $user->wallet->total_withdrawn,
            'total_wagered' => (float) $user->wallet->total_wagered,
            'currency' => $user->wallet->currency,
        ];
    }

    /**
     * Get betting history
     */
    private function getBettingHistory(User $user): array
    {
        return Bet::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($bet) => [
                'id' => $bet->id,
                'game_type' => $bet->game_type,
                'bet_amount' => (float) $bet->bet_amount,
                'win_amount' => (float) $bet->win_amount,
                'multiplier' => (float) $bet->multiplier,
                'result' => $bet->result,
                'is_win' => $bet->is_win,
                'created_at' => $bet->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get transaction history
     */
    private function getTransactionHistory(User $user): array
    {
        return Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
                'balance_after' => (float) $tx->balance_after,
                'description' => $tx->description,
                'created_at' => $tx->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get deposit history
     */
    private function getDepositHistory(User $user): array
    {
        return Deposit::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($deposit) => [
                'id' => $deposit->id,
                'amount' => (float) $deposit->amount,
                'status' => $deposit->status,
                'payment_method' => $deposit->payment_method,
                'reference_number' => $deposit->reference_number,
                'created_at' => $deposit->created_at->toIso8601String(),
                'approved_at' => $deposit->approved_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get withdrawal history
     */
    private function getWithdrawalHistory(User $user): array
    {
        return Withdrawal::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($withdrawal) => [
                'id' => $withdrawal->id,
                'amount' => (float) $withdrawal->amount,
                'status' => $withdrawal->status,
                'payment_method' => $withdrawal->payment_method,
                'gcash_number' => $withdrawal->gcash_number,
                'gcash_name' => $withdrawal->gcash_name,
                'created_at' => $withdrawal->created_at->toIso8601String(),
                'processed_at' => $withdrawal->processed_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get bonus history
     */
    private function getBonusHistory(User $user): array
    {
        return Bonus::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($bonus) => [
                'id' => $bonus->id,
                'type' => $bonus->type,
                'amount' => (float) $bonus->amount,
                'wagering_requirement' => (float) $bonus->wagering_requirement,
                'wagered_amount' => (float) $bonus->wagered_amount,
                'status' => $bonus->status,
                'created_at' => $bonus->created_at->toIso8601String(),
                'expires_at' => $bonus->expires_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get referral information
     */
    private function getReferralInformation(User $user): array
    {
        $referrals = Referral::where('user_id', $user->id)->get();
        $referredBy = Referral::where('referee_id', $user->id)->first();

        return [
            'referral_code' => $user->referral_code,
            'total_referrals' => $user->referral_count ?? 0,
            'total_referral_earnings' => (float) ($user->total_referral_earnings ?? 0),
            'referred_by_user_id' => $referredBy?->user_id,
            'referrals' => $referrals->map(fn($ref) => [
                'referee_id' => $ref->referee_id,
                'reward_amount' => (float) $ref->reward_amount,
                'status' => $ref->status,
                'created_at' => $ref->created_at->toIso8601String(),
            ])->toArray(),
        ];
    }

    /**
     * Get VIP information
     */
    private function getVipInformation(User $user): array
    {
        return [
            'vip_level' => $user->vip_level,
            'vip_points' => (float) ($user->vip_points ?? 0),
            'total_wagered' => (float) ($user->wallet->total_wagered ?? 0),
        ];
    }

    /**
     * Get audit logs
     */
    private function getAuditLogs(User $user): array
    {
        return AuditLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(1000) // Limit to last 1000 logs
            ->get()
            ->map(fn($log) => [
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport(array $data): string
    {
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>GDPR Data Export Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4F46E5; padding-bottom: 10px; }
        h2 { color: #4F46E5; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4F46E5; color: white; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 15px 0; }
        .info-item { padding: 10px; background: #f9f9f9; border-radius: 4px; }
        .info-label { font-weight: bold; color: #666; }
        .info-value { color: #333; margin-top: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>GDPR Data Export Report</h1>
        <p><strong>Export Date:</strong> {$data['export_date']}</p>
        <p><strong>User ID:</strong> {$data['user_id']}</p>
        
        <h2>Personal Information</h2>
        <div class='info-grid'>";
        
        foreach ($data['personal_information'] as $key => $value) {
            $html .= "<div class='info-item'>
                <div class='info-label'>" . ucwords(str_replace('_', ' ', $key)) . "</div>
                <div class='info-value'>" . htmlspecialchars($value ?? 'N/A') . "</div>
            </div>";
        }
        
        $html .= "</div>
        
        <h2>Wallet Information</h2>";
        
        if ($data['wallet_information']) {
            $html .= "<div class='info-grid'>";
            foreach ($data['wallet_information'] as $key => $value) {
                $html .= "<div class='info-item'>
                    <div class='info-label'>" . ucwords(str_replace('_', ' ', $key)) . "</div>
                    <div class='info-value'>₱" . number_format($value, 2) . "</div>
                </div>";
            }
            $html .= "</div>";
        } else {
            $html .= "<p>No wallet information available.</p>";
        }
        
        $html .= "
        <h2>Statistics</h2>
        <div class='info-grid'>
            <div class='info-item'>
                <div class='info-label'>Total Bets</div>
                <div class='info-value'>" . count($data['betting_history']) . "</div>
            </div>
            <div class='info-item'>
                <div class='info-label'>Total Transactions</div>
                <div class='info-value'>" . count($data['transaction_history']) . "</div>
            </div>
            <div class='info-item'>
                <div class='info-label'>Total Deposits</div>
                <div class='info-value'>" . count($data['deposit_history']) . "</div>
            </div>
            <div class='info-item'>
                <div class='info-label'>Total Withdrawals</div>
                <div class='info-value'>" . count($data['withdrawal_history']) . "</div>
            </div>
        </div>
        
        <p style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px;'>
            This export contains all personal data we hold about you in compliance with GDPR Article 15.
            For detailed machine-readable data, please refer to the data.json file included in this export.
        </p>
    </div>
</body>
</html>";
        
        return $html;
    }

    /**
     * Generate README file
     */
    private function generateReadme(User $user): string
    {
        return "GDPR DATA EXPORT
================

User ID: {$user->id}
Export Date: " . now()->format('Y-m-d H:i:s') . "

This ZIP archive contains all personal data we hold about you in compliance 
with the General Data Protection Regulation (GDPR) Article 15.

FILES INCLUDED:
--------------
1. data.json - Complete machine-readable export of all your data
2. report.html - Human-readable HTML report (open in web browser)
3. README.txt - This file

DATA CATEGORIES:
---------------
- Personal Information (account details, contact information)
- Wallet Information (balances, transaction totals)
- Betting History (all bets placed)
- Transaction History (deposits, withdrawals, transfers)
- Deposit History (payment details)
- Withdrawal History (payout details)
- Bonus History (bonuses received and used)
- Referral Information (referral code and earnings)
- VIP Information (level and benefits)
- Audit Logs (account activity logs)

YOUR RIGHTS UNDER GDPR:
----------------------
- Right of access (Article 15) - You have received this data
- Right to rectification (Article 16) - Contact support to correct data
- Right to erasure (Article 17) - Request account deletion
- Right to restriction of processing (Article 18)
- Right to data portability (Article 20) - This export
- Right to object (Article 21)

For questions or to exercise your rights, please contact our Data Protection Officer:
Email: privacy@yourdomain.com

This export was generated automatically and contains accurate data as of the export date.
";
    }
}
