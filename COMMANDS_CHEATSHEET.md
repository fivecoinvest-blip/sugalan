# 🎰 Slot Game Commands - Quick Cheatsheet

## 📋 Three Main Commands

### 1️⃣ Fetch Providers
```bash
php artisan slots:fetch-providers [--save]
```
**What it does:** Gets list of game providers (JILI, PG Soft, etc.)  
**Use --save:** Automatically saves to database

### 2️⃣ Sync Games
```bash
php artisan slots:sync [--provider=CODE] [--download-assets]
```
**What it does:** Fetches games from API and saves to database  
**Use --provider:** Sync only one provider (e.g., JILI)  
**Use --download-assets:** Automatically download images after sync

### 3️⃣ Download Assets
```bash
php artisan slots:download-assets [--provider=CODE] [--force]
```
**What it does:** Downloads game images and stores locally  
**Use --provider:** Download only for one provider  
**Use --force:** Re-download existing images

---

## 🚀 Common Use Cases

### First Time Setup
```bash
# Step 1: Add providers to database
php artisan slots:fetch-providers --save

# Step 2: Sync all games and download images
php artisan slots:sync --download-assets
```

### Add Single Provider
```bash
# Add JILI games with images
php artisan slots:sync --provider=JILI --download-assets
```

### Refresh Images
```bash
# Re-download all images
php artisan slots:download-assets --force

# Re-download JILI images only
php artisan slots:download-assets --provider=JILI --force
```

### Update Game List
```bash
# Sync new games (without re-downloading images)
php artisan slots:sync

# Sync and download new game images
php artisan slots:sync --download-assets
```

---

## 📊 Available Providers

| Name           | Code   | Brand ID | Command Example |
|----------------|--------|----------|-----------------|
| JILI Games     | JILI   | 48       | `--provider=JILI` |
| PG Soft        | PGSOFT | 52       | `--provider=PGSOFT` |
| Pragmatic Play | PP     | 35       | `--provider=PP` |
| CQ9            | CQ9    | 15       | `--provider=CQ9` |
| JDB            | JDB    | 28       | `--provider=JDB` |
| Spade Gaming   | SG     | 41       | `--provider=SG` |
| FC             | FC     | 19       | `--provider=FC` |

---

## 🔧 Maintenance Commands

### Check Storage Link
```bash
ls -la public/storage
```

### Create Storage Link (if missing)
```bash
php artisan storage:link
```

### Fix Permissions
```bash
chmod -R 775 storage/app/public
```

### Check Storage Size
```bash
du -sh storage/app/public/slot-games/
```

### Count Games
```bash
php artisan tinker
>>> App\Models\SlotGame::count()
>>> App\Models\GameProvider::with('slotGames')->get()
```

---

## 🎯 Quick Troubleshooting

**No providers showing?**
```bash
php artisan slots:fetch-providers --save
```

**Games not syncing?**
- Check provider has brand_id: `SELECT * FROM game_providers;`
- Try manual API URL in browser

**Images not loading?**
```bash
php artisan storage:link
chmod -R 775 storage/app/public
```

**Want to start over?**
```bash
# Delete all games and providers
php artisan tinker
>>> App\Models\SlotGame::truncate()
>>> App\Models\GameProvider::truncate()

# Re-run setup
php artisan slots:fetch-providers --save
php artisan slots:sync --download-assets
```

---

## 📁 File Locations

**Commands:**
- `app/Console/Commands/FetchGameProvidersCommand.php`
- `app/Console/Commands/SyncSlotGamesCommand.php`
- `app/Console/Commands/DownloadSlotGameAssets.php`

**Storage:**
- Images: `storage/app/public/slot-games/`
- Public URL: `/storage/slot-games/`

**Documentation:**
- Full guide: `SLOT_ASSET_MANAGEMENT_GUIDE.md`
- Quick start: `QUICK_START_SLOTS.md`
- Summary: `PHASE_8_ASSET_MANAGEMENT_SUMMARY.md`

---

## ⚡ One-Liner Complete Setup

```bash
php artisan slots:fetch-providers --save && php artisan slots:sync --download-assets
```

This will:
1. ✅ Add all 7 providers to database
2. ✅ Sync all games from all providers
3. ✅ Download all game images
4. ✅ Update database with local paths

**Time:** 5-10 minutes depending on internet speed

---

## 💡 Pro Tips

1. **Run sync during off-peak hours** - Large downloads
2. **Use --provider flag** - Faster for testing single provider
3. **Schedule daily sync** - Keep games up to date
4. **Monitor storage** - Games can use 1-5GB space
5. **Test one provider first** - Verify setup before syncing all

---

**Need help?** See full documentation in `SLOT_ASSET_MANAGEMENT_GUIDE.md`

