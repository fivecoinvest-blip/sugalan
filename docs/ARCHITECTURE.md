# System Architecture - Secure Online Casino Platform

## Overview
This document outlines the technical architecture for a secure, scalable online casino platform with multi-authentication support, provably fair games, and manual payment processing.

---

## 1. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENT LAYER                             │
├─────────────────────────────────────────────────────────────────┤
│  Web App (Vue.js/React) │ Mobile Web │ Admin Dashboard         │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      API GATEWAY LAYER                           │
├─────────────────────────────────────────────────────────────────┤
│  Load Balancer │ Rate Limiter │ WAF │ HTTPS/TLS                │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER (Laravel)                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────┐  ┌──────────────┐  ┌─────────────────┐       │
│  │   Auth      │  │   Wallet     │  │   Game Engine   │       │
│  │   Service   │  │   Service    │  │   Service       │       │
│  └─────────────┘  └──────────────┘  └─────────────────┘       │
│                                                                   │
│  ┌─────────────┐  ┌──────────────┐  ┌─────────────────┐       │
│  │   Payment   │  │   VIP        │  │   Bonus         │       │
│  │   Service   │  │   Service    │  │   Service       │       │
│  └─────────────┘  └──────────────┘  └─────────────────┘       │
│                                                                   │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DATA LAYER                                  │
├─────────────────────────────────────────────────────────────────┤
│  MySQL/PostgreSQL │ Redis Cache │ Queue Workers                │
└─────────────────────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   EXTERNAL SERVICES                              │
├─────────────────────────────────────────────────────────────────┤
│  SMS Provider │ MetaMask │ Telegram API │ Third-Party Games    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Application Architecture Pattern

**Pattern**: Service-Oriented Architecture with Repository Pattern

```
Controllers (HTTP Layer)
    ↓
Services (Business Logic)
    ↓
Repositories (Data Access)
    ↓
Models (ORM/Database)
```

### Benefits:
- **Separation of Concerns**: Each layer has a specific responsibility
- **Testability**: Easy to mock and test individual components
- **Maintainability**: Changes isolated to specific layers
- **Scalability**: Services can be extracted to microservices if needed

---

## 3. Authentication Architecture

### 3.1 Multi-Method Authentication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    AUTHENTICATION METHODS                        │
├──────────────┬──────────────┬──────────────┬──────────────────┤
│ Phone+Pass   │  MetaMask    │  Telegram    │    Guest         │
└──────┬───────┴──────┬───────┴──────┬───────┴─────┬────────────┘
       │              │              │             │
       ▼              ▼              ▼             ▼
┌─────────────────────────────────────────────────────────────────┐
│              AUTHENTICATION CONTROLLER                           │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              AUTHENTICATION SERVICE                              │
│  ┌──────────┐  ┌───────────┐  ┌──────────┐  ┌──────────┐      │
│  │  Phone   │  │  Web3     │  │ Telegram │  │  Guest   │      │
│  │  Auth    │  │  Auth     │  │  Auth    │  │  Auth    │      │
│  └──────────┘  └───────────┘  └──────────┘  └──────────┘      │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    JWT TOKEN GENERATION                          │
│  • Access Token (15min)                                          │
│  • Refresh Token (7 days)                                        │
│  • Token Rotation on Refresh                                     │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SESSION & CACHE                               │
│  Redis: User sessions, active tokens, rate limiting              │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Phone Authentication Flow

```
User → Enter Phone + Password
  ↓
Validate Input (CAPTCHA check)
  ↓
Check Rate Limiting
  ↓
Query User Table (phone number)
  ↓
Verify Password (Argon2)
  ↓
[Optional] Send OTP SMS
  ↓
[Optional] Verify OTP
  ↓
Generate JWT Tokens
  ↓
Store Session in Redis
  ↓
Return Tokens + User Data
```

### 3.3 MetaMask Authentication Flow

```
User → Connect MetaMask
  ↓
Request Wallet Address
  ↓
Generate Nonce (stored in DB)
  ↓
Request Signature (message with nonce)
  ↓
Verify Signature (SIWE/EIP-4361)
  ↓
Check if Wallet Exists in DB
  ↓
[If New] Create User Record
  ↓
[Optional] Link to Phone Account
  ↓
Generate JWT Tokens
  ↓
Store Session in Redis
  ↓
Return Tokens + User Data
```

### 3.4 Guest Authentication Flow

```
User → Click "Play as Guest"
  ↓
Generate Unique Guest ID (UUID)
  ↓
Create Guest User Record
  ↓
Set Guest Flag = true
  ↓
Generate JWT Tokens
  ↓
Store Session in Redis
  ↓
Return Tokens + User Data
  ↓
[Before Withdrawal]
  ↓
Require Guest Upgrade (Phone + Password)
```

---

## 4. Wallet & Transaction Architecture

### 4.1 Dual Balance System

```
┌─────────────────────────────────────────────────────────────────┐
│                      USER WALLET                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────────┐    ┌─────────────────────┐            │
│  │   REAL BALANCE      │    │   BONUS BALANCE     │            │
│  │                     │    │                     │            │
│  │  • Deposits         │    │  • Sign-up Bonus    │            │
│  │  • Winnings         │    │  • Reload Bonus     │            │
│  │  • Withdrawable     │    │  • Promotional      │            │
│  │                     │    │  • Has Wagering Req │            │
│  └─────────────────────┘    └─────────────────────┘            │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Transaction Flow Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   TRANSACTION INITIATION                         │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              PRE-TRANSACTION VALIDATION                          │
│  • Sufficient Balance Check                                      │
│  • Account Status Check (active, not suspended)                  │
│  • Withdrawal: Wagering Complete Check                           │
│  • Rate Limiting Check                                           │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              DATABASE TRANSACTION (BEGIN)                        │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              BALANCE LOCKING                                     │
│  • SELECT ... FOR UPDATE (pessimistic lock)                      │
│  • Lock user wallet row                                          │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              BALANCE UPDATE                                      │
│  • Debit/Credit Operation                                        │
│  • Maintain Balance Integrity                                    │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              TRANSACTION RECORD CREATION                         │
│  • Create immutable transaction log                              │
│  • Store all metadata (type, amount, status, etc.)              │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              AUDIT LOG CREATION                                  │
│  • Log operation details                                         │
│  • Store IP, user agent, timestamp                               │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              DATABASE TRANSACTION (COMMIT)                       │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              POST-TRANSACTION ACTIONS                            │
│  • Send notifications                                            │
│  • Update cache                                                  │
│  • Dispatch events                                               │
└─────────────────────────────────────────────────────────────────┘
```

### 4.3 Atomic Transaction Implementation

```php
DB::transaction(function () use ($userId, $amount, $type) {
    // Lock user wallet
    $wallet = Wallet::where('user_id', $userId)
        ->lockForUpdate()
        ->first();
    
    // Validate balance
    if ($type === 'debit' && $wallet->real_balance < $amount) {
        throw new InsufficientBalanceException();
    }
    
    // Update balance
    if ($type === 'debit') {
        $wallet->decrement('real_balance', $amount);
    } else {
        $wallet->increment('real_balance', $amount);
    }
    
    // Create transaction record
    Transaction::create([
        'user_id' => $userId,
        'type' => $type,
        'amount' => $amount,
        'balance_before' => $wallet->getOriginal('real_balance'),
        'balance_after' => $wallet->real_balance,
        'status' => 'completed',
        'metadata' => json_encode([...])
    ]);
    
    // Create audit log
    AuditLog::create([...]);
});
```

---

## 5. Payment System Architecture (Manual GCash)

### 5.1 Deposit Flow

```
USER                    SYSTEM                  ADMIN
  │                       │                       │
  │ 1. Request Deposit    │                       │
  ├──────────────────────>│                       │
  │                       │                       │
  │ 2. Display GCash #    │                       │
  │<──────────────────────┤                       │
  │                       │                       │
  │ 3. Send Money via     │                       │
  │    GCash App          │                       │
  │                       │                       │
  │ 4. Submit Reference + │                       │
  │    Screenshot         │                       │
  ├──────────────────────>│                       │
  │                       │                       │
  │                       │ 5. Create Pending     │
  │                       │    Deposit Record     │
  │                       │                       │
  │ 5. Confirmation       │                       │
  │<──────────────────────┤                       │
  │                       │                       │
  │                       │ 6. Notification       │
  │                       ├──────────────────────>│
  │                       │                       │
  │                       │ 7. Manual Verification│
  │                       │<──────────────────────┤
  │                       │                       │
  │                       │ 8. Approve/Reject     │
  │                       │<──────────────────────┤
  │                       │                       │
  │                       │ 9. Update Wallet      │
  │                       │    (if approved)      │
  │                       │                       │
  │ 10. Balance Updated   │                       │
  │     Notification      │                       │
  │<──────────────────────┤                       │
```

### 5.2 Withdrawal Flow

```
USER                    SYSTEM                  ADMIN
  │                       │                       │
  │ 1. Request Withdrawal │                       │
  ├──────────────────────>│                       │
  │                       │                       │
  │                       │ 2. Pre-Validation     │
  │                       │   • Wagering Check    │
  │                       │   • VIP Limits Check  │
  │                       │   • Phone Verified    │
  │                       │   • Guest Check       │
  │                       │                       │
  │ 2. Validation Result  │                       │
  │<──────────────────────┤                       │
  │                       │                       │
  │ 3. Confirm Withdrawal │                       │
  │    (GCash Number)     │                       │
  ├──────────────────────>│                       │
  │                       │                       │
  │                       │ 4. Lock Funds         │
  │                       │    Create Pending     │
  │                       │    Withdrawal         │
  │                       │                       │
  │ 4. Confirmation       │                       │
  │<──────────────────────┤                       │
  │                       │                       │
  │                       │ 5. Notification       │
  │                       ├──────────────────────>│
  │                       │                       │
  │                       │ 6. Manual Review      │
  │                       │<──────────────────────┤
  │                       │                       │
  │                       │ 7. Send via GCash     │
  │                       │<──────────────────────┤
  │                       │                       │
  │                       │ 8. Confirm Payment    │
  │                       │<──────────────────────┤
  │                       │                       │
  │                       │ 9. Update Status      │
  │                       │    Unlock/Deduct      │
  │                       │                       │
  │ 10. Completed         │                       │
  │     Notification      │                       │
  │<──────────────────────┤                       │
```

---

## 6. VIP & Bonus Architecture

### 6.1 VIP Tier Progression System

```
┌─────────────────────────────────────────────────────────────────┐
│                     VIP TIER CALCULATOR                          │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   CALCULATION INPUTS                             │
│  • Total Wagered Amount                                          │
│  • Activity Score (login frequency, bets placed)                 │
│  • Net Loss/Profit                                               │
│  • Time Period (monthly/quarterly)                               │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   TIER DETERMINATION                             │
│                                                                   │
│  Bronze:    $0 - $999 wagered                                    │
│  Silver:    $1,000 - $4,999 wagered                              │
│  Gold:      $5,000 - $19,999 wagered                             │
│  Platinum:  $20,000 - $99,999 wagered                            │
│  Diamond:   $100,000+ wagered                                    │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   BENEFIT APPLICATION                            │
│  • Bonus multipliers                                             │
│  • Wagering requirement reductions                               │
│  • Withdrawal limits                                             │
│  • Cashback percentages                                          │
│  • Processing speed                                              │
└─────────────────────────────────────────────────────────────────┘
```

### 6.2 Bonus Wagering System

```
┌─────────────────────────────────────────────────────────────────┐
│                   BONUS ACTIVATION                               │
│  Bonus Amount: $100                                              │
│  Wagering Requirement: 30x                                       │
│  Required Wagering: $3,000                                       │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   BET PLACEMENT                                  │
│  User places bet: $10 on Dice (100% contribution)                │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   WAGERING CALCULATION                           │
│  Wagering Progress: $10 × 100% = $10                            │
│  Total Wagered: $10 / $3,000 = 0.33%                            │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   COMPLETION CHECK                               │
│  IF Total Wagered >= Required Wagering                           │
│    → Convert Bonus Balance to Real Balance                       │
│    → Enable Withdrawals                                          │
│  ELSE                                                            │
│    → Continue Wagering                                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## 7. Provably Fair Game Architecture

### 7.1 Seed Generation & Storage

```
┌─────────────────────────────────────────────────────────────────┐
│                   GAME INITIALIZATION                            │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              SERVER SEED GENERATION                              │
│  • Generate random 256-bit string                                │
│  • Hash with SHA-256                                             │
│  • Store hash publicly (show to user)                            │
│  • Store actual seed securely (hidden until reveal)              │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              CLIENT SEED INPUT                                   │
│  • User can provide custom client seed                           │
│  • Or use randomly generated default                             │
│  • Store in database                                             │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              NONCE TRACKING                                      │
│  • Initialize nonce = 0 for new seed pair                        │
│  • Increment nonce for each bet                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 7.2 Result Generation (Provably Fair)

```
┌─────────────────────────────────────────────────────────────────┐
│                   BET PLACEMENT                                  │
│  User places bet with:                                           │
│  • Server seed (hashed)                                          │
│  • Client seed                                                   │
│  • Nonce                                                         │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              RESULT CALCULATION                                  │
│                                                                   │
│  result = HMAC-SHA256(                                           │
│    key: server_seed,                                             │
│    message: client_seed + ":" + nonce                            │
│  )                                                               │
│                                                                   │
│  // Convert hash to game-specific outcome                        │
│  // Example: Dice (0-100)                                        │
│  dice_result = (parseInt(result.substring(0, 8), 16) % 10001)   │
│               / 100                                              │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              RESULT STORAGE                                      │
│  • Store bet details                                             │
│  • Store result                                                  │
│  • Store server seed hash (not actual seed yet)                  │
│  • Store client seed                                             │
│  • Store nonce                                                   │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│              SEED REVEAL (After Game Ends)                       │
│  • Reveal actual server seed                                     │
│  • User can verify result independently                          │
│  • Compare hash(revealed_seed) === stored_hash                   │
└─────────────────────────────────────────────────────────────────┘
```

### 7.3 Verification Process

```
User Verification Steps:
1. Get server seed (revealed after bet)
2. Verify hash(server_seed) === server_seed_hash
3. Calculate HMAC-SHA256(server_seed, client_seed + ":" + nonce)
4. Convert hash to game result using game algorithm
5. Compare calculated result === displayed result

✅ If match: Game is provably fair
❌ If no match: Report to admin/support
```

---

## 8. Security Architecture

### 8.1 Security Layers

```
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 1: Network Security                                       │
│  • HTTPS/TLS 1.3                                                 │
│  • DDoS Protection (Cloudflare/AWS Shield)                       │
│  • WAF (Web Application Firewall)                                │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 2: Application Security                                   │
│  • Rate Limiting (per IP, per user)                              │
│  • CAPTCHA (Google reCAPTCHA)                                    │
│  • Input Validation & Sanitization                               │
│  • CSRF Protection                                               │
│  • XSS Protection                                                │
│  • SQL Injection Prevention (ORM)                                │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 3: Authentication Security                                │
│  • Password Hashing (Argon2)                                     │
│  • JWT Token Expiration                                          │
│  • Token Rotation                                                │
│  • Session Management                                            │
│  • IP Whitelisting (Admin)                                       │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 4: Data Security                                          │
│  • Encryption at Rest (Database)                                 │
│  • Encryption in Transit (HTTPS)                                 │
│  • Sensitive Data Masking                                        │
│  • Audit Logging                                                 │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 5: Transaction Security                                   │
│  • Database Transactions (ACID)                                  │
│  • Pessimistic Locking                                           │
│  • Idempotency Keys                                              │
│  • Immutable Transaction Logs                                    │
└─────────────────────────────────────────────────────────────────┘
```

### 8.2 Admin Panel Security

```
┌─────────────────────────────────────────────────────────────────┐
│                   ADMIN ACCESS CONTROL                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  1. IP Whitelisting                                              │
│     • Only allowed IPs can access admin routes                   │
│     • Configurable whitelist in database                         │
│                                                                   │
│  2. Strong Authentication                                        │
│     • Admin accounts with strong password policy                 │
│     • 2FA mandatory for admin accounts                           │
│     • Session timeout (15 minutes idle)                          │
│                                                                   │
│  3. Role-Based Permissions                                       │
│     • Admin: Full access                                         │
│     • Finance: Payment approval only                             │
│     • Support: View user data, limited actions                   │
│                                                                   │
│  4. Action Logging                                               │
│     • Every admin action logged                                  │
│     • IP, timestamp, action details                              │
│     • Immutable audit trail                                      │
│                                                                   │
│  5. Sensitive Action Confirmation                                │
│     • Password re-entry for critical actions                     │
│     • Withdrawal approval requires confirmation                  │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 9. Database Architecture

### 9.1 Core Tables Structure

```
users
├── id (PK)
├── phone_number (UNIQUE, INDEXED)
├── password_hash
├── is_guest (BOOLEAN)
├── is_phone_verified
├── wallet_address (NULLABLE)
├── telegram_id (NULLABLE)
├── status (active, suspended, banned)
├── vip_level (FK)
├── created_at
└── updated_at

wallets
├── id (PK)
├── user_id (FK, UNIQUE)
├── real_balance (DECIMAL)
├── bonus_balance (DECIMAL)
├── locked_balance (DECIMAL)
├── created_at
└── updated_at

transactions
├── id (PK, UUID)
├── user_id (FK, INDEXED)
├── type (deposit, withdrawal, bet, win, bonus, etc.)
├── amount (DECIMAL)
├── balance_before (DECIMAL)
├── balance_after (DECIMAL)
├── status (pending, completed, failed, cancelled)
├── metadata (JSON)
├── created_at
└── updated_at

deposits
├── id (PK)
├── user_id (FK, INDEXED)
├── amount (DECIMAL)
├── payment_method (gcash)
├── reference_number
├── screenshot_url
├── status (pending, approved, rejected)
├── admin_id (FK, NULLABLE)
├── admin_notes (TEXT)
├── created_at
└── updated_at

withdrawals
├── id (PK)
├── user_id (FK, INDEXED)
├── amount (DECIMAL)
├── gcash_number
├── status (pending, processing, completed, rejected)
├── admin_id (FK, NULLABLE)
├── admin_notes (TEXT)
├── created_at
└── updated_at

bets
├── id (PK, UUID)
├── user_id (FK, INDEXED)
├── game_type (dice, hilo, mines, etc.)
├── bet_amount (DECIMAL)
├── payout (DECIMAL)
├── result (JSON)
├── server_seed_hash
├── client_seed
├── nonce
├── created_at
└── updated_at

seeds
├── id (PK)
├── user_id (FK, INDEXED)
├── server_seed
├── server_seed_hash
├── client_seed
├── nonce
├── is_active (BOOLEAN)
├── revealed_at (NULLABLE)
├── created_at
└── updated_at

vip_levels
├── id (PK)
├── name (bronze, silver, gold, etc.)
├── min_wagered_amount (DECIMAL)
├── bonus_multiplier (DECIMAL)
├── wagering_reduction (DECIMAL)
├── cashback_percentage (DECIMAL)
├── withdrawal_limit_daily (DECIMAL)
├── withdrawal_limit_monthly (DECIMAL)
├── created_at
└── updated_at

bonuses
├── id (PK)
├── user_id (FK, INDEXED)
├── type (signup, reload, promotional, referral)
├── amount (DECIMAL)
├── wagering_requirement (DECIMAL)
├── wagering_progress (DECIMAL)
├── status (active, completed, expired, forfeited)
├── expires_at
├── created_at
└── updated_at

audit_logs
├── id (PK)
├── user_id (FK, INDEXED, NULLABLE)
├── admin_id (FK, INDEXED, NULLABLE)
├── action (login, deposit_approval, withdrawal, etc.)
├── ip_address
├── user_agent
├── metadata (JSON)
├── created_at
└── (no updated_at - immutable)
```

### 9.2 Database Indexes

```sql
-- High-frequency queries optimization
CREATE INDEX idx_users_phone ON users(phone_number);
CREATE INDEX idx_users_wallet_address ON users(wallet_address);
CREATE INDEX idx_transactions_user_created ON transactions(user_id, created_at);
CREATE INDEX idx_bets_user_created ON bets(user_id, created_at);
CREATE INDEX idx_deposits_status ON deposits(status);
CREATE INDEX idx_withdrawals_status ON withdrawals(status);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
```

---

## 10. API Architecture

### 10.1 RESTful API Structure

```
Authentication Endpoints:
POST   /api/auth/register/phone
POST   /api/auth/login/phone
POST   /api/auth/login/metamask
POST   /api/auth/login/telegram
POST   /api/auth/login/guest
POST   /api/auth/logout
POST   /api/auth/refresh
POST   /api/auth/forgot-password
POST   /api/auth/reset-password

User Endpoints:
GET    /api/user/profile
PUT    /api/user/profile
POST   /api/user/upgrade-guest
GET    /api/user/wallet
GET    /api/user/transactions
GET    /api/user/bets
GET    /api/user/vip

Payment Endpoints:
POST   /api/payments/deposit/gcash
GET    /api/payments/deposits
POST   /api/payments/withdrawal/gcash
GET    /api/payments/withdrawals
GET    /api/payments/methods

Bonus Endpoints:
GET    /api/bonuses
POST   /api/bonuses/activate
GET    /api/bonuses/{id}/progress

Game Endpoints:
GET    /api/games
POST   /api/games/{game}/bet
GET    /api/games/{game}/history
GET    /api/games/{game}/verify
POST   /api/games/seeds/rotate

Admin Endpoints:
GET    /api/admin/dashboard
GET    /api/admin/users
GET    /api/admin/deposits/pending
POST   /api/admin/deposits/{id}/approve
POST   /api/admin/deposits/{id}/reject
GET    /api/admin/withdrawals/pending
POST   /api/admin/withdrawals/{id}/approve
POST   /api/admin/withdrawals/{id}/reject
GET    /api/admin/reports
```

---

## 11. Deployment Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      LOAD BALANCER                               │
│                    (Nginx / AWS ALB)                             │
└──────────────┬──────────────────────────────────────────────────┘
               │
     ┌─────────┴─────────┐
     │                   │
     ▼                   ▼
┌─────────┐         ┌─────────┐
│  App    │         │  App    │
│ Server  │         │ Server  │
│   #1    │         │   #2    │
└────┬────┘         └────┬────┘
     │                   │
     └─────────┬─────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DATABASE CLUSTER                            │
│              (Master-Slave Replication)                          │
├─────────────────────────────────────────────────────────────────┤
│  Master (Write)  │  Slave 1 (Read)  │  Slave 2 (Read)          │
└─────────────────────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      REDIS CLUSTER                               │
│            (Sessions, Cache, Queue)                              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 12. Monitoring & Logging Architecture

```
Application Logs → ELK Stack (Elasticsearch, Logstash, Kibana)
Performance Monitoring → New Relic / Datadog
Error Tracking → Sentry
Uptime Monitoring → Pingdom / UptimeRobot
Alerts → Slack / Email / SMS
```

---

## 13. Technology Stack Summary

| Layer | Technology |
|-------|-----------|
| **Backend Framework** | Laravel 11+ |
| **Database** | MySQL 8.0+ / PostgreSQL 14+ |
| **Cache & Queue** | Redis 6+ |
| **Web Server** | Nginx |
| **Authentication** | JWT (tymon/jwt-auth) |
| **Password Hashing** | Argon2 |
| **Frontend** | Vue.js 3 / React 18 (TBD) |
| **API Documentation** | Swagger / Postman |
| **Testing** | PHPUnit, Pest |
| **Deployment** | Docker, AWS/DigitalOcean |
| **CI/CD** | GitHub Actions / GitLab CI |
| **Monitoring** | New Relic, Sentry, ELK |

---

**Last Updated**: December 21, 2025
