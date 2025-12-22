<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;

class NotificationService
{
    /**
     * Create a notification for a user
     */
    public function createNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Notify user of deposit approval
     */
    public function notifyDepositApproved(User $user, float $amount, string $referenceNumber): void
    {
        $this->createNotification(
            $user,
            'deposit_approved',
            'Deposit Approved',
            "Your deposit of ₱" . number_format($amount, 2) . " has been approved and credited to your wallet.",
            [
                'amount' => $amount,
                'reference_number' => $referenceNumber,
            ]
        );
    }

    /**
     * Notify user of deposit rejection
     */
    public function notifyDepositRejected(User $user, float $amount, string $reason): void
    {
        $this->createNotification(
            $user,
            'deposit_rejected',
            'Deposit Rejected',
            "Your deposit of ₱" . number_format($amount, 2) . " was rejected. Reason: {$reason}",
            [
                'amount' => $amount,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify user of withdrawal approval
     */
    public function notifyWithdrawalApproved(User $user, float $amount, string $gcashNumber): void
    {
        $this->createNotification(
            $user,
            'withdrawal_approved',
            'Withdrawal Approved',
            "Your withdrawal of ₱" . number_format($amount, 2) . " has been approved. Funds will be sent to {$gcashNumber}.",
            [
                'amount' => $amount,
                'gcash_number' => $gcashNumber,
            ]
        );
    }

    /**
     * Notify user of withdrawal rejection
     */
    public function notifyWithdrawalRejected(User $user, float $amount, string $reason): void
    {
        $this->createNotification(
            $user,
            'withdrawal_rejected',
            'Withdrawal Rejected',
            "Your withdrawal of ₱" . number_format($amount, 2) . " was rejected. Reason: {$reason}. Your balance has been unlocked.",
            [
                'amount' => $amount,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify user of VIP level upgrade
     */
    public function notifyVipUpgrade(User $user, $oldLevel, $newLevel): void
    {
        $this->createNotification(
            $user,
            'vip_upgrade',
            'VIP Level Upgrade! 🎉',
            "Congratulations! You've been upgraded from {$oldLevel->name} to {$newLevel->name}. Enjoy your enhanced benefits!",
            [
                'old_level' => $oldLevel->name,
                'new_level' => $newLevel->name,
            ]
        );
    }

    /**
     * Notify user of VIP level downgrade
     */
    public function notifyVipDowngrade(
        User $user, 
        $oldLevel, 
        $newLevel, 
        float $recentWagered, 
        float $requiredActivity
    ): void {
        $this->createNotification(
            $user,
            'vip_downgrade',
            'VIP Level Update',
            "Your VIP level has been adjusted from {$oldLevel->name} to {$newLevel->name} due to inactivity. Wager ₱" . number_format($requiredActivity, 2) . " in 90 days to maintain your tier. Recent activity: ₱" . number_format($recentWagered, 2),
            [
                'old_level' => $oldLevel->name,
                'new_level' => $newLevel->name,
                'recent_wagered' => $recentWagered,
                'required_activity' => $requiredActivity,
            ]
        );
    }

    /**
     * Notify user of bonus received
     */
    public function notifyBonusReceived(User $user, float $amount, string $type): void
    {
        $this->createNotification(
            $user,
            'bonus_received',
            'Bonus Received',
            "You've received a {$type} bonus of ₱" . number_format($amount, 2) . "!",
            [
                'amount' => $amount,
                'type' => $type,
            ]
        );
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(User $user, int $perPage = 20, ?bool $unreadOnly = false)
    {
        $query = $user->notifications()->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->paginate($perPage);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, User $user): void
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(User $user): void
    {
        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Delete old read notifications
     */
    public function deleteOldNotifications(int $daysOld = 30): int
    {
        return Notification::where('is_read', true)
            ->where('read_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}
