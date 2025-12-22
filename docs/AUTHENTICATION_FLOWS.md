# Authentication Flows - Secure Online Casino Platform

## Overview

This document details all authentication flows supported by the platform, including security measures, implementation details, and sample code.

---

## Supported Authentication Methods

1. **Phone + Password** (Primary)
2. **MetaMask (Web3)**
3. **Telegram OAuth**
4. **Guest Access**

---

## 1. Phone + Password Authentication

### 1.1 Registration Flow

```
┌──────────────────────────────────────────────────────────────┐
│                    USER REGISTRATION                          │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 1: User submits:                                        │
│  • Phone number (with country code)                           │
│  • Password (min 8 chars, mixed case, numbers, special)      │
│  • CAPTCHA token                                              │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 2: Server Validation                                    │
│  • Validate CAPTCHA                                           │
│  • Check rate limiting (5 attempts per IP per hour)           │
│  • Validate phone format                                      │
│  • Check phone uniqueness                                     │
│  • Validate password strength                                 │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 3: Password Hashing                                     │
│  • Hash password using Argon2id                               │
│  • Salt is automatically generated and stored with hash       │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 4: Create User & Wallet                                 │
│  • INSERT INTO users (transaction)                            │
│  • INSERT INTO wallets (transaction)                          │
│  • Generate UUID                                              │
│  • Set is_guest = false                                       │
│  • Set VIP level = Bronze                                     │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 5: Send OTP (Optional)                                  │
│  • Generate 6-digit OTP                                       │
│  • Store in Redis (expires in 10 minutes)                     │
│  • Send via SMS provider                                      │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 6: Generate JWT Tokens                                  │
│  • Access Token (expires: 15 minutes)                         │
│  • Refresh Token (expires: 7 days)                            │
│  • Store refresh token in Redis                               │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 7: Create Session                                       │
│  • Store session in Redis                                     │
│  • Store IP address, user agent                               │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 8: Create Audit Log                                     │
│  • Log registration event                                     │
│  • Store IP, user agent, timestamp                            │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Response: Return to client                                   │
│  {                                                            │
│    "success": true,                                           │
│    "user": {...},                                             │
│    "access_token": "...",                                     │
│    "refresh_token": "...",                                    │
│    "token_type": "Bearer",                                    │
│    "expires_in": 900                                          │
│  }                                                            │
└──────────────────────────────────────────────────────────────┘
```

### 1.2 Login Flow

```
User → Enter Phone + Password + CAPTCHA
  ↓
Server: Validate CAPTCHA
  ↓
Server: Check rate limiting (10 attempts per IP per hour)
  ↓
Server: Query user by phone number
  ↓
Server: Verify password (Argon2)
  ↓
[If failed] Increment failed attempts counter
  ↓
[If locked] Return "Account temporarily locked" error
  ↓
[If success] Reset failed attempts counter
  ↓
[Optional] Send & verify OTP
  ↓
Server: Check account status (active/suspended/banned)
  ↓
Server: Generate JWT tokens (access + refresh)
  ↓
Server: Create session in Redis
  ↓
Server: Update last_login_at, last_login_ip
  ↓
Server: Create audit log entry
  ↓
Response: Return tokens + user data
```

### 1.3 Sample Code (Laravel)

#### Registration Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->registerWithPhone(
                phoneNumber: $request->input('phone_number'),
                password: $request->input('password'),
                captchaToken: $request->input('captcha_token'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
```

#### Auth Service

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private CaptchaService $captchaService,
        private RateLimitService $rateLimitService,
        private AuditLogService $auditLogService
    ) {}

    public function registerWithPhone(
        string $phoneNumber,
        string $password,
        string $captchaToken,
        string $ipAddress,
        string $userAgent
    ): array {
        // 1. Validate CAPTCHA
        if (!$this->captchaService->verify($captchaToken)) {
            throw new \Exception('Invalid CAPTCHA');
        }

        // 2. Check rate limiting
        if (!$this->rateLimitService->check('register', $ipAddress, 5, 3600)) {
            throw new \Exception('Too many registration attempts');
        }

        // 3. Check if phone exists
        if ($this->userRepository->phoneExists($phoneNumber)) {
            throw new \Exception('Phone number already registered');
        }

        // 4. Create user and wallet in transaction
        $user = DB::transaction(function () use ($phoneNumber, $password) {
            // Create user
            $user = User::create([
                'uuid' => Str::uuid(),
                'phone_number' => $phoneNumber,
                'password_hash' => Hash::make($password),
                'is_guest' => false,
                'is_phone_verified' => false,
                'vip_level_id' => 1, // Bronze
                'status' => 'active'
            ]);

            // Create wallet
            Wallet::create([
                'user_id' => $user->id,
                'real_balance' => 0,
                'bonus_balance' => 0,
                'locked_balance' => 0
            ]);

            return $user;
        });

        // 5. Generate JWT tokens
        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = Str::random(64);

        // 6. Store refresh token in Redis
        \Cache::put(
            "refresh_token:{$user->id}:{$refreshToken}",
            true,
            now()->addDays(7)
        );

        // 7. Create audit log
        $this->auditLogService->log(
            action: 'user_registered',
            userId: $user->id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            metadata: ['method' => 'phone']
        );

        return [
            'user' => $user->load('wallet', 'vipLevel'),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 900 // 15 minutes
        ];
    }
}
```

---

## 2. MetaMask (Web3) Authentication

### 2.1 Authentication Flow

```
┌──────────────────────────────────────────────────────────────┐
│                 METAMASK AUTHENTICATION                       │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 1: User clicks "Connect with MetaMask"                  │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 2: Frontend requests wallet connection                  │
│  window.ethereum.request({ method: 'eth_requestAccounts' })  │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 3: User approves in MetaMask                            │
│  Receives wallet address                                      │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 4: Frontend requests nonce from backend                 │
│  POST /api/auth/metamask/nonce                                │
│  { "wallet_address": "0x..." }                                │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 5: Backend generates & returns nonce                    │
│  • Generate random nonce (UUID)                               │
│  • Store: wallet_address → nonce (Redis, 10 min expiry)      │
│  • Return nonce to frontend                                   │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 6: Frontend requests signature                          │
│  Message: "Sign this message to authenticate with Casino      │
│            Nonce: {nonce}"                                    │
│  window.ethereum.request({                                    │
│    method: 'personal_sign',                                   │
│    params: [message, walletAddress]                           │
│  })                                                           │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 7: User signs message in MetaMask                       │
│  Receives signature                                           │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 8: Frontend sends to backend                            │
│  POST /api/auth/metamask/verify                               │
│  {                                                            │
│    "wallet_address": "0x...",                                 │
│    "signature": "0x...",                                      │
│    "nonce": "..."                                             │
│  }                                                            │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 9: Backend verifies signature                           │
│  • Retrieve nonce from Redis                                  │
│  • Reconstruct message                                        │
│  • Verify signature using ecrecover                           │
│  • Confirm recovered address === wallet_address              │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 10: Check if wallet exists in database                  │
│  [If new] Create user account                                 │
│  [If exists] Retrieve user                                    │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 11: Generate JWT tokens                                 │
│  • Access Token (15 minutes)                                  │
│  • Refresh Token (7 days)                                     │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Step 12: Create session & audit log                          │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Response: Return tokens + user data                          │
└──────────────────────────────────────────────────────────────┘
```

### 2.2 Sample Code

#### Backend (Laravel)

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Elliptic\EC;
use kornrunner\Keccak;

class Web3AuthService
{
    public function generateNonce(string $walletAddress): string
    {
        $nonce = Str::uuid()->toString();
        
        \Cache::put(
            "metamask_nonce:{$walletAddress}",
            $nonce,
            now()->addMinutes(10)
        );
        
        return $nonce;
    }

    public function verifySignature(
        string $walletAddress,
        string $signature,
        string $nonce
    ): User {
        // 1. Retrieve stored nonce
        $storedNonce = \Cache::get("metamask_nonce:{$walletAddress}");
        
        if (!$storedNonce || $storedNonce !== $nonce) {
            throw new \Exception('Invalid or expired nonce');
        }

        // 2. Reconstruct message
        $message = "Sign this message to authenticate with Casino\nNonce: {$nonce}";
        
        // 3. Verify signature
        $recoveredAddress = $this->recoverAddress($message, $signature);
        
        if (strtolower($recoveredAddress) !== strtolower($walletAddress)) {
            throw new \Exception('Signature verification failed');
        }

        // 4. Delete used nonce
        \Cache::forget("metamask_nonce:{$walletAddress}");

        // 5. Find or create user
        $user = DB::transaction(function () use ($walletAddress) {
            $user = User::where('wallet_address', $walletAddress)->first();
            
            if (!$user) {
                $user = User::create([
                    'uuid' => Str::uuid(),
                    'wallet_address' => $walletAddress,
                    'is_guest' => false,
                    'vip_level_id' => 1,
                    'status' => 'active'
                ]);
                
                Wallet::create([
                    'user_id' => $user->id,
                    'real_balance' => 0,
                    'bonus_balance' => 0,
                    'locked_balance' => 0
                ]);
            }
            
            return $user;
        });

        return $user;
    }

    private function recoverAddress(string $message, string $signature): string
    {
        // Ethereum signed message prefix
        $hash = Keccak::hash(
            sprintf("\x19Ethereum Signed Message:\n%d%s", strlen($message), $message),
            256
        );
        
        // Parse signature
        $r = substr($signature, 2, 64);
        $s = substr($signature, 66, 64);
        $v = hexdec(substr($signature, 130, 2));
        
        if ($v < 27) {
            $v += 27;
        }
        
        // Recover public key
        $ec = new EC('secp256k1');
        $pubkey = $ec->recoverPubKey($hash, [
            'r' => $r,
            's' => $s
        ], $v - 27);
        
        // Get address from public key
        $publicKey = $pubkey->encode('hex');
        $address = '0x' . substr(Keccak::hash(substr(hex2bin($publicKey), 1), 256), 24);
        
        return $address;
    }
}
```

#### Frontend (JavaScript)

```javascript
// MetaMask Authentication
async function authenticateWithMetaMask() {
    try {
        // 1. Check if MetaMask is installed
        if (!window.ethereum) {
            throw new Error('MetaMask is not installed');
        }

        // 2. Request account access
        const accounts = await window.ethereum.request({
            method: 'eth_requestAccounts'
        });
        const walletAddress = accounts[0];

        // 3. Request nonce from backend
        const nonceResponse = await fetch('/api/auth/metamask/nonce', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet_address: walletAddress })
        });
        const { nonce } = await nonceResponse.json();

        // 4. Create message
        const message = `Sign this message to authenticate with Casino\nNonce: ${nonce}`;

        // 5. Request signature
        const signature = await window.ethereum.request({
            method: 'personal_sign',
            params: [message, walletAddress]
        });

        // 6. Verify signature with backend
        const authResponse = await fetch('/api/auth/metamask/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                wallet_address: walletAddress,
                signature: signature,
                nonce: nonce
            })
        });

        const authData = await authResponse.json();

        // 7. Store tokens
        localStorage.setItem('access_token', authData.access_token);
        localStorage.setItem('refresh_token', authData.refresh_token);

        return authData;

    } catch (error) {
        console.error('MetaMask authentication failed:', error);
        throw error;
    }
}
```

---

## 3. Telegram Authentication

### 3.1 Authentication Flow

```
User → Click "Login with Telegram" button
  ↓
Frontend → Show Telegram Login Widget
  ↓
User → Authenticate in Telegram
  ↓
Telegram → Return auth data to frontend
  ↓
Frontend → Send auth data to backend
  ↓
Backend → Verify Telegram data hash
  ↓
Backend → Check if Telegram ID exists
  ↓
[If new] Create user account
  ↓
Backend → Generate JWT tokens
  ↓
Backend → Create session & audit log
  ↓
Response → Return tokens + user data
```

### 3.2 Sample Code

#### Backend (Laravel)

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramAuthService
{
    private string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
    }

    public function authenticate(array $telegramData): User
    {
        // 1. Verify data authenticity
        if (!$this->verifyTelegramAuth($telegramData)) {
            throw new \Exception('Invalid Telegram authentication data');
        }

        // 2. Check data freshness (within 24 hours)
        if (time() - $telegramData['auth_date'] > 86400) {
            throw new \Exception('Authentication data expired');
        }

        // 3. Find or create user
        $user = DB::transaction(function () use ($telegramData) {
            $user = User::where('telegram_id', $telegramData['id'])->first();
            
            if (!$user) {
                $user = User::create([
                    'uuid' => Str::uuid(),
                    'telegram_id' => $telegramData['id'],
                    'telegram_username' => $telegramData['username'] ?? null,
                    'display_name' => $telegramData['first_name'] . ' ' . ($telegramData['last_name'] ?? ''),
                    'avatar_url' => $telegramData['photo_url'] ?? null,
                    'is_guest' => false,
                    'vip_level_id' => 1,
                    'status' => 'active'
                ]);
                
                Wallet::create([
                    'user_id' => $user->id,
                    'real_balance' => 0,
                    'bonus_balance' => 0,
                    'locked_balance' => 0
                ]);
            }
            
            return $user;
        });

        return $user;
    }

    private function verifyTelegramAuth(array $authData): bool
    {
        $checkHash = $authData['hash'] ?? '';
        unset($authData['hash']);

        $dataCheckArr = [];
        foreach ($authData as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        sort($dataCheckArr);

        $dataCheckString = implode("\n", $dataCheckArr);
        $secretKey = hash('sha256', $this->botToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($hash, $checkHash);
    }
}
```

#### Frontend (HTML + JavaScript)

```html
<!-- Telegram Login Widget -->
<script async src="https://telegram.org/js/telegram-widget.js?22"
        data-telegram-login="YOUR_BOT_USERNAME"
        data-size="large"
        data-onauth="onTelegramAuth(user)"
        data-request-access="write">
</script>

<script>
async function onTelegramAuth(user) {
    try {
        const response = await fetch('/api/auth/telegram/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(user)
        });

        const authData = await response.json();

        // Store tokens
        localStorage.setItem('access_token', authData.access_token);
        localStorage.setItem('refresh_token', authData.refresh_token);

        // Redirect to dashboard
        window.location.href = '/dashboard';

    } catch (error) {
        console.error('Telegram authentication failed:', error);
        alert('Authentication failed. Please try again.');
    }
}
</script>
```

---

## 4. Guest Authentication

### 4.1 Guest Flow

```
User → Click "Play as Guest"
  ↓
Backend → Generate unique guest ID (UUID)
  ↓
Backend → Create guest user record
  ↓
Backend → Create wallet (balance: 0)
  ↓
Backend → Generate JWT tokens
  ↓
Backend → Create session
  ↓
Response → Return tokens + user data
  ↓
[User plays games, deposits, etc.]
  ↓
[User attempts withdrawal]
  ↓
Backend → Check if guest account
  ↓
[If guest] Require upgrade (phone + password)
  ↓
Backend → Convert guest to regular user
  ↓
Backend → Preserve wallet balance & history
  ↓
Backend → Enable withdrawal
```

### 4.2 Sample Code

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestAuthService
{
    public function createGuestAccount(string $ipAddress, string $userAgent): User
    {
        $user = DB::transaction(function () {
            $user = User::create([
                'uuid' => Str::uuid(),
                'is_guest' => true,
                'vip_level_id' => 1,
                'status' => 'active'
            ]);
            
            Wallet::create([
                'user_id' => $user->id,
                'real_balance' => 0,
                'bonus_balance' => 0,
                'locked_balance' => 0
            ]);
            
            return $user;
        });

        return $user;
    }

    public function upgradeGuestAccount(
        User $guestUser,
        string $phoneNumber,
        string $password
    ): User {
        if (!$guestUser->is_guest) {
            throw new \Exception('User is not a guest account');
        }

        // Check if phone already exists
        if (User::where('phone_number', $phoneNumber)->exists()) {
            throw new \Exception('Phone number already registered');
        }

        // Upgrade guest account
        $guestUser->update([
            'phone_number' => $phoneNumber,
            'password_hash' => \Hash::make($password),
            'is_guest' => false,
            'is_phone_verified' => false
        ]);

        return $guestUser->fresh();
    }
}
```

---

## 5. Token Refresh Flow

```
Client → Access token expired
  ↓
Client → Send refresh token to /api/auth/refresh
  ↓
Backend → Validate refresh token
  ↓
Backend → Check if token in Redis
  ↓
Backend → Check if user still active
  ↓
Backend → Generate new access token
  ↓
Backend → Rotate refresh token (optional)
  ↓
Backend → Store new refresh token in Redis
  ↓
Backend → Revoke old refresh token
  ↓
Response → Return new tokens
```

---

## 6. Security Measures

### 6.1 Rate Limiting

```php
// Registration: 5 attempts per hour per IP
// Login: 10 attempts per hour per IP
// Password reset: 3 attempts per hour per IP
// MetaMask nonce: 10 requests per hour per IP
```

### 6.2 Token Security

- **Access Token**: Short-lived (15 minutes)
- **Refresh Token**: Long-lived (7 days), stored in Redis
- **Token Rotation**: Refresh tokens are rotated on use
- **Token Revocation**: Logout revokes all tokens

### 6.3 Password Requirements

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

### 6.4 Brute Force Protection

- Lock account after 5 failed login attempts
- Lockout duration: 30 minutes
- Send notification email on account lockout

---

**Last Updated**: December 21, 2025
