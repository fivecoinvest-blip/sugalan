<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\BonusController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VipController;
use App\Http\Controllers\Api\VipPromotionController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\VerificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register/phone', [AuthController::class, 'registerPhone']);
    Route::post('/login/phone', [AuthController::class, 'loginPhone']);
    Route::post('/metamask', [AuthController::class, 'authenticateMetaMask']);
    Route::post('/telegram', [AuthController::class, 'authenticateTelegram']);
    Route::post('/guest', [AuthController::class, 'createGuest']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

// Public provably fair verification
Route::prefix('games')->group(function () {
    Route::post('/verify', [VerificationController::class, 'verify']);
    Route::get('/verify/instructions', [VerificationController::class, 'instructions']);
});

// Public referral code validation
Route::post('/referrals/validate', [ReferralController::class, 'validateCode']);

// Public game info
Route::get('/games/wheel/config', [GameController::class, 'getWheelConfig']);
Route::get('/games/crash/current', [GameController::class, 'getCurrentCrashRound']);

// Protected routes (JWT)
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Wallet
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });

    // Payments
    Route::prefix('payments')->group(function () {
        // GCash accounts
        Route::get('/gcash-accounts', [PaymentController::class, 'getGcashAccounts']);

        // Deposits
        Route::post('/deposits', [PaymentController::class, 'createDeposit']);
        Route::get('/deposits', [PaymentController::class, 'getDepositHistory']);

        // Withdrawals
        Route::post('/withdrawals', [PaymentController::class, 'createWithdrawal']);
        Route::get('/withdrawals', [PaymentController::class, 'getWithdrawalHistory']);

        // Statistics
        Route::get('/stats', [PaymentController::class, 'getPaymentStats']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'getNotifications']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Bonuses
    Route::prefix('bonuses')->group(function () {
        Route::get('/active', [BonusController::class, 'getActiveBonuses']);
        Route::get('/history', [BonusController::class, 'getBonusHistory']);
        Route::post('/{bonusId}/cancel', [BonusController::class, 'cancelBonus']);
        Route::get('/wagering-stats', [BonusController::class, 'getWageringStats']);
    });

    // Referrals
    Route::prefix('referrals')->group(function () {
        Route::get('/stats', [ReferralController::class, 'getStats']);
        Route::get('/leaderboard', [ReferralController::class, 'getLeaderboard']);
        Route::get('/my-code', [ReferralController::class, 'getMyReferralCode']);
        Route::get('/referred-users', [ReferralController::class, 'getReferredUsers']);
    });

    // User Profile
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::post('/change-password', [UserController::class, 'changePassword']);
        Route::get('/statistics', [UserController::class, 'getStatistics']);
    });

    // VIP System
    Route::prefix('vip')->group(function () {
        Route::get('/benefits', [VipController::class, 'getBenefits']);
        Route::get('/levels', [VipController::class, 'getLevels']);
        Route::get('/progress', [VipController::class, 'getProgress']);
        
        // VIP Promotions
        Route::get('/promotions/available', [VipPromotionController::class, 'getAvailablePromotions']);
        Route::get('/promotions/claimed', [VipPromotionController::class, 'getClaimedPromotions']);
        Route::post('/promotions/claim', [VipPromotionController::class, 'claimPromotion']);
    });

    // Promotional Campaigns & Daily Rewards
    Route::prefix('promotions')->group(function () {
        Route::get('/campaigns', [PromotionController::class, 'getCampaigns']);
        Route::get('/campaigns/code/{code}', [PromotionController::class, 'getCampaignByCode']);
        Route::post('/campaigns/claim', [PromotionController::class, 'claimCampaign']);
        Route::get('/campaigns/claimed', [PromotionController::class, 'getClaimedCampaigns']);
        
        // Daily Rewards
        Route::get('/daily-reward/status', [PromotionController::class, 'getDailyRewardStatus']);
        Route::post('/daily-reward/claim', [PromotionController::class, 'claimDailyReward']);
        Route::get('/daily-reward/history', [PromotionController::class, 'getDailyRewardHistory']);
    });

    // Games
    Route::prefix('games')->group(function () {
        // Dice
        Route::post('/dice/play', [GameController::class, 'playDice']);

        // Hi-Lo
        Route::post('/hilo/start', [GameController::class, 'startHilo']);
        Route::post('/hilo/predict', [GameController::class, 'predictHilo']);
        Route::post('/hilo/cashout', [GameController::class, 'cashoutHilo']);

        // Mines
        Route::post('/mines/start', [GameController::class, 'startMines']);
        Route::post('/mines/reveal', [GameController::class, 'revealMines']);
        Route::post('/mines/cashout', [GameController::class, 'cashoutMines']);

        // Plinko
        Route::post('/plinko/play', [GameController::class, 'playPlinko']);

        // Keno
        Route::post('/keno/play', [GameController::class, 'playKeno']);

        // Wheel
        Route::post('/wheel/spin', [GameController::class, 'spinWheel']);

        // Crash
        Route::post('/crash/bet', [GameController::class, 'placeCrashBet']);
        Route::post('/crash/cashout', [GameController::class, 'cashoutCrash']);

        // Pump
        Route::post('/pump/bet', [GameController::class, 'placePumpBet']);
        Route::post('/pump/cashout', [GameController::class, 'cashoutPump']);
        Route::get('/pump/round', [GameController::class, 'getCurrentPumpRound']);
    });
});

// Admin authentication (public)
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/refresh', [AdminAuthController::class, 'refresh']);
});

// Admin routes (JWT with admin middleware)
Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    // Admin auth
    Route::get('/auth/profile', [AdminAuthController::class, 'profile']);
    Route::post('/auth/logout', [AdminAuthController::class, 'logout']);

    // Payment management (requires payment approval permission)
    Route::middleware('admin.permission:manage_payments')->group(function () {
        // Deposits
        Route::prefix('payments/deposits')->group(function () {
            Route::get('/pending', [AdminPaymentController::class, 'getPendingDeposits']);
            Route::get('/{id}', [AdminPaymentController::class, 'getDepositDetails']);
            Route::post('/{id}/approve', [AdminPaymentController::class, 'approveDeposit']);
            Route::post('/{id}/reject', [AdminPaymentController::class, 'rejectDeposit']);
        });

        // Withdrawals
        Route::prefix('payments/withdrawals')->group(function () {
            Route::get('/pending', [AdminPaymentController::class, 'getPendingWithdrawals']);
            Route::get('/{id}', [AdminPaymentController::class, 'getWithdrawalDetails']);
            Route::post('/{id}/approve', [AdminPaymentController::class, 'approveWithdrawal']);
            Route::post('/{id}/reject', [AdminPaymentController::class, 'rejectWithdrawal']);
        });

        // Statistics
        Route::get('/payments/statistics', [AdminPaymentController::class, 'getPaymentStatistics']);
        Route::get('/payments/history', [AdminPaymentController::class, 'getPaymentHistory']);
    });

    // Promotion management (requires manage_promotions permission)
    Route::middleware('admin.permission:manage_promotions')->group(function () {
        Route::prefix('promotions')->group(function () {
            // Campaigns
            Route::get('/campaigns', [AdminPromotionController::class, 'getCampaigns']);
            Route::get('/campaigns/{id}/statistics', [AdminPromotionController::class, 'getCampaignStatistics']);
            Route::post('/campaigns', [AdminPromotionController::class, 'createCampaign']);
            Route::put('/campaigns/{id}', [AdminPromotionController::class, 'updateCampaign']);
            Route::delete('/campaigns/{id}', [AdminPromotionController::class, 'deleteCampaign']);
            
            // Daily Rewards Statistics
            Route::get('/daily-rewards/statistics', [AdminPromotionController::class, 'getDailyRewardStatistics']);
        });
    });
});
