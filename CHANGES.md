# Changes Made - Bot Improvements

## Version 2.1.0 - November 11, 2025

### 🔧 Anti-Flood Fix for Loading Bar

#### Problem:
- Loading bar updates terlalu cepat menyebabkan Telegram API error
- Error: "Too Many Requests: retry after X"
- Error: "Bad Request: message is not modified"

#### Solution:
- ✅ Added anti-flood protection di `TelegramBot.php`
- ✅ Minimum 1 detik between message edits
- ✅ Auto-wait jika update terlalu cepat
- ✅ Track last edit time per message

#### Files Modified:
- `src/utils/TelegramBot.php`
  - Added `$lastEditTime` property
  - Modified `sendLoadingMessage()` - store last edit time
  - Modified `updateLoadingMessage()` - anti-flood check with auto-wait
- `src/handlers/DownloadHandler.php`
  - Removed manual `sleep()` calls
  - Rely on built-in timing dari `updateLoadingMessage()`

#### Benefits:
- No more Telegram API errors
- Consistent timing across all updates
- Smoother user experience
- More reliable loading bar

---

### 🚀 Auto Deploy Script

#### Features:
- ✅ Auto create ZIP dengan essential files only
- ✅ Exclude unnecessary files (logs, cache, temp)
- ✅ Create empty directories di server
- ✅ Auto upload via cURL
- ✅ Progress indicator & error handling
- ✅ Fully configurable

#### Files Created:
- `deploy.php` - Main deploy script
- `deploy.config.php` - Configuration file
- `test_deploy.php` - Test script (no upload)
- `DEPLOY.md` - Complete documentation
- `QUICK_FIX_SUMMARY.md` - Quick reference

#### Files Modified:
- `.gitignore` - Exclude deploy files & ZIPs

#### Usage:
```bash
# Test deploy (create ZIP only)
php test_deploy.php

# Full deploy (create ZIP + upload)
php deploy.php
```

#### What Gets Deployed:
- Core PHP files (handlers, utils, helpers)
- Config files
- Public files (webhook, test)
- API clients
- Documentation

#### What Gets Excluded:
- logs/
- temp/
- data/cache/
- sessions/
- .git/
- node_modules/

---

## Version 2.0.0 - November 11, 2025 (Earlier)

### 📊 Loading Bar Enhancement

#### Changes:
- ✅ Replaced simple loading with animated progress bar
- ✅ Visual progress: ▰▰▰▰▰▱▱▱▱▱
- ✅ Percentage display: 0% → 100%
- ✅ 5 stages: Initialize → Validate → Fetch → Process → Prepare

#### Files Modified:
- `src/utils/TelegramBot.php`
  - Added `sendLoadingMessage()`
  - Added `updateLoadingMessage()`
- `src/handlers/DownloadHandler.php`
  - Implemented progress bar in download flow

---

### 🏢 Group Support

#### Features:
- ✅ Bot dapat digunakan di grup/supergroup
- ✅ Welcome message ketika di-add ke grup
- ✅ Auto-detect & process links di grup
- ✅ Command dengan @mention support
- ✅ Proper message filtering (hanya command & link)

#### Files Modified:
- `src/handlers/MessageHandler.php`
  - Added `$chatType` support
  - Added `handleBotAddedToGroup()`
  - Added `handleChatMemberUpdate()`
  - Added `containsLink()`
  - Added `getBotUsername()`
  - Filter message di group
- `src/handlers/CommandHandler.php`
  - Added `$chatType` parameter
  - Conditional rendering (keyboard hanya di private)
- `public/webhook.php`
  - Added routing untuk `my_chat_member` update

#### Behavior:

**Private Chat:**
- ✅ Keyboard menu
- ✅ Session mode
- ✅ All commands
- ✅ User ID display

**Group Chat:**
- ✅ No keyboard (clean)
- ✅ Commands with /
- ✅ Auto-detect links
- ✅ @mention support
- ❌ No session mode
- ❌ No User ID display

---

## Date: November 9, 2025

### Issues Fixed:

#### 1. ✅ Removed Markdown Asterisks from Messages
- **Problem**: Messages were showing asterisks (*) instead of proper formatting
- **Solution**: Removed all asterisks from captions and used plain text formatting
- **Files Modified**: 
  - `public/webhook.php` - All downloader response functions

#### 2. ✅ Fixed Emoji Corruption
- **Problem**: Emojis were being corrupted due to wrong parse_mode
- **Solution**: 
  - Changed from `MarkdownV2` to `Markdown` or removed parse_mode entirely
  - Removed unnecessary escaping functions for MarkdownV2
- **Files Modified**: 
  - `public/webhook.php` - handleStartCommand, all downloader functions

#### 3. ✅ Fixed Keyboard Buttons
- **Problem**: Keyboard buttons didn't have proper text and had corrupted emojis
- **Solution**: 
  - Fixed emoji corruption in keyboard button text
  - Properly configured reply_markup keyboard
- **Files Modified**: 
  - `public/webhook.php` - handleStartCommand

#### 4. ✅ Created User Activity Logging
- **Problem**: No logging system for user activities
- **Solution**: 
  - Created `logs/` directory
  - Added `logUserActivity()` function
  - Logs are created per user as `logs/user_{userId}.log`
  - Logs include timestamp, username, user ID, and action
- **Files Created**: 
  - `logs/` directory
- **Files Modified**: 
  - `config/config.php` - Added logs directory
  - `public/webhook.php` - Added logUserActivity() function and call in handleStartCommand

#### 5. ✅ Improved /start Message
- **Problem**: Duplicate "Halo" and missing User ID
- **Solution**: 
  - Removed duplicate greeting
  - Added User ID display with backticks for easy copying
  - Format: `📋 User ID: {userId}`
- **Files Modified**: 
  - `public/webhook.php` - handleStartCommand

### Functions Updated:

1. **handleStartCommand()** - Welcome message improvements
2. **handleYtmp3Response()** - Removed asterisks, fixed formatting
3. **handleYtmp4Response()** - Removed asterisks, fixed formatting
4. **handleSpotifyResponse()** - Removed asterisks, fixed formatting
5. **handleTiktokResponse()** - Removed asterisks, fixed formatting
6. **handleCapcutResponse()** - Removed asterisks, fixed formatting
7. **handleFacebookResponse()** - Removed asterisks, fixed formatting

### New Features:

- **User Activity Logging**: Every time a user sends `/start`, it's logged with timestamp
- **Log Format**: `YYYY-MM-DD HH:MM:SS | User: username (ID: userId) | Action: /start`
- **Log Location**: `logs/user_{userId}.log`

### Configuration Changes:

- Added `logs` directory to config.php directories array
- Logs directory is auto-created on startup like other directories

### Testing Recommendations:

1. Test `/start` command to verify:
   - No duplicate "Halo"
   - User ID is displayed correctly
   - No emoji corruption
   - Keyboard buttons work properly
   - Log file is created in `logs/user_{userId}.log`

2. Test all downloader commands:
   - `/tiktok` - Verify no asterisks in output
   - `/facebook` - Verify no asterisks in output
   - `/spotify` - Verify no asterisks in output
   - `/ytmp3` - Verify no asterisks in output
   - `/ytmp4` - Verify no asterisks in output
   - `/capcut` - Verify no asterisks in output

3. Verify emojis display correctly:
   - In welcome message
   - In keyboard buttons
   - In downloader responses

### Files Modified Summary:

1. `config/config.php` - Added logs directory
2. `public/webhook.php` - Multiple function updates
3. `logs/` - New directory created

### Example Log Entry:

```
2025-11-09 14:30:45 | User: john_doe (ID: 123456789) | Action: /start
2025-11-09 14:31:12 | User: john_doe (ID: 123456789) | Action: /start
```

### Example New Welcome Message:

```
🦊 WELCOME TO JOSS HELPER!

═══════════════════════════

👋 Halo john_doe!
Selamat datang di JOSS HELPER BOT!
📋 User ID: `123456789`

📊 BOT STATISTICS
┣ Hari ini: 10 requests
┣ Minggu ini: 50 requests
┣ Bulan ini: 200 requests
┗ Total: 1,000 requests

✅ Sukses: 950 | ❌ Gagal: 50

💭 Quote of the Day
_Success is not final, failure is not fatal._
— Winston Churchill

═══════════════════════════

🎯 QUICK ACCESS
Gunakan keyboard di bawah untuk
akses cepat semua fitur bot!

🚀 Let's get started!
```

---

All issues have been resolved successfully! 🎉
