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
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\ResponsibleGamingController;
use App\Http\Controllers\CookieConsentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register/phone', [AuthController::class, 'registerPhone']);
    Route::post('/register', [AuthController::class, 'registerPhone']); // Alias
    Route::post('/login/phone', [AuthController::class, 'loginPhone']);
    Route::post('/login', [AuthController::class, 'loginPhone']); // Alias
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

    // Shorthand payment routes (for backward compatibility with tests)
    Route::post('/deposits', [PaymentController::class, 'createDeposit']);
    Route::get('/deposits', [PaymentController::class, 'getDepositHistory']);
    Route::get('/deposits/{id}', [PaymentController::class, 'getDeposit']);
    Route::delete('/deposits/{id}', [PaymentController::class, 'cancelDeposit']);
    Route::post('/withdrawals', [PaymentController::class, 'createWithdrawal']);
    Route::get('/withdrawals', [PaymentController::class, 'getWithdrawalHistory']);
    Route::delete('/withdrawals/{id}', [PaymentController::class, 'cancelWithdrawal']);

    // Payments (full namespace)
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

    // Slot Games
    Route::prefix('slots')->group(function () {
        Route::get('/providers', [\App\Http\Controllers\Api\SlotGameController::class, 'getProviders']);
        Route::get('/games', [\App\Http\Controllers\Api\SlotGameController::class, 'getAllGames']);
        Route::get('/providers/{providerId}/games', [\App\Http\Controllers\Api\SlotGameController::class, 'getGamesByProvider']);
        Route::get('/games/{gameId}', [\App\Http\Controllers\Api\SlotGameController::class, 'getGameDetails']);
        Route::post('/games/{gameId}/launch', [\App\Http\Controllers\Api\SlotGameController::class, 'launchGame']);
        Route::get('/bets/history', [\App\Http\Controllers\Api\SlotGameController::class, 'getBetHistory']);
        Route::get('/bets/stats', [\App\Http\Controllers\Api\SlotGameController::class, 'getStats']);
    });
});

// Slot game callbacks (no auth - provider callbacks)
Route::prefix('callbacks/slots')->group(function () {
    Route::post('/balance', [\App\Http\Controllers\Api\SlotCallbackController::class, 'balance']);
    Route::post('/debit', [\App\Http\Controllers\Api\SlotCallbackController::class, 'debit']);
    Route::post('/credit', [\App\Http\Controllers\Api\SlotCallbackController::class, 'credit']);
    Route::post('/rollback', [\App\Http\Controllers\Api\SlotCallbackController::class, 'rollback']);
});

// Admin authentication (public)
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/refresh', [AdminAuthController::class, 'refresh']);
});

// Admin routes (JWT with admin guard and middleware)
Route::middleware(['auth:admin', 'admin'])->prefix('admin')->group(function () {
    // Admin auth
    Route::get('/auth/profile', [AdminAuthController::class, 'profile']);
    Route::post('/auth/logout', [AdminAuthController::class, 'logout']);

    // Dashboard statistics
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);

    // Deposit management (requires manage_deposits permission)
    Route::middleware('admin.permission:manage_deposits')->group(function () {
        Route::prefix('payments/deposits')->group(function () {
            Route::get('/pending', [AdminPaymentController::class, 'getPendingDeposits']);
            Route::get('/{id}', [AdminPaymentController::class, 'getDepositDetails']);
            Route::post('/{id}/approve', [AdminPaymentController::class, 'approveDeposit']);
            Route::post('/{id}/reject', [AdminPaymentController::class, 'rejectDeposit']);
        });
    });

    // Withdrawal management (requires manage_withdrawals permission)
    Route::middleware('admin.permission:manage_withdrawals')->group(function () {
        Route::prefix('payments/withdrawals')->group(function () {
            Route::get('/pending', [AdminPaymentController::class, 'getPendingWithdrawals']);
            Route::get('/{id}', [AdminPaymentController::class, 'getWithdrawalDetails']);
            Route::post('/{id}/approve', [AdminPaymentController::class, 'approveWithdrawal']);
            Route::post('/{id}/reject', [AdminPaymentController::class, 'rejectWithdrawal']);
        });
    });

    // Payment statistics (viewable by both deposit and withdrawal managers)
    Route::get('/payments/statistics', [AdminPaymentController::class, 'getPaymentStatistics']);
    Route::get('/payments/history', [AdminPaymentController::class, 'getPaymentHistory']);

    // Promotion management (requires manage_bonuses permission)
    Route::middleware('admin.permission:manage_bonuses')->group(function () {
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
    
    // Slot Game Management (requires manage_games permission)
    Route::middleware('admin.permission:manage_games')->group(function () {
        Route::prefix('slots')->group(function () {
            // Providers
            Route::get('/providers', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'getProviders']);
            Route::post('/providers', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'saveProvider']);
            Route::put('/providers/{id}', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'saveProvider']);
            Route::delete('/providers/{id}', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'deleteProvider']);
            
            // Games
            Route::get('/games', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'getGames']);
            Route::post('/games', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'saveGame']);
            Route::put('/games/{id}', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'saveGame']);
            Route::post('/games/{id}/toggle-status', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'toggleGameStatus']);
            Route::delete('/games/{id}', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'deleteGame']);
            Route::post('/providers/{id}/sync', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'syncGames']);
            
            // Statistics
            Route::get('/statistics', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'getStatistics']);
            Route::get('/bets/history', [\App\Http\Controllers\Api\Admin\SlotGameManagementController::class, 'getBetHistory']);
        });
    });
    
    // Analytics routes (requires view_reports permission)
    Route::middleware('admin.permission:view_reports')->group(function () {
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('/realtime', [AnalyticsController::class, 'realtime']);
            Route::get('/export', [AnalyticsController::class, 'export']);
        });
    });
});

// GDPR routes (protected)
Route::middleware('auth:api')->prefix('gdpr')->group(function () {
    Route::get('/summary', [GdprController::class, 'summary']);
    Route::post('/export', [GdprController::class, 'export']);
    Route::get('/download/{token}', [GdprController::class, 'download'])->name('gdpr.download');
    Route::post('/rectification', [GdprController::class, 'rectification']);
    Route::post('/delete-account', [GdprController::class, 'deleteAccount']);
});

// Responsible Gaming routes (protected)
Route::middleware('auth:api')->prefix('responsible-gaming')->group(function () {
    Route::get('/settings', [ResponsibleGamingController::class, 'getSettings']);
    Route::get('/statistics', [ResponsibleGamingController::class, 'getStatistics']);
    Route::get('/check-playability', [ResponsibleGamingController::class, 'checkPlayability']);
    
    // Limits
    Route::post('/deposit-limits', [ResponsibleGamingController::class, 'setDepositLimits']);
    Route::post('/wager-limits', [ResponsibleGamingController::class, 'setWagerLimits']);
    Route::post('/loss-limits', [ResponsibleGamingController::class, 'setLossLimits']);
    Route::post('/session-limits', [ResponsibleGamingController::class, 'setSessionLimits']);
    
    // Self-exclusion
    Route::post('/self-exclusion', [ResponsibleGamingController::class, 'enableSelfExclusion']);
    Route::post('/self-exclusion/remove-request', [ResponsibleGamingController::class, 'requestSelfExclusionRemoval']);
    
    // Cool-off
    Route::post('/cool-off', [ResponsibleGamingController::class, 'enableCoolOff']);
    
    // Session management
    Route::post('/session/start', [ResponsibleGamingController::class, 'startSession']);
    Route::get('/reality-check', [ResponsibleGamingController::class, 'realityCheck']);
});

// Cookie Consent routes
Route::prefix('cookies')->group(function () {
    Route::get('/preferences', [CookieConsentController::class, 'getPreferences']);
    Route::post('/preferences', [CookieConsentController::class, 'savePreferences']);
    Route::post('/accept-all', [CookieConsentController::class, 'acceptAll']);
    Route::post('/reject-all', [CookieConsentController::class, 'rejectAll']);
    Route::delete('/consent', [CookieConsentController::class, 'clearConsent']);
});


