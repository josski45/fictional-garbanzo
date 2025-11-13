# 🔧 MAINTENANCE MODE - Panduan Lengkap

## 📋 Overview

Sistem Maintenance Mode memungkinkan admin untuk menonaktifkan bot sementara untuk semua user (kecuali admin yang di-whitelist) dengan pesan custom.

---

## ✨ Fitur Utama

### 1. **Dynamic Maintenance Mode**
- ✅ Enable/disable kapan saja via command
- ✅ Otomatis cek di webhook.php sebelum proses request
- ✅ Non-blocking: admin tetap bisa akses

### 2. **Scheduled Maintenance**
- ✅ Set durasi (menit) untuk auto-disable
- ✅ Tampilkan estimasi selesai ke user
- ✅ Auto-disable ketika waktu habis

### 3. **Admin Whitelist**
- ✅ Admin yang enable maintenance otomatis di-whitelist
- ✅ Bisa akses bot normal saat maintenance
- ✅ Logged sebagai "bypassed maintenance"

### 4. **Custom Messages**
- ✅ Set pesan maintenance custom
- ✅ Otomatis tambahkan estimasi waktu
- ✅ Support Markdown formatting

### 5. **Maintenance Broadcast**
- ✅ Kirim notifikasi ke semua user sebelum maintenance
- ✅ Template pesan siap pakai
- ✅ Track sent/failed messages

---

## 🎮 Cara Menggunakan

### Enable Maintenance (Manual)

```
/maintenanceon
```

**Hasil:**
- Maintenance mode: ON
- Durasi: Manual (harus disable manual)
- Admin yang enable: Di-whitelist otomatis
- User lain: Lihat pesan maintenance

---

### Enable Maintenance (Dengan Durasi)

```
/maintenanceon 30
```

**Hasil:**
- Maintenance mode: ON
- Durasi: 30 menit
- Auto-disable: Otomatis OFF setelah 30 menit
- Estimasi selesai: Ditampilkan ke user

---

### Enable Maintenance (Dengan Custom Message)

```
/maintenanceon 60 Bot sedang upgrade ke versi baru! Mohon tunggu ya 🚀
```

**Hasil:**
- Maintenance mode: ON
- Durasi: 60 menit
- Custom message: "Bot sedang upgrade ke versi baru! Mohon tunggu ya 🚀"
- Estimasi: Auto-tampil di message

---

### Disable Maintenance

```
/maintenanceoff
```

**Hasil:**
- Maintenance mode: OFF
- Bot kembali normal untuk semua user

---

### Cek Status Maintenance

```
/maintenancestatus
```

**Tampilan:**
```
🔧 MAINTENANCE MODE STATUS

Status: 🔴 ENABLED
Enabled at: 2025-11-13 10:30:45
Enabled by: 123456789
Scheduled end: 2025-11-13 11:00:45
Remaining: ~28 minutes

Message:
🔧 Bot sedang dalam maintenance.
Mohon tunggu beberapa saat.

⏰ Estimasi selesai: 11:00

Whitelisted admins: 1
```

---

### Set Custom Message

```
/maintenancemsg Bot sedang upgrade database. Estimasi 1 jam ⏰
```

**Hasil:**
- Message maintenance diubah
- Akan digunakan untuk maintenance berikutnya
- Bisa diubah kapan saja (bahkan saat maintenance aktif)

---

### Broadcast Maintenance Notice

```
/maintenancebroadcast
```

**Hasil:**
- Kirim notifikasi ke semua user (non-blocked)
- Template pesan maintenance siap pakai
- Report: sent/failed count

**Template Default:**
```
🔧 MAINTENANCE NOTICE

Bot akan mengalami maintenance dalam waktu dekat.

⏰ Durasi: ~30-60 menit
📅 Waktu: Segera

Mohon maaf atas ketidaknyamanannya.
Terima kasih! 🙏
```

---

## 🔄 Workflow Maintenance

### Skenario 1: Maintenance Terjadwal

```
1. Admin: /maintenancebroadcast
   → Kirim notifikasi ke semua user

2. Tunggu 5-10 menit (biar user selesai download)

3. Admin: /maintenanceon 60 Upgrading system...
   → Enable maintenance mode (60 menit)

4. Lakukan upgrade/perbaikan
   → Admin tetap bisa test bot

5. Selesai sebelum 60 menit?
   → /maintenanceoff (manual disable)

   Atau biarkan auto-disable setelah 60 menit
```

---

### Skenario 2: Emergency Maintenance

```
1. Ada bug critical yang harus diperbaiki!

2. Admin: /maintenanceon Emergency bug fix!
   → Langsung enable (manual)

3. Fix bug

4. Test (admin bisa test saat maintenance)

5. Admin: /maintenanceoff
   → Disable maintenance
```

---

### Skenario 3: Update Message Saat Maintenance

```
1. Maintenance sudah jalan

2. Ternyata butuh waktu lebih lama

3. Admin: /maintenancemsg Maaf maintenance diperpanjang. Estimasi 30 menit lagi 🙏
   → Update message (langsung berlaku)

4. User berikutnya yang coba akses bot:
   → Lihat message baru
```

---

## 🔍 Technical Details

### File Structure

```
src/
├── utils/
│   └── MaintenanceManager.php     [NEW] - Maintenance logic
│
├── handlers/
│   ├── AdminHandler.php           [UPDATED] - 5 new methods
│   └── CommandHandler.php         [UPDATED] - 5 new commands
│
└── public/
    └── webhook.php                [REWRITTEN] - Maintenance checking

data/
└── maintenance.json               [AUTO-CREATED] - Maintenance state
```

---

### MaintenanceManager.php Methods

```php
// Check if maintenance enabled
MaintenanceManager::isEnabled() : bool

// Check if user can bypass
MaintenanceManager::canBypass($userId) : bool

// Enable maintenance
MaintenanceManager::enable($adminId, $message = null, $durationMinutes = null)

// Disable maintenance
MaintenanceManager::disable($adminId)

// Get maintenance message
MaintenanceManager::getMessage() : string

// Set custom message
MaintenanceManager::setMessage($message)

// Whitelist management
MaintenanceManager::addToWhitelist($userId)
MaintenanceManager::removeFromWhitelist($userId)

// Get status
MaintenanceManager::getStatus() : array
MaintenanceManager::getStatusMessage() : string (formatted)
```

---

### webhook.php Flow

```
┌──────────────────────────────────┐
│    Telegram sends update         │
└────────────┬─────────────────────┘
             │
             ▼
┌──────────────────────────────────┐
│    webhook.php receives          │
└────────────┬─────────────────────┘
             │
             ▼
┌──────────────────────────────────┐
│  Check: Maintenance enabled?     │
└──────┬───────────────────┬───────┘
       │ YES               │ NO
       ▼                   ▼
┌──────────────────┐  ┌──────────────────┐
│ Check whitelist  │  │ Process normal   │
└──────┬───────────┘  │                  │
       │              │                  │
   ┌───┴────┐         │                  │
   │ Admin? │         │                  │
   └───┬────┘         │                  │
       │              │                  │
    YES│   NO         │                  │
       │   │          │                  │
       ▼   ▼          ▼                  │
  ┌────┐ ┌────────┐ ┌────────────────┐ │
  │Pass│ │ Block  │ │   Continue     │ │
  │    │ │ + Msg  │ │   to index.php │ │
  └────┘ └────────┘ └────────────────┘ │
       │              │                  │
       └──────────────┴──────────────────┘
```

---

### maintenance.json Structure

```json
{
    "enabled": true,
    "message": "🔧 Bot sedang dalam maintenance.\n\nMohon tunggu beberapa saat.",
    "enabled_at": "2025-11-13 10:30:45",
    "enabled_by": "123456789",
    "whitelist": [
        123456789
    ],
    "scheduled_end": 1699873845
}
```

**Fields:**
- `enabled` - true/false
- `message` - Pesan yang ditampilkan ke user
- `enabled_at` - Timestamp kapan diaktifkan
- `enabled_by` - User ID admin yang mengaktifkan
- `whitelist` - Array user IDs yang bisa bypass
- `scheduled_end` - Unix timestamp untuk auto-disable (null jika manual)

---

## 📊 Logging

### Logger Entries

```
[INFO] Maintenance mode enabled (admin_id: 123456789, duration: 30, scheduled_end: 2025-11-13 11:00:00)
[INFO] Request blocked - Maintenance mode (user_id: 987654321, chat_id: 987654321)
[INFO] Admin bypassed maintenance mode (user_id: 123456789)
[INFO] Maintenance mode disabled (admin_id: 123456789)
```

### User Logs

```
logs/123456789.txt:
[2025-11-13 10:30:45] COMMAND | /maintenanceon | {"duration":30,"has_custom_message":"yes"}
[2025-11-13 11:00:50] COMMAND | /maintenanceoff
```

---

## 🎯 Best Practices

### 1. **Selalu Broadcast Dulu**
```
❌ BAD: Langsung enable maintenance tanpa notifikasi
✅ GOOD: /maintenancebroadcast → tunggu 5-10 menit → /maintenanceon
```

### 2. **Gunakan Durasi Untuk Maintenance Rutin**
```
❌ BAD: /maintenanceon (lupa disable nanti)
✅ GOOD: /maintenanceon 60 (auto-disable setelah 1 jam)
```

### 3. **Custom Message Yang Informatif**
```
❌ BAD: "Maintenance"
✅ GOOD: "Bot sedang upgrade ke v3.0. Fitur baru: AI chat! 🚀 Estimasi 30 menit"
```

### 4. **Test Sebelum Disable**
```
Admin masih bisa test bot saat maintenance:
1. Enable maintenance
2. Fix bug
3. Test sebagai admin (bypass maintenance)
4. Kalau OK → /maintenanceoff
```

### 5. **Monitor Logs**
```
Cek berapa user yang kena block:
/viewlogs → Lihat "Request blocked - Maintenance mode"
```

---

## 🚨 Troubleshooting

### Problem: User masih bisa akses padahal maintenance ON

**Cek:**
```
1. /maintenancestatus → Pastikan status: ENABLED
2. Cek apakah user ada di whitelist
3. Cek logs/app-{date}.log → Lihat "Request blocked"
```

**Solusi:**
- Pastikan webhook.php ter-update
- Restart web server (nginx/apache)
- Cek file permissions: data/maintenance.json

---

### Problem: Maintenance tidak auto-disable

**Cek:**
```
/maintenancestatus → Lihat "Scheduled end"
```

**Solusi:**
- Auto-disable cuma jalan kalau ada request baru
- Kalau tidak ada user yang akses → tidak trigger check
- Manual disable: /maintenanceoff

---

### Problem: Pesan maintenance tidak update

**Cek:**
```
/maintenancemsg Test message baru
Lalu minta user coba akses bot
```

**Solusi:**
- Message baru langsung berlaku
- Tidak perlu restart atau /maintenanceoff + /maintenanceon
- Kalau masih masalah → cek webhook.php

---

## 📈 Statistics

### Commands Added: 5
- `/maintenancestatus`
- `/maintenanceon`
- `/maintenanceoff`
- `/maintenancemsg`
- `/maintenancebroadcast`

### Files Created: 1
- `src/utils/MaintenanceManager.php` (286 lines)

### Files Updated: 3
- `public/webhook.php` (complete rewrite)
- `src/handlers/AdminHandler.php` (+178 lines)
- `src/handlers/CommandHandler.php` (+39 lines)

### Files Deleted: 17
- Old webhook backups (2 files)
- Old API clients (3 files)
- Old response handlers (6 files)
- Test files (2 files)
- Ferdev backup folder (4 files)

### Net Change:
- **+562 lines added**
- **-2192 lines removed**
- **= -1630 lines (code cleanup!)**

---

## ✅ Testing Checklist

- [x] Enable maintenance (manual)
- [x] Enable maintenance (with duration)
- [x] Enable maintenance (with custom message)
- [x] Disable maintenance
- [x] Check status
- [x] Set custom message
- [x] Admin bypass (whitelist)
- [x] User blocked
- [x] Auto-disable after duration
- [x] Broadcast maintenance notice
- [x] Logging works
- [x] Multiple admins whitelist
- [x] Message shows estimated end time

---

## 🎓 Examples

### Example 1: Quick Maintenance (10 menit)

```bash
# 1. Enable dengan durasi
/maintenanceon 10 Upgrade cepat, 10 menit ya! ⚡

# Response:
✅ Maintenance Mode ENABLED

⏰ Duration: 10 minutes
🕐 Auto-disable at: 10:40

📝 Custom message set.

✅ You are whitelisted (can still use bot)
ℹ️ Other users will see maintenance message

# 2. User lain coba akses:
🔧 Upgrade cepat, 10 menit ya! ⚡

⏰ Estimasi selesai: 10:40

# 3. Auto-disable setelah 10 menit (otomatis)
```

---

### Example 2: Emergency Fix

```bash
# 1. Emergency! Langsung enable
/maintenanceon Bug fix! Please wait...

# 2. Fix bug sambil test (admin bisa akses)
# Test download: /download https://...
# Works! ✅

# 3. Disable manual
/maintenanceoff

# Response:
✅ Maintenance Mode DISABLED

Bot is now operational for all users.
```

---

### Example 3: Planned Maintenance dengan Broadcast

```bash
# 1. Kirim notifikasi dulu
/maintenancebroadcast

# Response:
📢 Maintenance Broadcast Complete

✅ Sent: 1250
❌ Failed: 15

Total: 1265

# 2. Tunggu 10 menit

# 3. Enable maintenance (2 jam)
/maintenanceon 120 Server migration. New features coming! 🚀

# 4. Lakukan migrasi...

# 5. Selesai lebih cepat (1 jam)
/maintenanceoff
```

---

### Example 4: Update Message Mid-Maintenance

```bash
# 1. Maintenance sudah jalan
/maintenancestatus
# Status: ENABLED

# 2. Butuh waktu lebih lama
/maintenancemsg Maaf butuh waktu lebih lama. Hampir selesai! 90% done 📊

# Response:
✅ Maintenance Message Updated

New message:
Maaf butuh waktu lebih lama. Hampir selesai! 90% done 📊

This will be shown to users when maintenance mode is enabled.

# 3. User berikutnya lihat message baru
```

---

## 🔐 Security

### Admin Only
- ✅ Semua maintenance commands cek `isAdmin()`
- ✅ Non-admin tidak bisa akses
- ✅ Logged setiap attempt access

### Whitelist Protection
- ✅ Only admin yang enable bisa add ke whitelist
- ✅ Tidak bisa self-whitelist (user biasa)
- ✅ Whitelist persist di maintenance.json

### Logging
- ✅ Semua maintenance actions logged
- ✅ Block attempts logged dengan user_id
- ✅ Admin bypass logged

---

## 📝 Notes

- Maintenance mode **tidak menghentikan webhook**
- Webhook tetap menerima updates, tapi **diblock sebelum proses**
- Admin di whitelist **tetap bisa test bot** saat maintenance
- Auto-disable **hanya trigger kalau ada request baru**
- Custom message **support Markdown** (bold, italic, code, dll)

---

## 🚀 Future Enhancements

### Planned for v2.5.0:
- [ ] Multiple admin whitelist via command
- [ ] Schedule maintenance di waktu tertentu
- [ ] Maintenance history log
- [ ] Recurring maintenance schedule
- [ ] API endpoint untuk enable/disable
- [ ] Webhook untuk notify channel ketika maintenance
- [ ] Maintenance analytics dashboard

---

**Version:** 2.4.0
**Status:** ✅ Production Ready
**Last Updated:** 2025-11-13

---

**Selamat mencoba! 🎉**

Kalau ada pertanyaan atau issue, check logs atau hubungi developer.
