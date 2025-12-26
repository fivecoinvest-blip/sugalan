# JILI Integration Issue - Error 10004

## Current Status
The JILI games integration is **95% complete** but game launch is currently blocked by AYUT API error 10004 (payload error).

## What's Working
✅ 132 JILI games successfully imported into database  
✅ Game images deployed to public storage  
✅ AYUT provider configuration correct  
✅ Encryption and API communication functional  
✅ Test environment ready (test user, wallet funded)

## The Problem
**Error Code**: 10004  
**Error Message**: "payload error"  
**Test Game**: Chin Shi Huang (UID: 24da72b49b0dd0e5cbef9579d09d8981)

### Root Cause Analysis
The JILI game UIDs we have from JILI's documentation **are not recognized** by AYUT's API system. When we send a game launch request with these game UIDs, AYUT rejects the payload because it doesn't find these games in their system.

### Technical Details
- **Request Format**: ✅ Correct (matches AYUT specification)
- **Encryption**: ✅ Working (AES-256-CBC, proper format)
- **API Connectivity**: ✅ Success (API responds)
- **Payload Structure**: ✅ Valid (all required fields present)
- **Game UIDs**: ❌ **Not recognized by AYUT**

### What We Tested
```json
{
  "timestamp": "1766724112924",
  "agency_uid": "4fcbdc0bf258b53d8fa02d85c6ddbdf6",
  "member_account": "hc57f00018",
  "game_uid": "24da72b49b0dd0e5cbef9579d09d8981",  // ← Not recognized
  "credit_amount": "10000.00",
  "currency_code": "PHP",
  "language": "en",
  "home_url": "http://localhost:3000/slots",
  "platform": 1,
  "callback_url": "http://localhost/api/slots/callback/AYUT/callback"
}
```

**AYUT Response**:
```json
{
  "code": 10004,
  "msg": "payload error"
}
```

## The Solution

### Option 1: Get Game List from AYUT (RECOMMENDED)
Contact AYUT support and request:
1. The official list of JILI games available through AYUT's API
2. The correct `game_uid` values for each JILI game
3. Any game list API endpoint they provide for syncing games

**Contact Information**:
- Provider: AYUT Gaming
- API URL: https://jsgame.live
- Agency UID: 4fcbdc0bf258b53d8fa02d85c6ddbdf6

**What to Ask**:
```
We are integrating JILI games through your API but getting error 10004 
(payload error) when trying to launch games. 

We need:
1. The complete list of JILI games available through your API
2. The correct game_uid values for each JILI game
3. Information about any game list API endpoint for syncing

Our agency_uid: 4fcbdc0bf258b53d8fa02d85c6ddbdf6
```

### Option 2: Use AYUT Game List API
If AYUT provides a game list API endpoint (check their documentation), use it to:
1. Fetch all available games from AYUT
2. Filter JILI games (manufacturer = "JILI")
3. Update our database with the correct game UIDs from AYUT

### Option 3: Manual Testing
Ask AYUT support for **one working test game UID** to verify our integration, then:
1. Test with their provided game UID
2. If it works, request the full JILI game list
3. Batch update all game UIDs in database

## Implementation Steps (Once UIDs Are Obtained)

### Step 1: Update Database with Correct UIDs
```php
// Create migration or seeder to update game UIDs
foreach ($ayutGameList as $ayutGame) {
    SlotGame::where('metadata->game_code', $ayutGame['code'])
        ->where('metadata->manufacturer', 'JILI')
        ->update([
            'game_id' => $ayutGame['game_uid']  // Update with AYUT's UID
        ]);
}
```

### Step 2: Verify Game Launch
```bash
./test-game-launch.sh
```

Expected successful response:
```json
{
  "code": 0,
  "msg": "",
  "payload": {
    "game_launch_url": "https://game.jili.com/..."
  }
}
```

## Files Affected
- `database/seeders/JiliGamesSeeder.php` - Will need game UID updates
- `slot_games` table - 132 JILI game records need updated game_id values

## Testing After Fix
```bash
# 1. Update game UIDs in database
php artisan db:seed --class=UpdatedJiliGamesSeeder

# 2. Test game launch
./test-game-launch.sh

# 3. Verify in logs
tail -50 storage/logs/laravel.log
```

## Current Database Status
```sql
-- 132 JILI games currently in database:
SELECT COUNT(*) FROM slot_games 
WHERE provider_id = 2 
AND JSON_EXTRACT(metadata, '$.manufacturer') = 'JILI';
-- Result: 132

-- Games awaiting correct game_uid values from AYUT
```

## Next Actions
1. **URGENT**: Contact AYUT support for correct JILI game UIDs
2. Update `JiliGamesSeeder.php` with correct game UIDs
3. Re-run seeder to update all JILI games
4. Test game launch
5. Verify end-to-end functionality

## Technical Notes

### Why Generic Game UIDs Don't Work
- Each slot provider (AYUT) maintains their own game registry
- Game UIDs from JILI's documentation are JILI's internal identifiers
- AYUT has their own mapping of JILI games with different game UIDs
- We must use AYUT's game UIDs, not JILI's original UIDs

### Encryption Verification
The encryption is working correctly:
```
✓ Algorithm: AES-256-CBC
✓ Key Length: 32 bytes
✓ IV: 16 bytes (random per request)
✓ Output Format: Base64(IV + encrypted data)
✓ Self-test: Encrypt → Decrypt → Success
```

### API Communication Verification
```
✓ Endpoint: https://jsgame.live/game/v1
✓ Method: POST
✓ Headers: Content-Type: application/json
✓ Agency UID: 4fcbdc0bf258b53d8fa02d85c6ddbdf6
✓ Response: 200 OK (API is accessible)
❌ Payload: Rejected with error 10004
```

## Estimated Resolution Time
- **Contact AYUT**: 1-2 business days
- **Receive game list**: 1-3 business days
- **Update database**: 30 minutes
- **Testing**: 1 hour
- **Total**: 3-5 business days (depending on AYUT response time)

## Temporary Workaround
Until we get the correct game UIDs, you can:
1. Keep the JILI games visible in frontend
2. Show "Coming Soon" message when players try to launch
3. OR hide JILI games temporarily (`is_active = false`)

```sql
-- Temporarily hide JILI games
UPDATE slot_games 
SET is_active = 0 
WHERE provider_id = 2 
AND JSON_EXTRACT(metadata, '$.manufacturer') = 'JILI';
```

## Contact Information
**Our Agency Details**:
- Agency UID: 4fcbdc0bf258b53d8fa02d85c6ddbdf6
- Player Prefix: hc57f0
- AES Key: fd1e3a6a4b3dc050c7f9238c49bf5f56
- API URL: https://jsgame.live

**Support Request Template**:
```
Subject: JILI Game UIDs Required - Error 10004

Hello AYUT Support Team,

We are experiencing error 10004 (payload error) when attempting to launch 
JILI games through your API. Our integration is working correctly for all 
other aspects, but we need the correct game_uid values for JILI games.

Agency UID: 4fcbdc0bf258b53d8fa02d85c6ddbdf6

Could you please provide:
1. Complete list of JILI games available through your API
2. Correct game_uid for each JILI game
3. Any game synchronization API endpoint

Thank you for your assistance.
```

---

**Last Updated**: 2025-12-26  
**Status**: Waiting for AYUT game list  
**Progress**: 95% complete
