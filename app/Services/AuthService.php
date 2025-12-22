<?php

namespace App\Services;

use App\Models\User;
use App\Models\Seed;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    protected $walletService;
    protected $bonusService;
    protected $referralService;

    public function __construct(
        WalletService $walletService,
        BonusService $bonusService,
        ReferralService $referralService
    ) {
        $this->walletService = $walletService;
        $this->bonusService = $bonusService;
        $this->referralService = $referralService;
    }
    /**
     * Register user with phone and password
     */
    public function registerWithPhone(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Create user
            $user = User::create([
                'uuid' => Str::uuid(),
                'username' => $data['username'] ?? null,
                'phone_number' => $data['phone_number'],
                'password' => Hash::make($data['password']),
                'auth_method' => 'phone',
                'vip_level_id' => 1, // Bronze
                'referral_code' => strtoupper(Str::random(8)),
                'referred_by' => $data['referred_by'] ?? null,
                'status' => 'active',
            ]);

            // Create wallet
            $user->wallet()->create([
                'real_balance' => 0,
                'bonus_balance' => 0,
                'locked_balance' => 0,
            ]);

            // Create initial seed pair
            Seed::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            // Award sign-up bonus (₱50 welcome bonus)
            $this->bonusService->awardSignUpBonus($user);

            // Track referral if referred by someone
            if (!empty($data['referred_by'])) {
                $this->referralService->trackReferral($user, $data['referred_by']);
            }

            // Audit log
            $this->logAction('user_registered', $user, null, $user->toArray());

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return [
                'user' => $user->load('vipLevel', 'wallet'),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ];
        });
    }

    /**
     * Login with phone and password
     */
    public function loginWithPhone(string $phoneNumber, string $password): ?array
    {
        $user = User::where('phone_number', $phoneNumber)
            ->where('auth_method', 'phone')
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        if (!$user->isActive()) {
            throw new \Exception('Account is ' . $user->status);
        }

        return $this->generateAuthResponse($user);
    }

    /**
     * Login/Register with MetaMask wallet
     */
    public function authenticateWithMetaMask(string $walletAddress, string $signature, string $message): array
    {
        // Verify signature (simplified - implement proper signature verification)
        // In production, verify the signature matches the wallet address
        
        return DB::transaction(function () use ($walletAddress) {
            $user = User::where('wallet_address', $walletAddress)
                ->where('auth_method', 'metamask')
                ->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'uuid' => Str::uuid(),
                    'username' => 'User_' . substr($walletAddress, 0, 8),
                    'wallet_address' => $walletAddress,
                    'auth_method' => 'metamask',
                    'vip_level_id' => 1,
                    'status' => 'active',
                ]);

                // Create wallet
                $user->wallet()->create([
                    'real_balance' => 0,
                    'bonus_balance' => 0,
                    'locked_balance' => 0,
                ]);

                // Create initial seed
                Seed::create([
                    'user_id' => $user->id,
                    'is_active' => true,
                ]);

                // Award sign-up bonus
                $this->bonusService->awardSignUpBonus($user);

                $this->logAction('user_registered_metamask', $user, null, $user->toArray());
            }

            return $this->generateAuthResponse($user);
        });
    }

    /**
     * Login/Register with Telegram
     */
    public function authenticateWithTelegram(array $telegramData): array
    {
        return DB::transaction(function () use ($telegramData) {
            $user = User::where('telegram_id', $telegramData['id'])
                ->where('auth_method', 'telegram')
                ->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'uuid' => Str::uuid(),
                    'username' => $telegramData['username'] ?? 'User_' . $telegramData['id'],
                    'telegram_id' => $telegramData['id'],
                    'telegram_username' => $telegramData['username'] ?? null,
                    'auth_method' => 'telegram',
                    'vip_level_id' => 1,
                    'status' => 'active',
                ]);

                // Create wallet
                $user->wallet()->create([
                    'real_balance' => 0,
                    'bonus_balance' => 0,
                    'locked_balance' => 0,
                ]);

                // Create initial seed
                Seed::create([
                    'user_id' => $user->id,
                    'is_active' => true,
                ]);

                // Award sign-up bonus
                $this->bonusService->awardSignUpBonus($user);

                $this->logAction('user_registered_telegram', $user, null, $user->toArray());
            }

            return $this->generateAuthResponse($user);
        });
    }

    /**
     * Create guest account
     */
    public function createGuestAccount(): array
    {
        return DB::transaction(function () {
            $user = User::create([
                'uuid' => Str::uuid(),
                'username' => 'Guest_' . Str::random(8),
                'auth_method' => 'guest',
                'vip_level_id' => 1,
                'status' => 'active',
            ]);

            // Create wallet
            $user->wallet()->create([
                'real_balance' => 0,
                'bonus_balance' => 0,
                'locked_balance' => 0,
            ]);

            // Create initial seed
            Seed::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            // Award sign-up bonus
            $this->bonusService->awardSignUpBonus($user);

            $this->logAction('guest_account_created', $user, null, $user->toArray());

            return $this->generateAuthResponse($user);
        });
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken(): array
    {
        $newToken = JWTAuth::refresh();
        
        return [
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        JWTAuth::invalidate(JWTAuth::getToken());
    }

    /**
     * Generate authentication response
     */
    private function generateAuthResponse(User $user): array
    {
        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        $this->logAction('user_login', $user);

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user->load('vipLevel', 'wallet'),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }

    /**
     * Log authentication actions
     */
    private function logAction(string $action, User $user, ?array $oldValues = null, ?array $newValues = null): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
