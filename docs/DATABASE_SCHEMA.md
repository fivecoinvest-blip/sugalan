# Database Schema - Secure Online Casino Platform

## Schema Overview

This document defines the complete database schema for the casino platform, including all tables, relationships, indexes, and constraints.

---

## Entity Relationship Diagram (Text Format)

```
users (1) ──────< (M) wallets
users (1) ──────< (M) transactions
users (1) ──────< (M) deposits
users (1) ──────< (M) withdrawals
users (1) ──────< (M) bets
users (1) ──────< (M) seeds
users (1) ──────< (M) bonuses
users (M) ──────> (1) vip_levels
users (1) ──────< (M) referrals
users (1) ──────< (M) sessions
users (1) ──────< (M) audit_logs

admin_users (1) ──────< (M) deposits (approver)
admin_users (1) ──────< (M) withdrawals (approver)
admin_users (1) ──────< (M) audit_logs

payment_methods (1) ──────< (M) gcash_accounts
```

---

## Core Tables

### 1. users

Primary user table supporting multiple authentication methods.

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Authentication
    phone_number VARCHAR(20) UNIQUE NULL,
    email VARCHAR(255) UNIQUE NULL,
    password_hash VARCHAR(255) NULL,
    is_guest BOOLEAN DEFAULT FALSE,
    is_phone_verified BOOLEAN DEFAULT FALSE,
    is_email_verified BOOLEAN DEFAULT FALSE,
    
    -- Web3 Authentication
    wallet_address VARCHAR(42) UNIQUE NULL,
    wallet_nonce VARCHAR(255) NULL,
    
    -- Social Authentication
    telegram_id BIGINT UNIQUE NULL,
    telegram_username VARCHAR(255) NULL,
    
    -- Profile
    username VARCHAR(50) UNIQUE NULL,
    display_name VARCHAR(100) NULL,
    avatar_url VARCHAR(255) NULL,
    country_code VARCHAR(2) NULL,
    currency VARCHAR(3) DEFAULT 'PHP',
    
    -- VIP & Status
    vip_level_id BIGINT UNSIGNED NULL,
    status ENUM('active', 'suspended', 'banned', 'closed') DEFAULT 'active',
    
    -- Statistics
    total_deposited DECIMAL(20, 2) DEFAULT 0.00,
    total_withdrawn DECIMAL(20, 2) DEFAULT 0.00,
    total_wagered DECIMAL(20, 2) DEFAULT 0.00,
    total_won DECIMAL(20, 2) DEFAULT 0.00,
    total_lost DECIMAL(20, 2) DEFAULT 0.00,
    
    -- Security
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_phone_number (phone_number),
    INDEX idx_email (email),
    INDEX idx_wallet_address (wallet_address),
    INDEX idx_telegram_id (telegram_id),
    INDEX idx_status (status),
    INDEX idx_vip_level (vip_level_id),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (vip_level_id) REFERENCES vip_levels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2. wallets

User wallet with separate real and bonus balances.

```sql
CREATE TABLE wallets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED UNIQUE NOT NULL,
    
    -- Balances
    real_balance DECIMAL(20, 2) DEFAULT 0.00,
    bonus_balance DECIMAL(20, 2) DEFAULT 0.00,
    locked_balance DECIMAL(20, 2) DEFAULT 0.00,
    
    -- Statistics
    lifetime_deposits DECIMAL(20, 2) DEFAULT 0.00,
    lifetime_withdrawals DECIMAL(20, 2) DEFAULT 0.00,
    lifetime_wagered DECIMAL(20, 2) DEFAULT 0.00,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_updated_at (updated_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Constraints
    CHECK (real_balance >= 0),
    CHECK (bonus_balance >= 0),
    CHECK (locked_balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3. transactions

Immutable transaction log for all financial operations.

```sql
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    
    -- Transaction Details
    type ENUM(
        'deposit', 'withdrawal',
        'bet', 'win', 'refund',
        'bonus_credit', 'bonus_conversion',
        'cashback', 'referral_reward',
        'admin_adjustment'
    ) NOT NULL,
    
    amount DECIMAL(20, 2) NOT NULL,
    balance_type ENUM('real', 'bonus') DEFAULT 'real',
    
    -- Balance Tracking
    balance_before DECIMAL(20, 2) NOT NULL,
    balance_after DECIMAL(20, 2) NOT NULL,
    
    -- Status
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'completed',
    
    -- References
    reference_type VARCHAR(50) NULL, -- 'deposit', 'bet', 'bonus', etc.
    reference_id BIGINT UNSIGNED NULL,
    
    -- Metadata
    description TEXT NULL,
    metadata JSON NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_user_created (user_id, created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 4. deposits

Manual GCash deposit requests.

```sql
CREATE TABLE deposits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    
    -- Deposit Details
    amount DECIMAL(20, 2) NOT NULL,
    payment_method ENUM('gcash') DEFAULT 'gcash',
    
    -- GCash Details
    gcash_account_id BIGINT UNSIGNED NULL,
    reference_number VARCHAR(100) NULL,
    screenshot_url VARCHAR(255) NULL,
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    
    -- Admin Action
    admin_id BIGINT UNSIGNED NULL,
    admin_notes TEXT NULL,
    processed_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_admin_id (admin_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gcash_account_id) REFERENCES gcash_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    
    CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 5. withdrawals

Withdrawal requests with manual admin approval.

```sql
CREATE TABLE withdrawals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    
    -- Withdrawal Details
    amount DECIMAL(20, 2) NOT NULL,
    payment_method ENUM('gcash') DEFAULT 'gcash',
    
    -- GCash Details
    gcash_number VARCHAR(20) NOT NULL,
    gcash_name VARCHAR(100) NULL,
    
    -- Status
    status ENUM('pending', 'processing', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    
    -- Validation
    wagering_complete BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    vip_limit_passed BOOLEAN DEFAULT FALSE,
    
    -- Admin Action
    admin_id BIGINT UNSIGNED NULL,
    admin_notes TEXT NULL,
    processed_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_admin_id (admin_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    
    CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6. payment_methods

Available payment methods configuration.

```sql
CREATE TABLE payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    type ENUM('manual', 'automatic') DEFAULT 'manual',
    
    -- Limits
    min_deposit DECIMAL(20, 2) DEFAULT 0.00,
    max_deposit DECIMAL(20, 2) DEFAULT 999999.99,
    min_withdrawal DECIMAL(20, 2) DEFAULT 0.00,
    max_withdrawal DECIMAL(20, 2) DEFAULT 999999.99,
    
    -- Status
    is_enabled BOOLEAN DEFAULT TRUE,
    supports_deposits BOOLEAN DEFAULT TRUE,
    supports_withdrawals BOOLEAN DEFAULT TRUE,
    
    -- Display
    display_order INT DEFAULT 0,
    icon_url VARCHAR(255) NULL,
    description TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 7. gcash_accounts

Admin-configured GCash accounts for receiving deposits.

```sql
CREATE TABLE gcash_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Account Details
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    
    -- Limits
    daily_limit DECIMAL(20, 2) DEFAULT 999999.99,
    monthly_limit DECIMAL(20, 2) DEFAULT 9999999.99,
    
    -- Tracking
    daily_received DECIMAL(20, 2) DEFAULT 0.00,
    monthly_received DECIMAL(20, 2) DEFAULT 0.00,
    last_reset_date DATE NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    
    -- Notes
    notes TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 8. vip_levels

VIP tier configuration.

```sql
CREATE TABLE vip_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Level Details
    name VARCHAR(50) UNIQUE NOT NULL,
    level INT UNIQUE NOT NULL,
    
    -- Requirements
    min_wagered_amount DECIMAL(20, 2) DEFAULT 0.00,
    min_deposit_amount DECIMAL(20, 2) DEFAULT 0.00,
    
    -- Benefits
    bonus_multiplier DECIMAL(5, 2) DEFAULT 1.00,
    wagering_reduction DECIMAL(5, 2) DEFAULT 0.00,
    cashback_percentage DECIMAL(5, 2) DEFAULT 0.00,
    
    -- Withdrawal Limits
    withdrawal_limit_daily DECIMAL(20, 2) DEFAULT 999999.99,
    withdrawal_limit_weekly DECIMAL(20, 2) DEFAULT 9999999.99,
    withdrawal_limit_monthly DECIMAL(20, 2) DEFAULT 99999999.99,
    
    -- Processing Speed
    withdrawal_processing_hours INT DEFAULT 24,
    
    -- Display
    color VARCHAR(7) NULL, -- Hex color code
    icon_url VARCHAR(255) NULL,
    description TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 9. bonuses

User bonuses with wagering requirements.

```sql
CREATE TABLE bonuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    
    -- Bonus Details
    type ENUM('signup', 'reload', 'promotional', 'referral', 'cashback') NOT NULL,
    name VARCHAR(100) NULL,
    amount DECIMAL(20, 2) NOT NULL,
    
    -- Wagering
    wagering_requirement DECIMAL(20, 2) NOT NULL, -- Total amount to wager
    wagering_progress DECIMAL(20, 2) DEFAULT 0.00,
    wagering_multiplier INT DEFAULT 30, -- e.g., 30x
    
    -- Game Contributions
    game_contributions JSON NULL, -- {"dice": 100, "slots": 50}
    max_bet_amount DECIMAL(20, 2) NULL,
    
    -- Status
    status ENUM('active', 'completed', 'expired', 'forfeited', 'cancelled') DEFAULT 'active',
    
    -- Expiration
    expires_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    -- Metadata
    terms_conditions TEXT NULL,
    metadata JSON NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_type (type),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    CHECK (amount > 0),
    CHECK (wagering_requirement >= 0),
    CHECK (wagering_progress >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 10. bets

All game bets with provably fair data.

```sql
CREATE TABLE bets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    
    -- Game Details
    game_type ENUM('dice', 'hilo', 'mines', 'plinko', 'keno', 'wheel', 'pump', 'crash') NOT NULL,
    game_id VARCHAR(50) NOT NULL,
    
    -- Bet Details
    bet_amount DECIMAL(20, 2) NOT NULL,
    balance_type ENUM('real', 'bonus') DEFAULT 'real',
    multiplier DECIMAL(10, 4) DEFAULT 1.0000,
    payout DECIMAL(20, 2) DEFAULT 0.00,
    profit DECIMAL(20, 2) DEFAULT 0.00,
    
    -- Result
    result ENUM('win', 'loss', 'push') NOT NULL,
    game_result JSON NOT NULL, -- Game-specific result data
    
    -- Provably Fair
    server_seed_hash VARCHAR(64) NOT NULL,
    client_seed VARCHAR(64) NOT NULL,
    nonce INT UNSIGNED NOT NULL,
    server_seed VARCHAR(64) NULL, -- Revealed after bet
    revealed_at TIMESTAMP NULL,
    
    -- Bonus Wagering
    bonus_id BIGINT UNSIGNED NULL,
    wagering_contribution DECIMAL(20, 2) DEFAULT 0.00,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_game_type (game_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user_game (user_id, game_type),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_bonus_id (bonus_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bonus_id) REFERENCES bonuses(id) ON DELETE SET NULL,
    
    CHECK (bet_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 11. seeds

Provably fair seed pairs for users.

```sql
CREATE TABLE seeds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    
    -- Server Seed
    server_seed VARCHAR(64) UNIQUE NOT NULL,
    server_seed_hash VARCHAR(64) UNIQUE NOT NULL,
    
    -- Client Seed
    client_seed VARCHAR(64) NOT NULL,
    
    -- Nonce
    nonce INT UNSIGNED DEFAULT 0,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    revealed_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    INDEX idx_user_active (user_id, is_active),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 12. referrals

Referral system tracking.

```sql
CREATE TABLE referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Referrer
    referrer_id BIGINT UNSIGNED NOT NULL,
    referral_code VARCHAR(20) UNIQUE NOT NULL,
    
    -- Referred User
    referred_user_id BIGINT UNSIGNED UNIQUE NULL,
    
    -- Reward
    reward_amount DECIMAL(20, 2) DEFAULT 0.00,
    reward_paid BOOLEAN DEFAULT FALSE,
    reward_paid_at TIMESTAMP NULL,
    
    -- Statistics
    total_referred INT DEFAULT 0,
    total_earned DECIMAL(20, 2) DEFAULT 0.00,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referral_code (referral_code),
    INDEX idx_referred_user_id (referred_user_id),
    
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 13. admin_users

Admin panel users with role-based access.

```sql
CREATE TABLE admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Authentication
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Profile
    full_name VARCHAR(100) NOT NULL,
    
    -- Role
    role ENUM('admin', 'finance', 'support', 'developer') NOT NULL,
    
    -- Permissions (JSON array of permissions)
    permissions JSON NULL,
    
    -- Security
    ip_whitelist JSON NULL, -- Array of allowed IPs
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Login Tracking
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 14. audit_logs

Immutable audit trail for all system actions.

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Actor
    user_id BIGINT UNSIGNED NULL,
    admin_id BIGINT UNSIGNED NULL,
    actor_type ENUM('user', 'admin', 'system') NOT NULL,
    
    -- Action
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL, -- 'deposit', 'withdrawal', 'user', etc.
    resource_id BIGINT UNSIGNED NULL,
    
    -- Details
    description TEXT NULL,
    changes JSON NULL, -- Before/after values
    metadata JSON NULL,
    
    -- Request Info
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_url VARCHAR(255) NULL,
    request_method VARCHAR(10) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at),
    INDEX idx_actor_created (actor_type, created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 15. sessions

User session management.

```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Indexes Summary

Critical indexes for performance optimization:

```sql
-- Users Table
CREATE INDEX idx_users_phone ON users(phone_number);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_wallet ON users(wallet_address);
CREATE INDEX idx_users_telegram ON users(telegram_id);
CREATE INDEX idx_users_status ON users(status);

-- Transactions Table
CREATE INDEX idx_transactions_user_created ON transactions(user_id, created_at);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_transactions_status ON transactions(status);

-- Bets Table
CREATE INDEX idx_bets_user_created ON bets(user_id, created_at);
CREATE INDEX idx_bets_game_type ON bets(game_type);

-- Deposits/Withdrawals
CREATE INDEX idx_deposits_status ON deposits(status);
CREATE INDEX idx_withdrawals_status ON withdrawals(status);

-- Audit Logs
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX idx_audit_logs_actor ON audit_logs(actor_type, created_at);
```

---

## Data Integrity Rules

### 1. Balance Constraints
- All balance columns must be >= 0
- Transactions must be atomic (use database transactions)
- Pessimistic locking for balance updates

### 2. Immutable Records
- `transactions` table: No updates allowed after creation
- `audit_logs` table: No updates or deletes allowed
- `bets` table: No updates allowed after creation

### 3. Referential Integrity
- Cascade deletes for user-owned records
- Set NULL for optional foreign keys
- Prevent deletion of referenced records

### 4. Status Transitions
- Deposits: pending → approved/rejected
- Withdrawals: pending → processing → completed/rejected
- Bonuses: active → completed/expired/forfeited

---

## Migration Order

Execute migrations in this order to satisfy foreign key constraints:

1. `vip_levels`
2. `admin_users`
3. `payment_methods`
4. `gcash_accounts`
5. `users`
6. `wallets`
7. `seeds`
8. `bonuses`
9. `transactions`
10. `deposits`
11. `withdrawals`
12. `bets`
13. `referrals`
14. `audit_logs`
15. `sessions`

---

## Sample Seed Data

### VIP Levels

```sql
INSERT INTO vip_levels (name, level, min_wagered_amount, bonus_multiplier, wagering_reduction, cashback_percentage) VALUES
('Bronze', 1, 0, 1.00, 0, 0),
('Silver', 2, 1000, 1.10, 5, 1),
('Gold', 3, 5000, 1.25, 10, 2),
('Platinum', 4, 20000, 1.50, 15, 3),
('Diamond', 5, 100000, 2.00, 20, 5);
```

### Payment Methods

```sql
INSERT INTO payment_methods (name, code, type, min_deposit, max_deposit, min_withdrawal, max_withdrawal) VALUES
('GCash', 'gcash', 'manual', 100.00, 50000.00, 200.00, 100000.00);
```

---

**Last Updated**: December 21, 2025
