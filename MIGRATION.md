# Migration Guide - NekoLabs API & Logging System

## 🚀 Overview

Bot ini telah di-refactor dengan perubahan besar berikut:

1. **✅ Migrasi ke NekoLabs API** - All-in-one downloader untuk semua platform
2. **📝 Sistem Logging Lengkap** - Error logging & per-user activity logs
3. **👥 User Management** - Tracking users untuk broadcast
4. **🏗️ Modular Architecture** - Kode yang lebih terstruktur dan maintainable

---

## 📦 What's New

### 1. NekoLabs API Integration

**NekoLabsClient** - Client baru untuk API all-in-one downloader

- ✅ Mendukung semua platform: TikTok, YouTube, Instagram, Facebook, Twitter, Spotify, dll
- ✅ Auto-detect platform dari URL
- ✅ Retry mechanism dengan exponential backoff
- ✅ Mendukung multiple API versions (v1, v2, v3, v4)
- ✅ Rate limiting handling

**File:** `src/api/NekoLabsClient.php`

**Supported Platforms:**
- TikTok
- YouTube
- Instagram
- Facebook
- Twitter/X
- Spotify
- SoundCloud
- CapCut
- Pinterest
- Reddit
- Threads
- SnackVideo

### 2. Logging System

#### Logger (Application Logs)

**File:** `src/utils/Logger.php`

Logs disimpan di: `logs/app-{date}.log`

**Methods:**
```php
Logger::error($message, $context);    // Log error
Logger::warning($message, $context);  // Log warning
Logger::info($message, $context);     // Log info
Logger::debug($message, $context);    // Log debug
Logger::exception($e, $context);      // Log exception
Logger::apiRequest($api, $endpoint, $params);  // Log API request
Logger::apiResponse($api, $success, $data);    // Log API response
```

**Example Log Entry:**
```
[2025-11-13 10:30:45] [ERROR] NekoLabs API error | {"chat_id":123456,"error":"Rate limit exceeded"}
```

#### UserLogger (Per-User Activity Logs)

**File:** `src/utils/UserLogger.php`

Logs disimpan di: `logs/{user_id}.txt`

**Methods:**
```php
UserLogger::log($userId, $action, $details);           // General log
UserLogger::logCommand($userId, $command, $params);    // Log command
UserLogger::logDownload($userId, $platform, $url);     // Log download
UserLogger::logError($userId, $error, $context);       // Log user error
UserLogger::logHarExtraction($userId, $action, $data); // Log HAR action
```

**Example User Log:**
```
[2025-11-13 10:30:45] COMMAND: /start
[2025-11-13 10:31:20] DOWNLOAD | {"platform":"tiktok","url":"https://..."}
[2025-11-13 10:31:25] Download successful | {"source":"tiktok","type":"video"}
```

### 3. User Management

**File:** `src/utils/UserManager.php`

User data disimpan di: `data/users.json`

**Features:**
- ✅ Auto-register users saat pertama kali menggunakan bot
- ✅ Track first_seen, last_seen, request_count
- ✅ User blocking/unblocking
- ✅ Admin management
- ✅ Export to CSV
- ✅ Statistics

**Structure:**
```json
{
  "123456": {
    "user_id": 123456,
    "username": "john_doe",
    "first_seen": "2025-11-13 10:00:00",
    "last_seen": "2025-11-13 10:30:00",
    "request_count": 5,
    "is_blocked": false,
    "is_admin": false,
    "chat_type": "private",
    "last_platform": "tiktok"
  }
}
```

**Methods:**
```php
UserManager::addUser($userId, $userData);        // Add/update user
UserManager::getUser($userId);                   // Get user data
UserManager::getAllUsers();                      // Get all users
UserManager::getUserIds();                       // Get all user IDs (for broadcast)
UserManager::getActiveUserIds();                 // Get active (non-blocked) user IDs
UserManager::blockUser($userId);                 // Block user
UserManager::unblockUser($userId);               // Unblock user
UserManager::setAdmin($userId, $isAdmin);        // Set admin status
UserManager::getStats();                         // Get user statistics
UserManager::exportToCsv($filename);             // Export to CSV
```

---

## 📂 New File Structure

```
fictional-garbanzo/
├── src/
│   ├── api/
│   │   ├── NekoLabsClient.php         [NEW] ✨
│   │   ├── SSSTikProClient.php         (unchanged)
│   │   └── ferdev_backup/              (deprecated)
│   │
│   ├── handlers/
│   │   ├── DownloadHandler.php         [UPDATED] 🔄 - Using NekoLabs
│   │   ├── CommandHandler.php          [UPDATED] 🔄 - Added logging
│   │   └── MessageHandler.php          [UPDATED] 🔄 - Added logging
│   │
│   ├── responses/
│   │   ├── NekoLabsResponseHandler.php [NEW] ✨
│   │   └── ...
│   │
│   └── utils/
│       ├── Logger.php                  [NEW] ✨
│       ├── UserLogger.php              [NEW] ✨
│       └── UserManager.php             [NEW] ✨
│
├── logs/                               [NEW] 📁
│   ├── app-2025-11-13.log             (application logs)
│   ├── 123456.txt                      (user activity logs)
│   └── ...
│
├── data/                               [NEW] 📁
│   ├── users.json                      (user database)
│   └── uptime.txt
│
├── .gitignore                          [NEW] 📄
└── MIGRATION.md                        [NEW] 📄 (this file)
```

---

## 🔧 Configuration Changes

### config/config.php

**Added:**
```php
// NekoLabs API Settings
'NEKOLABS_API_VERSION' => env('NEKOLABS_API_VERSION', 'v1'),

// Directories
'directories' => [
    // ... existing directories
    'logs' => __DIR__ . '/../logs',
    'data' => __DIR__ . '/../data'
],
```

**Deprecated:**
```php
// Ferdev API Key (DEPRECATED - Use NekoLabs instead)
'FERDEV_API_KEY' => env('FERDEV_API_KEY', ''),
```

### .env (New Variables)

```bash
# NekoLabs API Configuration
NEKOLABS_API_VERSION=v1   # Options: v1, v2, v3, v4
```

---

## 🎯 Usage Examples

### 1. Basic Download (Auto-detect Platform)

```bash
# User sends any supported URL
https://vm.tiktok.com/ZMA7H3EtC/

# Bot will:
# 1. Auto-detect platform (TikTok)
# 2. Call NekoLabs API
# 3. Log the request
# 4. Send the media
# 5. Update user statistics
```

### 2. Logging Examples

**Check Application Logs:**
```bash
tail -f logs/app-$(date +%Y-%m-%d).log
```

**Check User Activity:**
```bash
cat logs/123456.txt
```

### 3. User Management

**Get All Active Users (For Broadcast):**
```php
$userIds = UserManager::getActiveUserIds();
foreach ($userIds as $userId) {
    $bot->sendMessage($userId, "Broadcast message here!");
}
```

**Get Statistics:**
```php
$stats = UserManager::getStats();
/*
Array (
    [total_users] => 150
    [active_users] => 145
    [blocked_users] => 5
    [admin_users] => 2
    [total_requests] => 1250
)
*/
```

**Block/Unblock User:**
```php
UserManager::blockUser(123456);
UserManager::unblockUser(123456);
```

---

## 🔀 API Version Switching

NekoLabs menyediakan 4 versi API (v1, v2, v3, v4). Anda bisa switch version:

### Via .env:
```bash
NEKOLABS_API_VERSION=v2
```

### Via Code:
```php
$handler = new DownloadHandler($bot, $sessionManager, $config);
$handler->handleWithVersion($chatId, $url, 'v3');
```

### Test API Connection:
```php
$handler->testApi($chatId);
```

---

## 🗑️ Deprecated / Removed

### ❌ Ferdev API
- **File:** `src/api/ferdev_backup/`
- **Status:** DEPRECATED
- **Reason:** Migrasi ke NekoLabs API
- **Action:** Akan dihapus di update selanjutnya

### ⚠️ Old Logging
- **File:** Inline `error_log()` calls
- **Status:** Replaced with Logger/UserLogger
- **Action:** Sedang dalam proses migrasi

---

## 📊 Benefits

### Before (Old System)
```
❌ Multiple API clients untuk berbeda platform
❌ Logging dengan error_log() tidak terstruktur
❌ Tidak ada user tracking
❌ Sulit maintenance
❌ Error handling tidak konsisten
```

### After (New System)
```
✅ Single API client untuk ALL platforms
✅ Structured logging (app + per-user)
✅ Complete user management & broadcast ready
✅ Modular & maintainable
✅ Consistent error handling
✅ Auto-retry & rate limiting
✅ Better debugging
```

---

## 🚦 Migration Checklist

- [x] NekoLabsClient created
- [x] Logger system implemented
- [x] UserLogger system implemented
- [x] UserManager implemented
- [x] DownloadHandler refactored
- [x] CommandHandler updated with logging
- [x] MessageHandler updated with logging
- [x] Config updated
- [x] .gitignore created
- [x] Directories created (logs/, data/)
- [ ] Remove deprecated Ferdev API code
- [ ] Add broadcast command for admins
- [ ] Add user statistics command
- [ ] Update README.md

---

## 📝 Notes

1. **Logs Cleanup:** Logger memiliki auto-cleanup untuk logs lama (30 hari untuk app logs, 90 hari untuk user logs)
2. **User Privacy:** User logs tidak di-commit ke git (ada di .gitignore)
3. **Performance:** Logging adalah async dan tidak mempengaruhi response time
4. **Backwards Compatible:** Bot masih support command lama (tiktok, ytmp3, dll)

---

## 🆘 Troubleshooting

### Logs tidak tercreate?
```bash
# Pastikan directory ada dan writable
mkdir -p logs data
chmod 755 logs data
```

### API error 429 (Rate Limit)?
- NekoLabs API punya rate limit
- Bot sudah implement retry dengan exponential backoff
- Jika masih error, coba switch ke version lain (v2, v3, v4)

### User tidak tercatat di users.json?
- Pastikan directory `data/` ada dan writable
- Check logs: `tail -f logs/app-$(date +%Y-%m-%d).log`

---

## 📚 Additional Resources

- **NekoLabs API Docs:** https://api.nekolabs.web.id
- **Support Platforms:** All major social media platforms
- **API Versions:** v1 (default), v2, v3, v4

---

**Last Updated:** 2025-11-13
**Version:** 2.2.0
**Migration Status:** ✅ Complete
