# Frontend Authentication Guide

## Overview
The backend uses JWT (JSON Web Tokens) for authentication. After login/registration, the frontend must store the token and include it in all subsequent requests.

## Authentication Flow

### 1. Registration/Login
When a user registers or logs in successfully, the API returns:

```json
{
  "success": true,
  "data": {
    "user": { ... },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

### 2. Store the Token
The frontend MUST store this token. Common approaches:

**Option A: localStorage (persists across sessions)**
```javascript
// After successful login/registration
const response = await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ phone: '09972382805', password: 'password' })
});

const data = await response.json();
if (data.success) {
  // Store token
  localStorage.setItem('access_token', data.data.access_token);
  localStorage.setItem('user', JSON.stringify(data.data.user));
  
  // Redirect to user dashboard
  window.location.href = '/dashboard';
}
```

**Option B: sessionStorage (cleared when browser closes)**
```javascript
sessionStorage.setItem('access_token', data.data.access_token);
```

### 3. Include Token in All Protected Requests
Every request to a protected route MUST include the Authorization header:

```javascript
// Get the stored token
const token = localStorage.getItem('access_token');

// Make authenticated request
const response = await fetch('/api/wallet/balance', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
```

### 4. Handle 401 Unauthorized Errors
If a token expires or is invalid, the API returns 401. The frontend should:
- Clear stored token
- Redirect to login page

```javascript
if (response.status === 401) {
  // Token expired or invalid
  localStorage.removeItem('access_token');
  localStorage.removeItem('user');
  window.location.href = '/login';
}
```

## Protected Routes
These routes require the Authorization header:

### User Profile
- `GET /api/auth/me` - Get current user
- `POST /api/auth/logout` - Logout
- `GET /api/user/profile` - Get profile
- `PUT /api/user/profile` - Update profile
- `POST /api/user/change-password` - Change password

### Wallet
- `GET /api/wallet/balance` - Get balance
- `GET /api/wallet/transactions` - Transaction history

### Payments
- `POST /api/payments/deposits` - Create deposit
- `GET /api/payments/deposits` - Deposit history
- `POST /api/payments/withdrawals` - Create withdrawal
- `GET /api/payments/withdrawals` - Withdrawal history

### Games (All game endpoints)
- `POST /api/games/dice/play`
- `POST /api/games/hilo/start`
- `POST /api/games/mines/start`
- `POST /api/games/plinko/play`
- `POST /api/games/keno/play`
- `POST /api/games/wheel/spin`
- `POST /api/games/crash/bet`
- etc.

### VIP
- `GET /api/vip/benefits`
- `GET /api/vip/progress`

### Bonuses
- `GET /api/bonuses/active`
- `GET /api/bonuses/history`

### Referrals
- `GET /api/referrals/stats`
- `GET /api/referrals/my-code`

## Complete Frontend Example

### Using Fetch API
```javascript
// Authentication service
class AuthService {
  static async login(phone, password) {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone, password })
    });
    
    const data = await response.json();
    if (data.success) {
      localStorage.setItem('access_token', data.data.access_token);
      localStorage.setItem('user', JSON.stringify(data.data.user));
      return data.data;
    }
    throw new Error(data.message || 'Login failed');
  }
  
  static logout() {
    localStorage.removeItem('access_token');
    localStorage.removeItem('user');
    window.location.href = '/login';
  }
  
  static getToken() {
    return localStorage.getItem('access_token');
  }
  
  static isAuthenticated() {
    return !!this.getToken();
  }
}

// API service
class ApiService {
  static async request(url, options = {}) {
    const token = AuthService.getToken();
    
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };
    
    // Add Authorization header if token exists
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    
    const response = await fetch(url, {
      ...options,
      headers
    });
    
    // Handle 401 Unauthorized
    if (response.status === 401) {
      AuthService.logout();
      return;
    }
    
    return response.json();
  }
  
  // Convenience methods
  static get(url) {
    return this.request(url, { method: 'GET' });
  }
  
  static post(url, data) {
    return this.request(url, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }
}

// Usage examples
async function playDiceGame() {
  try {
    const result = await ApiService.post('/api/games/dice/play', {
      bet_amount: 10,
      target_number: 50,
      prediction: 'over'
    });
    
    if (result.success) {
      console.log('Win:', result.data.win_amount);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

async function getWalletBalance() {
  const data = await ApiService.get('/api/wallet/balance');
  console.log('Balance:', data.data.balance);
}
```

### Using Axios
```javascript
import axios from 'axios';

// Create axios instance
const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Add token to all requests
api.interceptors.request.use(config => {
  const token = localStorage.getItem('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 errors globally
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      localStorage.removeItem('access_token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Usage
async function login(phone, password) {
  const { data } = await api.post('/auth/login', { phone, password });
  localStorage.setItem('access_token', data.data.access_token);
  return data.data;
}

async function getProfile() {
  const { data } = await api.get('/auth/me');
  return data.data;
}

async function playGame() {
  const { data } = await api.post('/games/dice/play', {
    bet_amount: 10,
    target_number: 50,
    prediction: 'over'
  });
  return data.data;
}
```

## Debugging

### Check if Token is Stored
Open browser console:
```javascript
console.log(localStorage.getItem('access_token'));
```

### Check if Token is Being Sent
Open browser DevTools → Network tab → Click on any API request → Check Headers:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

If you don't see the Authorization header, that's why protected routes return 401.

### Test Token Manually
```javascript
// In browser console after login
const token = localStorage.getItem('access_token');

fetch('/api/auth/me', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
})
.then(r => r.json())
.then(console.log);
```

## Current Issue Solution

**Problem**: User can login but can't access games or user pages.

**Root Cause**: Frontend is not sending the Authorization header with the JWT token.

**Solution**:
1. After successful login, store the token:
   ```javascript
   localStorage.setItem('access_token', loginResponse.data.access_token);
   ```

2. Include token in all subsequent requests:
   ```javascript
   fetch('/api/games/dice/play', {
     headers: {
       'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
       'Content-Type': 'application/json'
     },
     // ... rest of request
   });
   ```

3. Verify the token is being sent in DevTools Network tab.

## Security Notes

- **Never** expose tokens in URLs (use headers only)
- Clear tokens on logout
- Handle token expiration gracefully
- Consider using httpOnly cookies for production (more secure than localStorage)
- Implement token refresh mechanism for better UX
