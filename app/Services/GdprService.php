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
     * @param User|int $user
     * @return string Path to the exported ZIP file
     */
    public function exportUserData(User|int $user): string
    {
        // Convert int to User model if needed
        if (is_int($user)) {
            $user = User::with('wallet')->findOrFail($user);
        }
        
        // Validate user has an ID (throw TypeError if invalid)
        if (!$user->id) {
            throw new \TypeError('User must have a valid ID');
        }

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

        $timestamp = now()->format('Y-m-d_His');
        $filename = "user_{$user->id}_data_export_{$timestamp}";
        $directory = "gdpr-exports";
        
        // Save JSON file
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT);
        Storage::put("{$directory}/{$filename}.json", $jsonContent);

        // Create human-readable HTML report
        $htmlContent = $this->generateHtmlReport($exportData);
        Storage::put("{$directory}/{$filename}.html", $htmlContent);

        // Create README
        $readmeContent = $this->generateReadme($user);
        Storage::put("{$directory}/{$filename}_README.txt", $readmeContent);

        // Create ZIP archive
        $zipPath = "{$directory}/{$filename}.zip";
        $zip = new ZipArchive();
        
        if ($zip->open(Storage::path($zipPath), ZipArchive::CREATE) === TRUE) {
            $zip->addFile(Storage::path("{$directory}/{$filename}.json"), 'data.json');
            $zip->addFile(Storage::path("{$directory}/{$filename}.html"), 'export.html');
            $zip->addFile(Storage::path("{$directory}/{$filename}_README.txt"), 'README.txt');
            $zip->close();

            // Clean up individual files
            Storage::delete("{$directory}/{$filename}.json");
            Storage::delete("{$directory}/{$filename}.html");
            Storage::delete("{$directory}/{$filename}_README.txt");
        }

        Log::info('GDPR data export completed', [
            'user_id' => $user->id,
            'file_path' => $zipPath,
        ]);

        return Storage::path($zipPath);
    }

    /**
     * Get user data as array (for testing and internal use)
     * 
     * @param int $userId
     * @return array
     */
    public function getUserData(int $userId): array
    {
        $user = User::with([
            'wallet',
            'bets',
            'transactions',
            'deposits',
            'withdrawals',
            'bonuses',
            'referrals'
        ])->findOrFail($userId);

        return [
            'profile' => $this->getPersonalInformation($user),
            'wallet' => $this->getWalletInformation($user),
            'bets' => $this->getBettingHistory($user),
            'transactions' => $this->getTransactionHistory($user),
            'deposits' => $this->getDepositHistory($user),
            'withdrawals' => $this->getWithdrawalHistory($user),
            'bonuses' => $this->getBonusHistory($user),
            'referrals' => $this->getReferralInformation($user),
            'vip' => $this->getVipInformation($user),
            'audit_logs' => $this->getAuditLogs($user),
        ];
    }

    /**
     * Delete all user data in compliance with GDPR Article 17
     * (Right to erasure / "Right to be forgotten")
     * 
     * @param User|int $user
     * @param string $reason
     * @return bool Success status
     */
    public function deleteUserData(User|int $user, string $reason = 'User request'): bool
    {
        // Convert int to User model if needed
        if (is_int($user)) {
            $user = User::findOrFail($user);
        }

        Log::warning('GDPR data deletion initiated', [
            'user_id' => $user->id,
            'reason' => $reason,
        ]);

        DB::beginTransaction();

        try {
            // Check if we should anonymize instead of delete
            if (config('gdpr.anonymize_instead_of_delete', false)) {
                // Anonymize user data but keep the account active (not soft-deleted)
                // Need to update both encrypted and plain fields
                DB::table('users')->where('id', $user->id)->update([
                    'email' => 'deleted_user_' . $user->id . '@anonymized.local',
                    'email_encrypted' => null,
                    'email_hash' => null,
                    'phone_number' => null,
                    'phone_encrypted' => null,
                    'phone_hash' => null,
                    'name' => 'Deleted User',
                    'wallet_address' => null,
                    'telegram_id' => null,
                    'telegram_username' => null,
                ]);
                
                // Do NOT soft delete - keep account active but anonymized
                
                Log::info('User data anonymized', ['user_id' => $user->id]);
            } else {
                // Check if financial records should be preserved
                if (!config('gdpr.preserve_financial_records', false)) {
                    // Delete all related data (will cascade via foreign keys)
                    // Explicitly delete non-cascading relations
                    Referral::where('user_id', $user->id)
                        ->orWhere('referee_id', $user->id)
                        ->delete();
                }
                
                // Soft delete user (this will trigger cascade deletes via foreign key constraints)
                $user->delete();
                
                Log::info('User data deleted', ['user_id' => $user->id]);
            }

            // Create audit log
            AuditLog::create([
                'user_id' => null,
                'actor_type' => 'system',
                'actor_id' => null,
                'action' => 'gdpr.data_deletion',
                'description' => "User account deleted: {$reason}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI',
                'metadata' => json_encode([
                    'original_user_id' => $user->id,
                    'reason' => $reason,
                ]),
            ]);

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('GDPR data deletion failed', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get personal information
     */
    private function getPersonalInformation(User $user): array
    {
        return [
            'id' => $user->id,
            'phone_number' => $user->phone_number,
            'email' => $user->email,
            'name' => $user->name,
            'username' => $user->username,
            'auth_method' => $user->auth_method,
            'wallet_address' => $user->wallet_address,
            'telegram_id' => $user->telegram_id,
            'telegram_username' => $user->telegram_username,
            'referral_code' => $user->referral_code,
            'referred_by' => $user->referred_by,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
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
