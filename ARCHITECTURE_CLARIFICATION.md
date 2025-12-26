# Slot Games Architecture Clarification

## Overview
The system has been updated to properly distinguish between:
- **Aggregators** (e.g., AYUT API)
- **Game Manufacturers/Providers** (e.g., JILI, PG Soft, Pragmatic Play)
- **Game Types/Categories** (e.g., slots, table, crash)

## Architecture

```
┌─────────────────────────────────────────────────┐
│ AYUT API (Aggregator)                           │
│ • API Endpoint: https://jsgame.live             │
│ • Provides access to multiple manufacturers     │
│ • Handles authentication, wallet, transactions  │
└─────────────────────────────────────────────────┘
                    │
                    ├─── JILI (Manufacturer)
                    │    • 132 slot games
                    │
                    ├─── PG Soft (Future)
                    │    • TBD games
                    │
                    └─── Pragmatic Play (Future)
                         • TBD games
```

## Database Schema

### `slot_providers` Table
Represents **aggregators** that provide access to games.

| Field | Description | Example |
|-------|-------------|---------|
| `code` | Aggregator identifier | "AYUT" |
| `name` | Aggregator display name | "AYUT Gaming" |
| `api_url` | Aggregator API endpoint | "https://jsgame.live" |
| `agency_uid` | Your agency ID with aggregator | "4fcbdc0bf258b53d8fa02d85c6ddbdf6" |
| `aes_key` | Encryption key for API calls | "fd1e3a6a4b3dc050c7f9238c49bf5f56" |

### `slot_games` Table
Represents individual games with clear attribution.

| Field | Description | Example |
|-------|-------------|---------|
| `provider_id` | References the **aggregator** | 1 (AYUT) |
| `manufacturer` | **Game manufacturer/provider** | "JILI" |
| `category` | **Game type** | "slots" |
| `game_id` | Manufacturer's game UID | "24da72b49b0dd0e5cbef9579d09d8981" |
| `name` | Game display name | "Super Ace" |

## Game Launch Flow

1. **User Action**: User clicks "Play Super Ace"
2. **Backend Processing**: 
   - Identifies game: JILI's Super Ace
   - Identifies aggregator: AYUT
   - Prepares launch payload
3. **API Request**: POST to AYUT API (`https://jsgame.live/game/v1`)
4. **AYUT Processing**: 
   - AYUT validates request
   - AYUT launches JILI game
   - Returns game URL
5. **User Experience**: User plays JILI game via AYUT platform

## Code Updates

### Models

#### SlotProvider Model
```php
/**
 * SlotProvider Model
 * 
 * Represents a slot game aggregator (e.g., AYUT API).
 * An aggregator provides access to multiple game manufacturers/providers.
 */
class SlotProvider extends Model
```

#### SlotGame Model
```php
/**
 * SlotGame Model
 * 
 * Architecture:
 * - provider_id: References the aggregator (e.g., AYUT API)
 * - manufacturer: The actual game provider/manufacturer (e.g., JILI, PG Soft)
 * - category: Game type (e.g., slots, table, crash)
 */
class SlotGame extends Model
{
    protected $fillable = [
        'provider_id',      // Aggregator
        'manufacturer',     // Game provider
        'category',         // Game type
        // ... other fields
    ];
    
    // Scope for filtering by manufacturer
    public function scopeManufacturer($query, $manufacturer)
    {
        return $query->where('manufacturer', $manufacturer);
    }
}
```

### Database Migration
```php
// Migration: add_manufacturer_to_slot_games_table
Schema::table('slot_games', function (Blueprint $table) {
    $table->string('manufacturer', 100)->nullable()->after('provider_id')
        ->comment('Game provider/manufacturer (JILI, PG Soft, etc.)');
    $table->index('manufacturer');
});
```

### Admin UI Updates

**Games Table Columns:**
- Thumbnail
- Game Name
- **Aggregator** (formerly "Provider") - Shows AYUT
- **Manufacturer** (NEW) - Shows JILI
- Category - Shows slots
- RTP
- Min/Max Bet
- Status
- Actions

## Usage Examples

### Query Games by Manufacturer
```php
// Get all JILI games
$jiliGames = SlotGame::manufacturer('JILI')->get();

// Get active JILI slot games
$jiliSlots = SlotGame::manufacturer('JILI')
    ->category('slots')
    ->active()
    ->get();
```

### Add New Manufacturer Games
When AYUT adds support for PG Soft or Pragmatic Play:

```php
SlotGame::create([
    'provider_id' => 1,              // AYUT aggregator
    'manufacturer' => 'PG Soft',     // Game manufacturer
    'game_id' => '...',
    'name' => 'Mahjong Ways',
    'category' => 'slots',
    // ... other fields
]);
```

## Benefits

1. **Clarity**: Clear distinction between aggregator, manufacturer, and game type
2. **Scalability**: Easy to add more manufacturers through AYUT
3. **Filtering**: Filter games by manufacturer (JILI, PG Soft, etc.)
4. **Reporting**: Track performance by manufacturer
5. **UI**: Display manufacturer badges in game lists
6. **SEO**: Better game attribution and metadata

## Admin Panel Features

### View Games
- **URL**: http://127.0.0.1:8000/admin/slots/games
- **Columns**: Shows Aggregator (AYUT) and Manufacturer (JILI) separately
- **Filters**: Can filter by aggregator, manufacturer, or category

### Manage Providers
- **URL**: http://127.0.0.1:8000/admin/slots/providers
- **Shows**: List of aggregators (currently AYUT)
- **Actions**: Add, edit, delete, sync games

## API Endpoints

### Get Games
```http
GET /api/slots/games?manufacturer=JILI
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Super Ace",
      "provider_name": "AYUT Gaming",
      "manufacturer": "JILI",
      "category": "slots",
      "thumbnail_url": "/storage/jili_image/code_49 Super Ace.png",
      // ... other fields
    }
  ]
}
```

## Current Statistics

- **Aggregators**: 1 (AYUT)
- **Manufacturers**: 1 (JILI)
- **Game Types**: 1 (slots)
- **Total Games**: 132

## Future Expansion

When adding more manufacturers through AYUT:

1. **AYUT announces new provider** (e.g., PG Soft)
2. **Create seeder** for new manufacturer games
3. **Run seeder**:
   ```bash
   php artisan db:seed --class=PgSoftGamesSeeder
   ```
4. **Games appear** with:
   - `provider_id`: 1 (AYUT)
   - `manufacturer`: "PG Soft"
   - `category`: "slots"

All working through the same AYUT aggregator API!

---

**Updated**: December 26, 2025
**Status**: ✅ Implemented and Verified
