# User Experience - Playing Slot Games

## Overview
Users can now browse and play 132 JILI slot games through the AYUT platform with seamless wallet integration.

## Features

### 1. Game Browsing
**URL**: `http://127.0.0.1:8000/slots`

**Features**:
- **Search Bar**: Search games by name
- **Provider Filter**: Filter by aggregator (AYUT)
- **Category Filter**: Filter by game type (slots, table, etc.)
- **Popular Games Section**: Featured top games
- **All Games Grid**: Complete game library

### 2. Game Cards
Each game card displays:
- **Thumbnail**: Game image (automatically prefixed with `/storage/`)
- **Game Name**: Display name
- **Manufacturer Badge**: Purple badge showing game provider (JILI)
- **Aggregator Badge**: Blue badge showing API platform (AYUT)
- **RTP**: Return to player percentage
- **Volatility**: Game volatility level
- **Category**: Game type

**Hover Effect**: Shows "▶ Play Now" overlay

### 3. Game Launch Flow

#### Step 1: User Clicks Game
- User clicks on any game card
- System checks authentication

#### Step 2: Authentication Check
If not logged in:
- Shows login modal
- User must authenticate first

If logged in:
- Shows loading modal
- "Launching game..." message

#### Step 3: Backend Processing
```
POST /api/slots/games/{gameId}/launch
Headers: Authorization: Bearer {token}
Body: { demo_mode: false }
```

**Backend Actions**:
1. Validates user authentication
2. Checks game availability
3. Checks wallet balance
4. Creates slot session
5. Generates session token
6. Calls AYUT API to get game URL
7. Returns game URL to frontend

#### Step 4: Game Launch
- Game opens in modal iframe
- Full-screen capable
- Active session indicator appears at bottom
- Wallet balance updates in real-time

### 4. Active Session Bar
When a game is running:
```
🎮 Playing: Super Ace | Balance: ₱10,000.00 | [End Session]
```

Features:
- Shows current game name
- Displays current balance
- "End Session" button to close game

### 5. Ending Session
**Action**: User clicks "End Session"
```
POST /api/slots/session/end
```

**Result**:
- Closes game modal
- Updates wallet balance
- Clears active session
- Returns to game grid

## API Endpoints

### Get Providers
```http
GET /api/slots/providers
Authorization: Bearer {token}
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "AYUT",
      "name": "AYUT Gaming",
      "is_active": true
    }
  ]
}
```

### Get Games
```http
GET /api/slots/games?provider=AYUT&category=slots
Authorization: Bearer {token}
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "game_id": "24da72b49b0dd0e5cbef9579d09d8981",
      "name": "Super Ace",
      "manufacturer": "JILI",
      "provider_name": "AYUT Gaming",
      "category": "slots",
      "thumbnail_url": "/storage/jili_image/code_49 Super Ace.png",
      "min_bet": "1.00",
      "max_bet": "10000.00",
      "rtp": "96.50",
      "is_active": true
    }
  ]
}
```

### Launch Game
```http
POST /api/slots/games/1/launch
Authorization: Bearer {token}
Content-Type: application/json

{
  "demo_mode": false
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "game_url": "https://jsgame.live/game/gamesUrl?id=xxx",
    "expires_at": "2025-12-26T10:30:00Z"
  }
}
```

## User Interface

### Page Layout
```
┌─────────────────────────────────────────┐
│  🎰 Slot Games                         │
│  Play slots from top providers         │
│  [Search games...]                     │
├─────────────────────────────────────────┤
│  [🎰 All] [AYUT Gaming]                │
│  [All] [slots] [table]                 │
├─────────────────────────────────────────┤
│  🔥 Popular Games                      │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐      │
│  │IMG  │ │IMG  │ │IMG  │ │IMG  │      │
│  │Name │ │Name │ │Name │ │Name │      │
│  │JILI │ │JILI │ │JILI │ │JILI │      │
│  │AYUT │ │AYUT │ │AYUT │ │AYUT │      │
│  └─────┘ └─────┘ └─────┘ └─────┘      │
├─────────────────────────────────────────┤
│  All Games                             │
│  [Game Grid - Auto-fill columns]      │
└─────────────────────────────────────────┘
```

### Game Modal
```
┌─────────────────────────────────────────┐
│  [×]                                    │
│  ┌───────────────────────────────────┐ │
│  │                                   │ │
│  │   GAME IFRAME                     │ │
│  │   Full screen capable             │ │
│  │                                   │ │
│  └───────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

## User Flow Examples

### Example 1: New User Playing First Game
1. User visits `/slots`
2. Sees all 132 JILI games
3. Clicks "Super Ace"
4. **Not logged in** → Shows login modal
5. User registers/logs in
6. Returns to slots page
7. Clicks "Super Ace" again
8. Game launches in modal
9. User plays game
10. Clicks "End Session" when done

### Example 2: Existing User
1. User already logged in
2. Visits `/slots`
3. Searches for "Treasure"
4. Sees filtered results
5. Clicks "Dragon Treasure"
6. Game launches immediately
7. Active session bar appears
8. User plays until done
9. Clicks [×] to close modal
10. Game ends, balance updates

### Example 3: Browsing by Manufacturer
1. User visits `/slots`
2. Sees manufacturer badges (JILI)
3. Can filter/search by "JILI"
4. All JILI games displayed
5. Selects desired game
6. Plays instantly

## Technical Implementation

### Frontend Components
- **File**: `resources/js/pages/Slots.vue`
- **State Management**: Pinia stores (auth, wallet)
- **API Client**: Axios with JWT authentication
- **UI Framework**: Vue 3 Composition API

### Backend Services
- **SlotGameService**: Game management
- **SlotProviderService**: Provider management
- **SlotSessionService**: Session handling
- **SlotEncryptionService**: AYUT API encryption (AES-256-ECB)

### Security
- JWT token authentication
- Session expiration (30 minutes default)
- Wallet balance validation
- Game availability checks
- Active session tracking

## Mobile Responsive
- Grid adapts to screen size
- Touch-friendly game cards
- Modal scales appropriately
- Portrait and landscape support

## Performance
- Lazy loading of game thumbnails
- Cached provider and game data (12 hours)
- Debounced search input
- Optimized grid rendering

## Error Handling
- **No games**: Shows empty state
- **Launch failed**: Shows error message
- **Session expired**: Auto-redirects to login
- **Insufficient balance**: Shows warning
- **Provider offline**: Shows unavailable message

## Next Steps
1. Test game launch with real user
2. Monitor session handling
3. Track wallet transactions
4. Add game favorites
5. Add recent games section
6. Add game statistics

---

**Status**: ✅ Implemented and Ready
**Last Updated**: December 26, 2025
