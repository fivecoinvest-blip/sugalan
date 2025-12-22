# Sugalan Casino API Documentation

## Base URL
```
Development: http://localhost:8000/api
Production: https://api.sugalan.com/api
```

## Authentication

### JWT Token Authentication
Most endpoints require a JWT token in the Authorization header:
```
Authorization: Bearer <token>
```

Admin endpoints use the `auth:admin` guard with separate tokens.

---

## Public Endpoints

### Authentication

#### Register with Phone
```http
POST /auth/register/phone
Content-Type: application/json

{
  "phone_number": "09171234567",
  "password": "SecurePass123!",
  "username": "player123",
  "email": "player@example.com",
  "referral_code": "ABC123" // optional
}
```

#### Login with Phone
```http
POST /auth/login/phone
Content-Type: application/json

{
  "phone_number": "09171234567",
  "password": "SecurePass123!"
}
```

#### MetaMask Authentication
```http
POST /auth/metamask
Content-Type: application/json

{
  "wallet_address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
  "signature": "0x...",
  "message": "Sign this message to authenticate..."
}
```

#### Telegram Authentication
```http
POST /auth/telegram
Content-Type: application/json

{
  "telegram_id": "123456789",
  "first_name": "John",
  "username": "johndoe",
  "auth_date": 1703260800,
  "hash": "..."
}
```

#### Create Guest Account
```http
POST /auth/guest
Content-Type: application/json

{} // No body required
```

#### Refresh Token
```http
POST /auth/refresh
Authorization: Bearer <expired_token>
```

---

## Protected Endpoints (User)

### Profile

#### Get Current User
```http
GET /auth/me
Authorization: Bearer <token>
```

#### Logout
```http
POST /auth/logout
Authorization: Bearer <token>
```

### Wallet

#### Get Balance
```http
GET /wallet/balance
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "real_balance": 1000.00,
    "bonus_balance": 50.00,
    "locked_balance": 100.00,
    "total_balance": 1050.00,
    "available_balance": 950.00
  }
}
```

#### Get Transactions
```http
GET /wallet/transactions?page=1&per_page=20
Authorization: Bearer <token>
```

### Payments

#### Get Available GCash Accounts
```http
GET /payments/gcash-accounts
Authorization: Bearer <token>
```

#### Create Deposit
```http
POST /payments/deposits
Authorization: Bearer <token>
Content-Type: multipart/form-data

{
  "gcash_account_id": 1,
  "amount": 500,
  "reference_number": "GC12345678",
  "screenshot": <file>,
  "notes": "Optional notes"
}
```

#### Get Deposit History
```http
GET /payments/deposits?page=1&per_page=20
Authorization: Bearer <token>
```

#### Create Withdrawal
```http
POST /payments/withdrawals
Authorization: Bearer <token>
Content-Type: application/json

{
  "amount": 1000,
  "gcash_number": "09171234567",
  "gcash_name": "Juan Dela Cruz"
}
```

#### Get Withdrawal History
```http
GET /payments/withdrawals?page=1&per_page=20
Authorization: Bearer <token>
```

#### Get Payment Statistics
```http
GET /payments/stats
Authorization: Bearer <token>

Response:
{
  "success": true,
  "data": {
    "total_deposited": 5000.00,
    "total_withdrawn": 2000.00,
    "net_balance": 3000.00,
    "pending_deposits": 1,
    "pending_withdrawals": 0
  }
}
```

### Notifications

#### Get Notifications
```http
GET /notifications?page=1&per_page=20&unread_only=false
Authorization: Bearer <token>
```

#### Get Unread Count
```http
GET /notifications/unread-count
Authorization: Bearer <token>
```

#### Mark as Read
```http
POST /notifications/{id}/read
Authorization: Bearer <token>
```

#### Mark All as Read
```http
POST /notifications/read-all
Authorization: Bearer <token>
```

### Games

#### Dice Game
```http
POST /games/dice/play
Authorization: Bearer <token>
Content-Type: application/json

{
  "bet_amount": 10,
  "target": 50.5,
  "prediction": "over" // or "under"
}

Response:
{
  "success": true,
  "data": {
    "result": 67.42,
    "won": true,
    "payout": 19.60,
    "multiplier": 1.96,
    "bet_id": "uuid-here",
    "provably_fair": {
      "server_seed_hash": "...",
      "client_seed": "...",
      "nonce": 1
    }
  }
}
```

#### Hi-Lo Game
```http
POST /games/hilo/start
Authorization: Bearer <token>
Content-Type: application/json

{
  "bet_amount": 10
}

POST /games/hilo/predict
{
  "prediction": "higher" // or "lower"
}

POST /games/hilo/cashout
```

#### Mines Game
```http
POST /games/mines/start
Authorization: Bearer <token>
Content-Type: application/json

{
  "bet_amount": 10,
  "mines_count": 3
}

POST /games/mines/reveal
{
  "position": 5 // 0-24
}

POST /games/mines/cashout
```

#### Plinko Game
```http
POST /games/plinko/play
Authorization: Bearer <token>
Content-Type: application/json

{
  "bet_amount": 10,
  "risk_level": "medium" // low, medium, high
}
```

#### Keno Game
```http
POST /games/keno/play
Authorization: Bearer <token>
Content-Type: application/json

{
  "bet_amount": 10,
  "selected_numbers": [1, 5, 10, 15, 20, 25, 30, 35] // 1-10 numbers
}
```

#### Wheel Game
```http
GET /games/wheel/config
// Get wheel configuration

POST /games/wheel/spin
Authorization: Bearer <token>
Content-Type: application/json

{
  "bet_amount": 10,
  "risk_level": "medium" // low, medium, high
}
```

#### Crash Game
```http
GET /games/crash/current
// Get current crash round

POST /games/crash/bet
Authorization: Bearer <token>
Content-Type: application/json

{
  "bet_amount": 10
}

POST /games/crash/cashout
Authorization: Bearer <token>
Content-Type: application/json

{
  "target_multiplier": 2.0
}
```

---

## Admin Endpoints

### Admin Authentication

#### Admin Login
```http
POST /admin/auth/login
Content-Type: application/json

{
  "email": "admin@sugalan.com",
  "password": "Admin123!@#"
}

Response:
{
  "success": true,
  "message": "Admin login successful",
  "data": {
    "admin": {
      "id": 1,
      "username": "superadmin",
      "full_name": "Super Admin",
      "email": "admin@sugalan.com",
      "role": "admin",
      "permissions": [...]
    },
    "access_token": "...",
    "token_type": "bearer",
    "expires_in": 900
  }
}
```

#### Get Current Admin
```http
GET /admin/auth/me
Authorization: Bearer <admin_token>
```

#### Admin Logout
```http
POST /admin/auth/logout
Authorization: Bearer <admin_token>
```

### Dashboard

#### Get Overview Statistics
```http
GET /admin/dashboard/overview?period=today
Authorization: Bearer <admin_token>

Query params: period (today, week, month, all)

Response:
{
  "success": true,
  "data": {
    "users": {
      "total_users": 150,
      "active_users": 120,
      "guest_users": 30,
      "vip_distribution": [...]
    },
    "financial": {
      "total_deposits": 50000.00,
      "total_withdrawals": 20000.00,
      "total_bets": 100000.00,
      "total_winnings": 95000.00,
      "gross_revenue": 5000.00,
      "net_revenue": 30000.00,
      "profit_margin": 5.00
    },
    "games": {
      "by_game": [...],
      "total_bets": 500
    },
    "pending": {
      "pending_deposits": 5,
      "pending_withdrawals": 3
    }
  }
}
```

#### Get Recent Activity
```http
GET /admin/dashboard/activity?limit=20
Authorization: Bearer <admin_token>
```

#### Get Users List
```http
GET /admin/users?page=1&per_page=50&search=john&status=active&vip_level=1
Authorization: Bearer <admin_token>
```

### Payment Management

#### Get Pending Deposits
```http
GET /admin/deposits/pending?page=1&per_page=50
Authorization: Bearer <admin_token>
```

#### Approve Deposit
```http
POST /admin/deposits/{id}/approve
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "admin_notes": "Verified and approved"
}
```

#### Reject Deposit
```http
POST /admin/deposits/{id}/reject
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "rejected_reason": "Invalid reference number"
}
```

#### Get Deposit Statistics
```http
GET /admin/deposits/stats?period=today
Authorization: Bearer <admin_token>

Response:
{
  "success": true,
  "data": {
    "total_amount": 10000.00,
    "total_count": 20,
    "average_amount": 500.00,
    "pending_count": 5
  }
}
```

#### Get Pending Withdrawals
```http
GET /admin/withdrawals/pending?page=1&per_page=50
Authorization: Bearer <admin_token>
```

#### Approve Withdrawal
```http
POST /admin/withdrawals/{id}/approve
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "admin_notes": "Payment sent via GCash"
}
```

#### Reject Withdrawal
```http
POST /admin/withdrawals/{id}/reject
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "rejected_reason": "Wagering requirement not met"
}
```

#### Get Withdrawal Statistics
```http
GET /admin/withdrawals/stats?period=today
Authorization: Bearer <admin_token>
```

---

## Default Admin Accounts

For testing purposes, use these credentials:

1. **Super Admin**
   - Email: `admin@sugalan.com`
   - Password: `Admin123!@#`
   - Permissions: All

2. **Finance Manager**
   - Email: `finance@sugalan.com`
   - Password: `Finance123!@#`
   - Permissions: Deposits, Withdrawals, Reports

3. **Support Agent**
   - Email: `support@sugalan.com`
   - Password: `Support123!@#`
   - Permissions: Users, Reports

4. **Game Manager**
   - Email: `games@sugalan.com`
   - Password: `Games123!@#`
   - Permissions: Games, Reports

---

## Error Responses

All endpoints return errors in this format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Rate Limiting

API endpoints are rate-limited to prevent abuse:
- Standard endpoints: 60 requests per minute
- Auth endpoints: 10 requests per minute

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

---

## Provably Fair Verification

All game results can be verified using:

1. **Server Seed Hash** - Provided before game
2. **Client Seed** - Player-chosen or random
3. **Nonce** - Bet counter
4. **Result** - Game outcome

Verification formula:
```
hash = HMAC-SHA256(client_seed:nonce, server_seed)
result = convert_hash_to_game_result(hash)
```

---

## WebSocket Events (Future)

Real-time updates for:
- New notifications
- Crash game multiplier updates
- Balance changes
- Bonus activations

---

## Changelog

### v1.0.0 (December 22, 2025)
- Initial API release
- Multi-authentication support
- 7 provably fair games
- Manual GCash payment system
- Admin dashboard APIs
- Notification system
