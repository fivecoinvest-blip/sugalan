# Third-Party Slot Game Integration Guide

**Phase 8: Slot Game Integration**  
**Last Updated**: December 24, 2025  
**Status**: Planning & Documentation

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Test Environment Credentials](#test-environment-credentials)
3. [Integration Architecture](#integration-architecture)
4. [API Endpoints](#api-endpoints)
5. [Implementation Plan](#implementation-plan)
6. [Security Requirements](#security-requirements)
7. [Testing Strategy](#testing-strategy)
8. [Go-Live Checklist](#go-live-checklist)

---

## 🎯 Overview

This guide covers the integration of third-party slot games into the Sugalan casino platform. The integration will provide access to a library of professional slot games while maintaining our security standards and wallet system.

### Integration Goals

- ✅ Seamless player experience (single wallet system)
- ✅ Secure API communication (AES encryption)
- ✅ Real-time game launches from our platform
- ✅ Automatic wallet synchronization
- ✅ Transaction audit trail
- ✅ Support for multiple game providers
- ✅ Mobile-responsive game interface

---

## 🔐 Test Environment Credentials

### Provider: AYUT Gaming Platform

```
Environment:     Test/Sandbox
Name:            AYUT
Agency UID:      4fcbdc0bf258b53d8fa02d85c6ddbdf6
AES Key:         fd1e3a6a4b3dc050c7f9238c49bf5f56
Player Prefix:   hc57f0
Server URL:      https://jsgame.live
```

### Security Notes

- **AES Key**: Used for request/response encryption
- **Player Prefix**: Prepended to all player IDs to avoid conflicts
- **Agency UID**: Identifies our platform in API requests
- **Never commit credentials to version control**

---

## 🏗️ Integration Architecture

### System Flow

```
┌─────────────┐         ┌──────────────┐         ┌─────────────────┐
│             │         │              │         │                 │
│   Player    │────────▶│   Sugalan    │────────▶│  Slot Provider  │
│  Frontend   │         │   Backend    │         │   (AYUT API)    │
│             │         │              │         │                 │
└─────────────┘         └──────────────┘         └─────────────────┘
                               │
                               │
                        ┌──────▼──────┐
                        │             │
                        │   Wallet    │
                        │   Service   │
                        │             │
                        └─────────────┘
```

### Component Responsibilities

#### 1. Frontend (Vue.js)
- Display game lobby/categories
- Launch game in iframe/modal
- Handle loading states
- Display player balance

#### 2. Backend (Laravel)
- Authenticate player
- Generate game launch tokens
- Handle wallet callbacks
- Encrypt/decrypt API requests
- Log all transactions
- Sync balance with provider

#### 3. Wallet Service
- Lock balance during active sessions
- Process bets and wins
- Maintain transaction history
- Handle rollbacks

#### 4. Slot Provider API
- Provide game URLs
- Host game clients
- Send wallet callbacks
- Return game results

---

## 📡 API Endpoints

### Our Backend Endpoints (To Implement)

#### 1. Get Game List
```http
GET /api/slots/games
Authorization: Bearer {jwt_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "games": [
      {
        "id": "game_001",
        "name": "Fire Strike",
        "provider": "AYUT",
        "category": "video_slots",
        "thumbnail": "https://cdn.example.com/games/fire-strike.jpg",
        "min_bet": 1.00,
        "max_bet": 1000.00,
        "rtp": 96.5,
        "volatility": "high",
        "lines": 20
      }
    ],
    "categories": ["video_slots", "classic_slots", "jackpot_slots"]
  }
}
```

#### 2. Launch Game
```http
POST /api/slots/launch
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "game_id": "game_001",
  "demo_mode": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "game_url": "https://jsgame.live/game/launch?token=abc123...",
    "session_id": "sess_xyz789",
    "balance": 1500.50,
    "expires_at": "2025-12-24T12:00:00Z"
  }
}
```

#### 3. Get Active Session
```http
GET /api/slots/session/{session_id}
Authorization: Bearer {jwt_token}
```

#### 4. End Session
```http
POST /api/slots/session/{session_id}/end
Authorization: Bearer {jwt_token}
```

---

### Provider Callback Endpoints (To Implement)

These endpoints will be called by the slot provider:

#### 1. Get Player Balance
```http
POST /api/slots/callback/balance
Content-Type: application/json
X-Signature: {hmac_signature}

{
  "agency_uid": "4fcbdc0bf258b53d8fa02d85c6ddbdf6",
  "player_id": "hc57f0_123456",
  "session_id": "sess_xyz789"
}
```

**Response:**
```json
{
  "success": true,
  "balance": 1500.50,
  "currency": "PHP"
}
```

#### 2. Place Bet
```http
POST /api/slots/callback/bet
Content-Type: application/json
X-Signature: {hmac_signature}

{
  "agency_uid": "4fcbdc0bf258b53d8fa02d85c6ddbdf6",
  "player_id": "hc57f0_123456",
  "session_id": "sess_xyz789",
  "round_id": "round_abc123",
  "transaction_id": "txn_def456",
  "bet_amount": 10.00,
  "game_id": "game_001",
  "timestamp": "2025-12-24T10:30:00Z"
}
```

**Response:**
```json
{
  "success": true,
  "balance": 1490.50,
  "transaction_id": "txn_def456"
}
```

#### 3. Credit Win
```http
POST /api/slots/callback/win
Content-Type: application/json
X-Signature: {hmac_signature}

{
  "agency_uid": "4fcbdc0bf258b53d8fa02d85c6ddbdf6",
  "player_id": "hc57f0_123456",
  "session_id": "sess_xyz789",
  "round_id": "round_abc123",
  "transaction_id": "txn_ghi789",
  "win_amount": 50.00,
  "game_id": "game_001",
  "timestamp": "2025-12-24T10:30:15Z"
}
```

**Response:**
```json
{
  "success": true,
  "balance": 1540.50,
  "transaction_id": "txn_ghi789"
}
```

#### 4. Rollback Transaction
```http
POST /api/slots/callback/rollback
Content-Type: application/json
X-Signature: {hmac_signature}

{
  "agency_uid": "4fcbdc0bf258b53d8fa02d85c6ddbdf6",
  "player_id": "hc57f0_123456",
  "transaction_id": "txn_def456",
  "reason": "connection_timeout"
}
```

---

## 🛠️ Implementation Plan

### Phase 8.1: Database Schema (Week 34)

#### New Tables

**1. slot_providers**
```sql
CREATE TABLE slot_providers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    agency_uid VARCHAR(100) NOT NULL,
    aes_key VARCHAR(100) NOT NULL,
    player_prefix VARCHAR(20) NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    callback_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    config JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
);
```

**2. slot_games**
```sql
CREATE TABLE slot_games (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    provider_id BIGINT UNSIGNED NOT NULL,
    game_id VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50),
    thumbnail_url VARCHAR(500),
    min_bet DECIMAL(15,2) DEFAULT 1.00,
    max_bet DECIMAL(15,2) DEFAULT 10000.00,
    rtp DECIMAL(5,2),
    volatility VARCHAR(20),
    lines INT,
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES slot_providers(id),
    UNIQUE KEY unique_game (provider_id, game_id),
    INDEX idx_provider (provider_id),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
);
```

**3. slot_sessions**
```sql
CREATE TABLE slot_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    game_id BIGINT UNSIGNED NOT NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    session_token VARCHAR(500) NOT NULL,
    game_url TEXT,
    initial_balance DECIMAL(15,2) NOT NULL,
    final_balance DECIMAL(15,2),
    total_bets DECIMAL(15,2) DEFAULT 0,
    total_wins DECIMAL(15,2) DEFAULT 0,
    rounds_played INT DEFAULT 0,
    status ENUM('active', 'ended', 'expired') DEFAULT 'active',
    started_at TIMESTAMP,
    ended_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (game_id) REFERENCES slot_games(id),
    FOREIGN KEY (provider_id) REFERENCES slot_providers(id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_provider (provider_id)
);
```

**4. slot_transactions**
```sql
CREATE TABLE slot_transactions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    session_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    wallet_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED,
    round_id VARCHAR(100) NOT NULL,
    external_txn_id VARCHAR(100) NOT NULL,
    type ENUM('bet', 'win', 'rollback') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    game_data JSON,
    status ENUM('pending', 'completed', 'rolled_back') DEFAULT 'completed',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES slot_sessions(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (wallet_id) REFERENCES wallets(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    UNIQUE KEY unique_external_txn (external_txn_id),
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_round (round_id),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
);
```

### Phase 8.2: Backend Services (Week 34)

#### Services to Create

1. **SlotProviderService** - Manages provider configurations
2. **SlotGameService** - Handles game catalog and launches
3. **SlotSessionService** - Manages game sessions
4. **SlotWalletService** - Processes bets/wins (extends WalletService)
5. **SlotCallbackService** - Handles provider callbacks
6. **SlotEncryptionService** - AES encryption/decryption

#### Key Features

- ✅ AES-256 encryption for API requests
- ✅ HMAC signature verification for callbacks
- ✅ Idempotent transaction processing
- ✅ Automatic rollback handling
- ✅ Session expiration management
- ✅ Balance locking during active sessions
- ✅ Comprehensive audit logging

### Phase 8.3: API Controllers (Week 34-35)

1. **SlotController** - Player-facing endpoints
2. **SlotCallbackController** - Provider callback handlers
3. **AdminSlotController** - Admin game management

### Phase 8.4: Frontend Integration (Week 35)

#### Components to Create

1. **SlotLobby.vue** - Game catalog display
2. **SlotGame.vue** - Game iframe container
3. **SlotCategories.vue** - Category filters
4. **SlotSearch.vue** - Game search
5. **SlotSession.vue** - Active session management

#### User Experience

- Responsive game lobby with filters
- One-click game launch
- Real-time balance updates
- Session timeout warnings
- Mobile-optimized interface
- Landscape mode for mobile games

### Phase 8.5: Testing (Week 35-36)

#### Test Coverage

1. **Unit Tests**
   - Encryption/decryption
   - Balance calculations
   - Transaction validation
   - Rollback logic

2. **Integration Tests**
   - Game launch flow
   - Bet placement
   - Win crediting
   - Session expiration
   - Callback authentication

3. **End-to-End Tests**
   - Complete game session
   - Multiple concurrent sessions
   - Network failure recovery
   - Balance synchronization

---

## 🔒 Security Requirements

### 1. API Encryption

- All requests/responses encrypted with AES-256-CBC
- Unique IV (Initialization Vector) per request
- Key rotation support

```php
// Example encryption
$encrypted = openssl_encrypt(
    json_encode($data),
    'AES-256-CBC',
    $aes_key,
    0,
    $iv
);
```

### 2. Callback Authentication

- HMAC-SHA256 signature verification
- Timestamp validation (5-minute window)
- Replay attack prevention

```php
$signature = hash_hmac(
    'sha256',
    $request_body . $timestamp,
    $aes_key
);
```

### 3. Transaction Security

- Idempotent operations (duplicate prevention)
- Atomic wallet operations
- Comprehensive audit trail
- Automatic rollback on failure

### 4. Session Security

- Secure random token generation
- Session expiration (30 minutes default)
- IP address validation
- One session per player per game

---

## 🧪 Testing Strategy

### Test Environment Testing

1. **Provider Integration Test**
   - Verify API connectivity
   - Test encryption/decryption
   - Validate callback endpoints
   - Check error handling

2. **Wallet Integration Test**
   - Balance inquiry
   - Bet placement (various amounts)
   - Win crediting
   - Rollback scenarios
   - Concurrent transactions

3. **Session Management Test**
   - Game launch
   - Session expiration
   - Multiple sessions
   - Force close handling

### Production Readiness

- [ ] All unit tests passing (95%+ coverage)
- [ ] Integration tests completed
- [ ] Load testing (100+ concurrent games)
- [ ] Security audit completed
- [ ] Error handling tested
- [ ] Rollback scenarios validated
- [ ] Documentation complete
- [ ] Admin tools functional
- [ ] Monitoring alerts configured

---

## 📋 Go-Live Checklist

### Pre-Launch (Week 36)

- [ ] Production credentials obtained
- [ ] Production API endpoints configured
- [ ] SSL certificates validated
- [ ] Firewall rules configured
- [ ] Rate limiting implemented
- [ ] Error tracking enabled (Sentry)
- [ ] Performance monitoring setup
- [ ] Backup procedures tested

### Launch Day

- [ ] Provider notified
- [ ] Database migrations run
- [ ] Seed data imported (game catalog)
- [ ] Admin users trained
- [ ] Customer support briefed
- [ ] Monitoring dashboards active
- [ ] Rollback plan ready

### Post-Launch (Week 37)

- [ ] Monitor error rates
- [ ] Review transaction logs
- [ ] Check balance reconciliation
- [ ] Gather player feedback
- [ ] Optimize slow queries
- [ ] Update documentation

---

## 📊 Monitoring & Metrics

### Key Metrics to Track

1. **Technical Metrics**
   - API response times
   - Error rates
   - Session duration
   - Game load times

2. **Business Metrics**
   - Games played per day
   - Total bets volume
   - Average bet size
   - RTP accuracy
   - Revenue per game

3. **Operational Metrics**
   - Balance discrepancies
   - Failed transactions
   - Rollback frequency
   - Session expiration rate

---

## 🚨 Error Handling

### Common Scenarios

1. **Insufficient Balance**
   ```json
   {
     "success": false,
     "error_code": "INSUFFICIENT_BALANCE",
     "message": "Player balance too low for bet",
     "balance": 5.00,
     "required": 10.00
   }
   ```

2. **Duplicate Transaction**
   ```json
   {
     "success": true,
     "message": "Transaction already processed",
     "original_transaction_id": "txn_def456",
     "balance": 1490.50
   }
   ```

3. **Session Expired**
   ```json
   {
     "success": false,
     "error_code": "SESSION_EXPIRED",
     "message": "Game session has expired",
     "expired_at": "2025-12-24T10:00:00Z"
   }
   ```

---

## 📞 Support Contacts

### Provider Support
- **AYUT Gaming Platform**
- Email: support@ayut.example.com
- Skype: ayut.support
- Hours: 24/7

### Internal Escalation
- Tech Lead: [Contact Info]
- DevOps: [Contact Info]
- Finance Team: [Contact Info]

---

## 📚 Additional Resources

- [Provider API Documentation](./GameApi Doc EN.html)
- [Wallet Service Documentation](./ARCHITECTURE.md)
- [Security Guidelines](./SECURITY_HARDENING.md)
- [Testing Procedures](./PHASE_12_COMPLETION_SUMMARY.md)

---

**Last Updated**: December 24, 2025  
**Document Version**: 1.0  
**Status**: Planning Phase  
**Next Review**: Start of Week 34
