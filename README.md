# 🔧 Josski Tools Bot - HAR Extractor

Bot Telegram untuk ekstraksi dan dekripsi file HAR dengan mudah.

## 🌟 Features

- 🔧 **HAR File Extractor** - Extract dan decrypt file HAR
- 🔐 **Secret Key Protection** - Akses dengan secret key `JSK`
- 📄 **TXT Output** - Hasil dalam format .txt yang rapi
- 📊 **API Analysis** - Analyze encrypted API requests
- 📥 **Media Downloader** - Bonus: Download files from URLs
- 🎛️ **Web Setup Interface** - Setup bot via browser (NEW!)

## 🚀 Quick Start

### Method 1: Web Setup (Recommended) ⭐

1. **Upload files ke server**
   ```bash
   # Upload semua file via FTP/cPanel
   ```

2. **Buka Setup Menu**
   ```
   https://your-domain.com/setup.php
   Password: joss2024
   ```

3. **Ikuti wizard:**
   - Edit .env (isi BOT_TOKEN dari @BotFather)
   - Setup Webhook
   - Fix Permissions
   - Done! 🎉

📚 **Panduan lengkap:** [SETUP_GUIDE.md](SETUP_GUIDE.md)

### Method 2: Manual Setup

### 1. Install Dependencies
```bash
npm install
```

### 2. Setup Environment
```bash
cp .env.example .env
```

Edit `.env`:
```env
BOT_TOKEN=your_telegram_bot_token_here
SECRET_KEY=JSK
DEFAULT_ENCRYPTION_KEY=Match&Ocean
```

### 3. Run Bot
```bash
npm start
```

For development (auto-reload):
```bash
npm run dev
```

## 📖 How to Use

### 🔧 HAR Extraction

**Step-by-step:**

1. **Gunakan command dengan secret key:**
   ```
   /ekstrakhar JSK
   ```

2. **Upload file .har** ketika bot meminta

3. **Kirim decryption key:**
   - Ketik key custom Anda, atau
   - Ketik `default` untuk key default (`Match&Ocean`)

4. **Terima hasil** dalam format .txt file

### Example Flow

```
👤 User: /ekstrakhar JSK

🤖 Bot: ✅ Secret Key Verified!
       Please upload your .har file...

👤 User: [uploads file.har]

🤖 Bot: 📁 HAR File Received!
       Now send decryption key...

👤 User: default

🤖 Bot: � Processing... 
       ✅ Complete! Found 25 encrypted entries.
       [sends hasil_file_xxx.txt]
```

## � Commands

| Command | Description |
|---------|-------------|
| `/start` | Start bot |
| `/help` | Bantuan lengkap |
| `/menu` | Menu utama |
| `/ekstrakhar JSK` | **Ekstrak HAR file** 🔐 |
| `/download` | Download dari URL |
| `/cancel` | Cancel operasi |

## 📁 Project Structure

```
josski-tools/
├── src/
│   ├── config/
│   │   └── config.js          # Bot configuration
│   ├── handlers/
│   │   ├── commandHandler.js  # Command handlers
│   │   ├── callbackHandler.js # Callback handlers
│   │   └── messageHandler.js  # Message handlers
│   ├── utils/
│   │   ├── encryption.js      # Encryption utilities
│   │   ├── harParser.js       # HAR file parser
│   │   ├── fileManager.js     # File management
│   │   └── sessionManager.js  # Session management
│   └── index.js               # Main entry point
├── temp/                      # Temporary files
├── downloads/                 # Downloaded files
├── results/                   # Processing results
├── .env                       # Environment variables
├── .env.example              # Environment template
├── .gitignore                # Git ignore rules
├── package.json              # Project dependencies
└── README.md                 # This file
```

## � Secret Key

Secret key untuk `/ekstrakhar`: **`JSK`**

Ubah di `.env` jika perlu:
```env
SECRET_KEY=JSK
```

## ⚙️ Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `BOT_TOKEN` | Telegram Bot Token | **Required** |
| `SECRET_KEY` | Secret key untuk /ekstrakhar | `JSK` |
| `DEFAULT_ENCRYPTION_KEY` | Default decryption key | `Match&Ocean` |
| `ADMIN_IDS` | Admin user IDs (optional) | Empty |

### Limits

- Max HAR file size: **100MB**
- Max download file size: **50MB**
- Session timeout: **30 minutes**
- File auto-cleanup: **24 hours**

### Default Decryption Key

Default key: **`Match&Ocean`**

User bisa:
- Ketik `default` untuk pakai key ini
- Atau ketik key custom mereka sendiri

## � Output Format

File hasil ekstraksi (`.txt`) berisi:

```
╔═══════════════════════════════════════════════════════════════╗
║              JOSSKI TOOLS - HAR EXTRACTION RESULTS            ║
╚═══════════════════════════════════════════════════════════════╝

File: example.har
Decryption Key: Match&Ocean
Extracted: [timestamp]
Total Entries: 25

═══════════════════════════════════════════════════════════════

[URL] [STATUS] [METHOD]
[Headers...]

[REQUEST SIGN]
Decrypted: {...}

[RESPONSE DATA]
Decrypted: {...}

───────────────────────────────────────────────────────────────

[Next entry...]
```

## �🛠️ Development

### Requirements
- Node.js >= 14.x
- npm >= 6.x

### Scripts
```bash
npm start    # Production mode
npm run dev  # Development mode (auto-reload)
```

### Project Structure
```
src/
├── config/          # Configuration
├── handlers/        # Command & message handlers
├── utils/          # Utilities (encryption, parser, etc)
└── index.js        # Entry point
```

## ❓ Troubleshooting

### Bot tidak merespon
- ✅ Cek `BOT_TOKEN` di `.env`
- ✅ Pastikan bot sudah di-start: `/start`

### "Invalid secret key"
- ✅ Gunakan: `/ekstrakhar JSK`
- ✅ Secret key case-sensitive

### "No encrypted data found"
- ✅ File HAR tidak berisi data terenkripsi
- ✅ Coba decryption key berbeda

### Upload gagal
- ✅ File harus format `.har`
- ✅ Max size: 100MB
- ✅ File harus valid JSON

## 🔒 Security

- Secret key protection untuk HAR extraction
- Auto file cleanup setelah 24 jam
- Session expiration (30 menit)
- Input validation
- File size limits

## 📝 License

MIT License

## 👤 Author

**Josski** - Developer & Maintainer

## 🤝 Support

Butuh bantuan? 
- Baca `/help` di bot
- Check documentation di README
- Contact developer

---

**Made with ❤️ by Josski**

🚀 Happy Extracting!
