<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\Logger;
use JosskiTools\Utils\UserLogger;
use JosskiTools\Utils\UserManager;
use JosskiTools\Helpers\KeyboardHelper;
use JosskiTools\Handlers\AdminHandler;
use JosskiTools\Handlers\DownloadHandler;
use JosskiTools\Utils\StatsManager;
use JosskiTools\Utils\DownloadHistory;
use JosskiTools\Utils\ChannelHistory;
use JosskiTools\Utils\DonationManager;

/**
 * Command Handler - Handle all bot commands
 */
class CommandHandler {
    /**
     * Handle /stats command
     */
    public function handleStats($chatId, $userId) {
        UserLogger::logCommand($userId, '/stats');

        $stats = $this->statsManager->getStats();

        $today = $stats['today'] ?? [];
        $week = $stats['week'] ?? [];
        $month = $stats['month'] ?? [];
        $total = $stats['total'] ?? [];

        $totalRequests = $total['requests'] ?? 0;
        $totalSuccess = $total['success'] ?? 0;
        $totalFailed = $total['failed'] ?? 0;

        $successRate = $totalRequests > 0
            ? round(($totalSuccess / $totalRequests) * 100, 1)
            : 0;

        if ($successRate >= 95) {
            $statusEmoji = '🟢';
        } elseif ($successRate >= 85) {
            $statusEmoji = '🟡';
        } else {
            $statusEmoji = '🔴';
        }

        $todayDate = $today['date'] ?? date('Y-m-d');
        $todayRequests = $today['requests'] ?? 0;
        $todaySuccess = $today['success'] ?? 0;
        $todayFailed = $today['failed'] ?? 0;

        $weekLabel = $week['week'] ?? date('Y-W');
        $weekRequests = $week['requests'] ?? 0;
        $weekSuccess = $week['success'] ?? 0;
        $weekFailed = $week['failed'] ?? 0;

        $monthLabel = $month['month'] ?? date('Y-m');
        $monthRequests = $month['requests'] ?? 0;
        $monthSuccess = $month['success'] ?? 0;
        $monthFailed = $month['failed'] ?? 0;

        $message = "📊 *BOT STATISTICS OVERVIEW*\n\n";
        $message .= "📅 *Today* ({$todayDate}): {$todayRequests} requests\n";
        $message .= "   ✅ {$todaySuccess} | ❌ {$todayFailed}\n\n";

        $message .= "🗓️ *This Week* ({$weekLabel}): {$weekRequests} requests\n";
        $message .= "   ✅ {$weekSuccess} | ❌ {$weekFailed}\n\n";

        $message .= "📆 *This Month* ({$monthLabel}): {$monthRequests} requests\n";
        $message .= "   ✅ {$monthSuccess} | ❌ {$monthFailed}\n\n";

        $message .= "🌐 *All-Time Total*: {$totalRequests} requests\n";
        $message .= "   ✅ {$totalSuccess} | ❌ {$totalFailed}\n\n";

        $message .= "{$statusEmoji} *Success Rate:* {$successRate}%\n";
        $message .= "🕒 *Last Updated:* " . ($stats['last_updated'] ?? 'N/A');

        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }


    private $bot;
    private $sessionManager;
    private $config;
    private $adminHandler;
    private $statsManager;
    private $downloadHandler;

    public function __construct($bot, $sessionManager, $config) {
        $this->bot = $bot;
        $this->sessionManager = $sessionManager;
        $this->config = $config;

        // Initialize logging
        Logger::init($config['directories']['logs'] ?? null);
        UserLogger::init($config['directories']['logs'] ?? null);
        UserManager::init();

        // Initialize admin handler
        $this->adminHandler = new AdminHandler($bot, $sessionManager, $config);
        
        // Initialize stats manager with configured data directory
        $statsBaseDir = $config['directories']['data'] ?? (__DIR__ . '/../../data');
        $statsDir = rtrim($statsBaseDir, '\\/') . '/stats';
        $this->statsManager = new StatsManager($statsDir);
        $this->downloadHandler = new DownloadHandler($bot, $sessionManager, $config);
    }
    
    /**
     * Handle /start command
     */
    public function handleStart($chatId, $userId, $username, $chatType = 'private', $startPayload = '') {
        Logger::info("Start command", ['user_id' => $userId, 'chat_type' => $chatType]);
        UserLogger::logCommand($userId, '/start');

        // Register/update user
        UserManager::addUser($userId, [
            'username' => $username,
            'chat_type' => $chatType
        ]);

        // Clear any previous session
        $this->sessionManager->clearSession($userId);
        
        // Get stats with safe defaults
        $stats = $this->statsManager->getStats();
        
        // Get quote
        $quotesManager = new \JosskiTools\Utils\QuotesManager();
        $quote = $quotesManager->getDailyQuote();
        
        // Calculate uptime (from bot start time if you track it, or use a placeholder)
        $uptimeFile = __DIR__ . '/../../data/uptime.txt';
        if (file_exists($uptimeFile)) {
            $startTime = (int)file_get_contents($uptimeFile);
            $uptime = time() - $startTime;
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            $uptimeStr = "{$days}d {$hours}h {$minutes}m";
        } else {
            // Create uptime file with current time
            file_put_contents($uptimeFile, time());
            $uptimeStr = "0d 0h 0m";
        }
        
        // Build message with Markdown formatting
        $message = "🦊 *WELCOME TO JOSS HELPER!*\n\n";
        $message .= "👋 Halo *" . ($username ?? 'User') . "*!\n";
        $message .= "Selamat datang di JOSS HELPER BOT!\n";
        
        // Show User ID only in private chat
        if ($chatType === 'private') {
            $message .= "📋 User ID: `{$userId}`\n\n";
        } else {
            $message .= "\n";
        }
        
        // Safe stats display with null coalescing (use correct keys: today/week/month)
        $dailyRequests = $stats['today']['requests'] ?? 0;
        $weeklyRequests = $stats['week']['requests'] ?? 0;
        $monthlyRequests = $stats['month']['requests'] ?? 0;
        $totalRequests = $stats['total']['requests'] ?? 0;
        $successTotal = $stats['total']['success'] ?? 0;
        $failTotal = $stats['total']['failed'] ?? 0;
        
        $message .= "📊 *BOT STATISTICS*\n";
        $message .= "┣ Hari ini: *{$dailyRequests}* requests\n";
        $message .= "┣ Minggu ini: *{$weeklyRequests}* requests\n";
        $message .= "┣ Bulan ini: *{$monthlyRequests}* requests\n";
        $message .= "┗ Total: *{$totalRequests}* requests\n\n";
        
        $message .= "✅ Sukses: *{$successTotal}* | ❌ Gagal: *{$failTotal}*\n\n";
        
        if ($quote) {
            $message .= "💭 *Quote of the Day*\n";
            $message .= "_" . ($quote['text'] ?? $quote['quote'] ?? 'No quote available') . "_\n";
            $message .= "— " . ($quote['author'] ?? 'Unknown') . "\n\n";
        }
        
        $message .= "*UPTIME:* `{$uptimeStr}`\n\n";
        
        // Add group-specific info
        if (in_array($chatType, ['group', 'supergroup'])) {
            $message .= "💡 *Tips:* Gunakan perintah /help untuk melihat semua fitur!\n";
            $message .= "Atau langsung kirim link untuk download otomatis.\n\n";
            $message .= "🤖 @josshelperbot\n";
        }
        
        // Only show keyboard in private chat
        $keyboard = null;
        if ($chatType === 'private') {
            $keyboard = KeyboardHelper::getMainKeyboard();
        }
        
        try {
            // Send photo with caption
            $photoPath = __DIR__ . '/../../public/energetic-orange-fox-mascot-giving-thumbs-up.png';
            
            if (file_exists($photoPath)) {
                $this->bot->sendPhoto($chatId, new \CURLFile($photoPath), $message, 'Markdown', $keyboard);

            } else {
                // Fallback to text only
                $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
            }
        } catch (\Exception $e) {
            // Fallback to plain text if formatting fails
            $this->bot->sendMessage($chatId, strip_tags($message), null, $keyboard);
        }

            $this->handleStartPayload($chatId, $userId, $startPayload);
    }

        private function handleStartPayload($chatId, $userId, $payload) {
            $payload = trim((string)$payload);

            if ($payload === '') {
                return;
            }

            if (stripos($payload, 'share_') === 0) {
                $token = strtoupper(substr($payload, 6));

                if (!preg_match('/^[A-Z0-9]{4,}$/', $token)) {
                    $this->bot->sendMessage($chatId, "⚠️ Link tidak valid. Silakan minta tautan baru.");
                    return;
                }

                ChannelHistory::init($this->bot, $this->config);
                $entry = ChannelHistory::getHistoryEntry($token);

                if (!$entry) {
                    $this->bot->sendMessage($chatId, "❌ Konten dengan ID `{$token}` tidak ditemukan atau sudah kedaluwarsa.", 'Markdown');
                    return;
                }

                $url = $entry['url'] ?? '';
                if (!$url) {
                    $this->bot->sendMessage($chatId, "❌ Konten dengan ID `{$token}` tidak memiliki URL yang bisa diunduh.", 'Markdown');
                    return;
                }

                UserLogger::log($userId, 'share_link_used', [
                    'token' => $token,
                    'owner_id' => $entry['user_id'] ?? null,
                    'platform' => $entry['platform'] ?? null
                ]);

                $title = $entry['title'] ?? 'Konten';
                $this->bot->sendMessage(
                    $chatId,
                    "🔁 Mengambil konten yang dibagikan (`{$token}`) - {$title}. Mohon tunggu...",
                    'Markdown'
                );

                $platform = $entry['platform'] ?? null;
                $this->downloadHandler->handle($chatId, $url, $platform);
                return;
            }

            // Future payloads can be added here
        }
    
    /**
     * Handle /help command
     */
    public function handleHelp($chatId) {
        UserLogger::logCommand($chatId, '/help');
        $message = "📚 *Panduan Pengguna Josski Tools*\n\n";
        $message .= "✨ *Cara tercepat:* kirimkan link TikTok/Facebook/YouTube/Spotify/CapCut langsung ke chat, bot akan mendeteksi dan menyiapkan unduhan otomatis.\n\n";
        $message .= "🔘 *Tombol Utama di Keyboard*\n";
        $message .= "• 📥 Downloader — pilih platform secara manual\n";
        $message .= "• 📚 Help — buka panduan ini\n";
        $message .= "• 🎛️ Menu — tampilkan menu inline\n";
        $message .= "• 💝 Donasi — dukung bot via QRIS\n\n";
        $message .= "🧰 *Perintah Penting*\n";
        $message .= "/start — perkenalan bot\n";
        $message .= "/help — panduan ini\n";
        $message .= "/menu — menu cepat\n";
        $message .= "/donate — info dukungan & QRIS\n";
        $message .= "/cancel — batalkan proses yang sedang berjalan\n\n";
        $message .= "🎯 *Downloader Commands*\n";
        $message .= "/tiktok, /facebook, /spotify, /capcut <url>\n";
        $message .= "/ytmp3 & /ytmp4 <url> untuk audio/video YouTube\n";
        $message .= "/ttuser <username> — daftar video TikTok per halaman\n";
        $message .= "/ttall <username> — kirim semua video (maks 60)\n\n";
        $message .= "🗂️ *Riwayat & Favorit*\n";
        $message .= "/get [kata]/[jumlah] — ambil riwayat download terbaru\n";
        $message .= "/history — riwayat lengkap\n";
        $message .= "/favorites & /favorite <url> — kelola favorit\n\n";
        $message .= "🔗 *Share Link*\n";
        $message .= "/share — reply ke media untuk generate share link\n\n";
        $message .= "❓ Masih bingung? Tinggal chat admin atau tekan tombol Donasi untuk mendukung pengembangan. 🚀";
        
        $keyboard = KeyboardHelper::getMainKeyboard();
        
        try {
            $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
        } catch (\Exception $e) {
            $this->bot->sendMessage($chatId, $message, 'Markdown');
        }
    }
    
    /**
     * Handle /menu command
     */
    public function handleMenu($chatId) {
        UserLogger::logCommand($chatId, '/menu');
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📥 Downloader', 'callback_data' => 'menu_downloader']],
                [['text' => '📚 Help', 'callback_data' => 'menu_help']]
            ]
        ];
        
        $message = "🎛️ *Josski Tools - Main Menu*\n\n";
        $message .= "Select a feature below:\n\n";
        $message .= "📥 *Downloader* - Download media from various platforms\n";
        $message .= "📚 *Help* - View help & instructions";
        
        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
    
    /**
     * Handle /codex command (hidden feature)
     */
    public function handleCodex($chatId, $userId, $args) {
        UserLogger::logCommand($userId, '/codex', ['args_provided' => !empty($args)]);
        $key = trim($args);

        if ($key !== $this->config['secret_key']) {
            Logger::warning("Invalid codex key attempt", ['user_id' => $userId]);
            UserLogger::logError($userId, "Invalid codex key");
            $this->bot->sendMessage($chatId, "❌ Invalid secret key!\n\nUsage: `/codex JSK`", 'Markdown');
            return;
        }

        Logger::info("Codex access granted", ['user_id' => $userId]);
        UserLogger::log($userId, "Codex access granted");
        
        // Grant access to user
        $this->sessionManager->setState($userId, 'codex_access', ['activated' => true]);
        
        $message = "🔓 *Access Granted!*\n\n";
        $message .= "Welcome to Josski Tools!\n\n";
        $message .= "Special features are now available for you.";
        
        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }
    
    /**
     * Handle /ekstrakhar command (hidden feature)
     */
    public function handleEkstrakhar($chatId, $userId) {
        UserLogger::logCommand($userId, '/ekstrakhar');

        // Check if user has codex access
        $session = $this->sessionManager->getSession($userId);
        $hasAccess = isset($session['data']['activated']) && $session['data']['activated'] === true;

        if (!$hasAccess) {
            Logger::warning("Ekstrakhar access denied - no codex", ['user_id' => $userId]);
            UserLogger::logError($userId, "Ekstrakhar access denied");
            $message = "🔒 *Access Denied*\n\n";
            $message .= "You need to activate this feature first!\n\n";
            $message .= "Use: `/codex <secret_key>`\n\n";
            $message .= "Don't have the secret key? Contact admin.";
            
            $this->bot->sendMessage($chatId, $message, 'Markdown');
            return;
        }

        Logger::info("HAR extractor activated", ['user_id' => $userId]);
        UserLogger::logHarExtraction($userId, "Extractor activated");

        $this->sessionManager->setState($userId, 'awaiting_har_file', ['activated' => true]);
        
        $message = "✅ *HAR Extractor Ready*\n\n";
        $message .= "Please send your .har file now.\n\n";
        $message .= "📁 Max size: 100MB\n";
        $message .= "🔒 Your data is secure\n\n";
        $message .= "Use /cancel to abort.";
        
        $keyboard = KeyboardHelper::getCancelKeyboard();
        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
    
    /**
     * Handle /cancel command
     */
    public function handleCancel($chatId, $userId) {
        UserLogger::logCommand($userId, '/cancel');
        $this->sessionManager->clearSession($userId);

        $message = "❌ *Operation Cancelled*\n\n";
        $message .= "All pending operations have been cancelled.\n\n";
        $message .= "Use /help to see available commands.";

        $keyboard = KeyboardHelper::getMainKeyboard();
        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }

    // ==========================================
    // USER FEATURES COMMANDS
    // ==========================================

    /**
     * Handle /history command
     */
    public function handleHistory($chatId, $userId) {
        UserLogger::logCommand($userId, '/history');

        $history = DownloadHistory::getHistory($userId, 10);

        if (empty($history)) {
            $this->bot->sendMessage(
                $chatId,
                "📭 **Download History**\n\nYou haven't downloaded anything yet!"
            );
            return;
        }

        $message = "📜 **Your Download History**\n\n";
        foreach ($history as $idx => $item) {
            $num = $idx + 1;
            $platform = $item['platform'] ?? 'unknown';
            $title = $item['title'] ?? 'No title';
            $date = date('M d, H:i', $item['timestamp'] ?? time());

            $message .= "{$num}. [{$platform}] {$title}\n";
            $message .= "   📅 {$date}\n\n";
        }

        $message .= "💡 Use /clearhistory to clear all history";

        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /get command (quick history lookup)
     */
    public function handleGet($chatId, $userId, $args = '') {
        UserLogger::logCommand($userId, '/get', ['query' => $args]);

        $query = trim($args);
        $limit = 5;

        if ($query !== '') {
            if (preg_match('/^(\d+)\s+(.*)$/', $query, $matches)) {
                $limit = (int) $matches[1];
                $query = trim($matches[2]);
            } elseif (is_numeric($query)) {
                $limit = (int) $query;
                $query = '';
            }
        }

        $limit = max(1, min($limit, 10));

        // Check if user is admin
        $adminIds = $this->config['admin_ids'] ?? [];
        $isAdmin = in_array($userId, $adminIds);

        ChannelHistory::init($this->bot, $this->config);
        $hasChannel = $isAdmin ? ChannelHistory::hasChannel($userId) : false;

        // Check if query looks like a UUIDv4 token
        if ($query !== '' && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $query)) {
            // Try to get from channel history by token
            $historyEntry = ChannelHistory::getHistoryEntry($query);
            
            if ($historyEntry) {
                // Found in channel history - redirect to channel or show info
                $message = "📦 *Download Found*\n\n";
                $message .= "🔑 Token: `{$query}`\n";
                $message .= "📺 Platform: " . strtoupper($historyEntry['platform'] ?? 'unknown') . "\n";
                $message .= "📝 Title: " . $this->escapeMarkdown($historyEntry['title'] ?? 'No title') . "\n";
                $message .= "📅 Date: " . date('M d, H:i', $historyEntry['timestamp'] ?? time()) . "\n\n";
                
                $keyboard = null;
                if (!empty($historyEntry['url'])) {
                    $keyboard = ['inline_keyboard' => [
                        [['text' => '🔗 Open Original', 'url' => $historyEntry['url']]]
                    ]];
                }
                
                // If there's a channel message, provide link
                if (!empty($historyEntry['channel_id']) && !empty($historyEntry['message_id'])) {
                    $channelId = $historyEntry['channel_id'];
                    // Remove the -100 prefix if present for the link
                    $channelIdStr = ltrim($channelId, '-');
                    if (strpos($channelIdStr, '100') === 0) {
                        $channelIdStr = substr($channelIdStr, 3);
                    }
                    $messageId = $historyEntry['message_id'];
                    $message .= "💡 View in channel: `https://t.me/c/{$channelIdStr}/{$messageId}`\n";
                }
                
                $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
                return;
            }
        }

        if ($query !== '') {
            $history = DownloadHistory::searchHistory($userId, $query);
        } else {
            $history = DownloadHistory::getHistory($userId, $limit);
        }

        if (empty($history)) {
            $message = "📭 *No downloads found*";

            if ($query !== '') {
                $message .= "\n\n🔍 Filter: `" . $this->escapeMarkdown($query) . "`";
            }

            if (!$hasChannel) {
                $message .= "\n\n💡 Setup history channel dengan /setupchannel agar downloadmu tersimpan otomatis.";
            }

            $this->bot->sendMessage($chatId, $message, 'Markdown');
            return;
        }

        $history = array_slice($history, 0, $limit);

        $message = "📦 *Latest Downloads*\n\n";

        if ($query !== '') {
            $message .= "🔍 Filter: `" . $this->escapeMarkdown($query) . "`\n\n";
        }

        $keyboard = ['inline_keyboard' => []];

        foreach ($history as $idx => $item) {
            $num = $idx + 1;
            $platform = strtoupper($item['platform'] ?? 'unknown');
            $title = $this->escapeMarkdown($item['title'] ?? 'No title');
            $date = date('M d, H:i', $item['timestamp'] ?? time());
            $url = $item['url'] ?? '';

            $message .= "{$num}. [{$platform}] {$title}\n";
            $message .= "   📅 {$date}\n\n";

            if (!empty($url)) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => "🔗 #{$num}", 'url' => $url]
                ];
            }
        }

        if ($hasChannel) {
            $message .= "📺 *Tip:* Cek channel historimu untuk versi media.\n";
        } elseif ($isAdmin) {
            $message .= "💡 Gunakan /setupchannel agar riwayat tersimpan dengan preview.\n";
        }

        $this->bot->sendMessage($chatId, $message, 'Markdown', !empty($keyboard['inline_keyboard']) ? $keyboard : null);
    }

    /**
     * Handle /favorites command
     */
    public function handleFavorites($chatId, $userId) {
        UserLogger::logCommand($userId, '/favorites');

        $favorites = DownloadHistory::getFavorites($userId);

        if (empty($favorites)) {
            $this->bot->sendMessage(
                $chatId,
                "⭐ **Your Favorites**\n\nNo favorites yet!\n\n💡 Add favorites using /favorite <url>"
            );
            return;
        }

        $message = "⭐ **Your Favorites** (" . count($favorites) . ")\n\n";
        foreach ($favorites as $idx => $fav) {
            $num = $idx + 1;
            $platform = $fav['platform'] ?? 'unknown';
            $title = $fav['title'] ?? 'No title';
            $url = $fav['url'];

            $message .= "{$num}. [{$platform}] {$title}\n";
            $message .= "   🔗 {$url}\n\n";

            if ($num >= 10) {
                $message .= "...and " . (count($favorites) - 10) . " more\n";
                break;
            }
        }

        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /favorite command
     */
    public function handleFavorite($chatId, $userId, $args) {
        $url = trim($args);

        if (empty($url)) {
            $this->bot->sendMessage($chatId, "Usage: /favorite <url>");
            return;
        }

        UserLogger::logCommand($userId, '/favorite', ['url' => $url]);

        $platform = \JosskiTools\Api\NekoLabsClient::detectPlatform($url);
        $result = DownloadHistory::addFavorite($userId, $url, $platform);

        $this->bot->sendMessage($chatId, $result['message']);
    }

    /**
     * Handle /clearhistory command
     */
    public function handleClearHistory($chatId, $userId) {
        UserLogger::logCommand($userId, '/clearhistory');

        $count = DownloadHistory::clearHistory($userId);

        $this->bot->sendMessage(
            $chatId,
            "🗑️ **History Cleared**\n\n" .
            "Removed {$count} items from your history."
        );
    }

    /**
     * Handle /share command
     */
    public function handleShare($chatId, $userId, array $message, $args = '') {
        UserLogger::logCommand($userId, '/share');

        $chatType = $message['chat']['type'] ?? 'private';
        if ($chatType !== 'private') {
            $this->bot->sendMessage($chatId, "ℹ️ Gunakan /share di chat pribadi dengan bot.");
            return;
        }

        $reply = $message['reply_to_message'] ?? null;
        if (!$reply) {
            $this->bot->sendMessage(
                $chatId,
                "🔁 Balas (reply) pesan file yang ingin dibagikan, lalu kirim /share."
            );
            return;
        }

        $replyMessageId = $reply['message_id'] ?? null;
        if (!$replyMessageId) {
            $this->bot->sendMessage($chatId, "❌ Tidak dapat membaca pesan yang direply. Coba lagi.");
            return;
        }

        ChannelHistory::init($this->bot, $this->config);
        $shareResult = ChannelHistory::createShareFromMessage($userId, $replyMessageId);

        if (!($shareResult['success'] ?? false)) {
            $this->bot->sendMessage($chatId, $shareResult['message'] ?? '❌ Gagal membuat link berbagi.');
            return;
        }

        $token = $shareResult['token'] ?? null;
        $link = $shareResult['link'] ?? null;

        if (!$token || !$link) {
            $this->bot->sendMessage($chatId, "⚠️ Link berbagi belum tersedia. Coba lagi nanti.");
            return;
        }

        $safeToken = $this->escapeMarkdown($token);
        $safeLink = $this->escapeMarkdown($link);

        $messageText = "📤 *Link Berbagi Siap!*\n\n";
        $messageText .= "Bagikan ke temanmu:\n`{$safeLink}`\n\n";
        $messageText .= "🆔 Token: `{$safeToken}`\n\n";
        $messageText .= "Temanmu akan otomatis menerima kontennya setelah menekan Start.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🚀 Buka Link', 'url' => $link]
                ]
            ]
        ];

        $this->bot->sendMessage($chatId, $messageText, 'Markdown', $keyboard);

        UserLogger::log($userId, 'share_link_generated', [
            'token' => $token,
            'message_id' => $replyMessageId
        ]);
    }

    /**
     * Handle /mystats command
     */
    public function handleMyStats($chatId, $userId) {
        UserLogger::logCommand($userId, '/mystats');

        $stats = DownloadHistory::getStats($userId);
        $donorBadge = DonationManager::getBadge($userId) ?? '';

        $message = "📊 **Your Statistics** {$donorBadge}\n\n";
        $message .= "📥 Total Downloads: {$stats['total_downloads']}\n";
        $message .= "⭐ Total Favorites: {$stats['total_favorites']}\n";
        $message .= "📈 This Week: {$stats['downloads_this_week']}\n\n";

        if (!empty($stats['platform_breakdown'])) {
            $message .= "🌐 **Platform Usage:**\n";
            foreach (array_slice($stats['platform_breakdown'], 0, 5) as $platform => $count) {
                $message .= "• {$platform}: {$count}\n";
            }
        }

        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /donate command
     */
    public function handleDonate($chatId, $userId, array $context = []) {
        $log = $context['log'] ?? true;
        if ($log) {
            UserLogger::logCommand($userId, '/donate');
        }

        DonationManager::init($this->config['directories']['data'] ?? null);

        $mode = $context['mode'] ?? 'send';
        $messageId = $context['message_id'] ?? null;
        $state = $context['state'] ?? 'main';

        $message = DonationManager::getDonationInfo();
        $keyboard = $this->buildDonationInlineKeyboard($state);

        $photoPath = dirname(__DIR__, 2) . '/public/qrisku.jpg';

        if ($mode === 'edit' && $messageId) {
            $this->bot->editMessageCaption($chatId, $messageId, $message, 'Markdown', $keyboard);
            return;
        }

        if (file_exists($photoPath)) {
            $this->bot->sendPhoto($chatId, $photoPath, $message, 'Markdown', $keyboard);
        } else {
            $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
        }
    }

    /**
     * Handle /myprofile command (donor profile)
     */
    public function handleMyProfile($chatId, $userId, array $context = []) {
        $log = $context['log'] ?? true;
        if ($log) {
            UserLogger::logCommand($userId, '/myprofile');
        }

        DonationManager::init($this->config['directories']['data'] ?? null);

        $mode = $context['mode'] ?? 'send';
        $messageId = $context['message_id'] ?? null;
        $state = $context['state'] ?? 'profile';

        $message = DonationManager::getDonorProfile($userId);
        $keyboard = $this->buildDonationInlineKeyboard($state);

        if ($mode === 'edit' && $messageId) {
            $this->bot->editMessageCaption($chatId, $messageId, $message, 'Markdown', $keyboard);
            return;
        }

        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }

    /**
     * Handle /leaderboard command
     */
    public function handleLeaderboard($chatId, $userId, array $context = []) {
        $log = $context['log'] ?? true;
        if ($log) {
            UserLogger::logCommand($userId, '/leaderboard');
        }

        DonationManager::init($this->config['directories']['data'] ?? null);

        $mode = $context['mode'] ?? 'send';
        $messageId = $context['message_id'] ?? null;
        $state = $context['state'] ?? 'leaderboard';

        $message = DonationManager::getLeaderboard(10);
        $keyboard = $this->buildDonationInlineKeyboard($state);

        if ($mode === 'edit' && $messageId) {
            $this->bot->editMessageCaption($chatId, $messageId, $message, 'Markdown', $keyboard);
            return;
        }

        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }

    private function buildDonationInlineKeyboard(string $state = 'main'): array {
        $rows = [
            [
                ['text' => '👤 Profil Donaturku', 'callback_data' => 'donate_profile'],
                ['text' => '🏆 Leaderboard', 'callback_data' => 'donate_leaderboard']
            ]
        ];

        if ($state !== 'main') {
            $rows[] = [
                ['text' => '⬅️ Kembali ke QR', 'callback_data' => 'donate_main']
            ];
        }

        return ['inline_keyboard' => $rows];
    }

    /**
     * Escape Telegram Markdown special chars for legacy Markdown parser.
     */
    private function escapeMarkdown(string $text): string {
        $replacements = [
            '_' => '\\_',
            '*' => '\\*',
            '`' => '\\`',
            '[' => '\\['
        ];

        return strtr($text, $replacements);
    }

    /**
     * Handle /bulk command
     */
    public function handleBulk($chatId, $userId, $args) {
        UserLogger::logCommand($userId, '/bulk');

        if (empty($args)) {
            $this->bot->sendMessage(
                $chatId,
                "📦 **Bulk Download**\n\n" .
                "Download multiple videos at once!\n\n" .
                "Usage: `/bulk <url1> <url2> <url3> ...`\n\n" .
                "Max: 10 URLs per request"
            );
            return;
        }

        // Parse URLs from args
        $urls = preg_split('/\s+/', $args);
        $urls = array_filter($urls, function($url) {
            return filter_var(trim($url), FILTER_VALIDATE_URL);
        });
        $urls = array_values($urls);

        if (empty($urls)) {
            $this->bot->sendMessage($chatId, "❌ No valid URLs found!");
            return;
        }

        // Create bulk download handler
        $bulkHandler = new BulkDownloadHandler($this->bot, $this->config);
        $bulkHandler->handleBulkDownload($chatId, $userId, $urls);
    }

    /**
     * Handle /advstats command (admin only - advanced statistics)
     */
    public function handleAdvStats($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            $this->bot->sendMessage($chatId, "❌ Admin only command");
            return;
        }

        UserLogger::logCommand($userId, '/advstats');

        $message = AdvancedStats::generateDashboard();
        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /setupchannel command (Admin only)
     */
    public function handleSetupChannel($chatId, $userId, $args = '') {
        UserLogger::logCommand($userId, '/setupchannel');

        // Check if user is admin
        $adminIds = $this->config['admin_ids'] ?? [];
        if (!in_array($userId, $adminIds)) {
            $this->bot->sendMessage($chatId, "❌ Command ini hanya untuk admin.");
            return;
        }

        // Initialize channel history
        ChannelHistory::init($this->bot, $this->config);

        if (empty($args)) {
            $message = ChannelHistory::getSetupInstructions();
            $this->bot->sendMessage($chatId, $message, 'Markdown');
            return;
        }

        // User provided channel ID/username
        $channelId = trim($args);

        // If username provided, convert to ID format
        if (strpos($channelId, '@') === 0) {
            // Already in @username format
        } elseif (is_numeric($channelId)) {
            // Numeric ID
            if ($channelId > 0) {
                $channelId = '-100' . $channelId;  // Convert to channel format
            }
        }

        $result = ChannelHistory::setupChannel($userId, $channelId);
        $this->bot->sendMessage($chatId, $result['message'], 'Markdown');
    }

    /**
     * Handle /channelinfo command (Admin only)
     */
    public function handleChannelInfo($chatId, $userId) {
        UserLogger::logCommand($userId, '/channelinfo');

        // Check if user is admin
        $adminIds = $this->config['admin_ids'] ?? [];
        if (!in_array($userId, $adminIds)) {
            $this->bot->sendMessage($chatId, "❌ Command ini hanya untuk admin.");
            return;
        }

        ChannelHistory::init($this->bot, $this->config);
        $message = ChannelHistory::getChannelInfo($userId);
        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /removechannel command (Admin only)
     */
    public function handleRemoveChannel($chatId, $userId) {
        UserLogger::logCommand($userId, '/removechannel');

        // Check if user is admin
        $adminIds = $this->config['admin_ids'] ?? [];
        if (!in_array($userId, $adminIds)) {
            $this->bot->sendMessage($chatId, "❌ Command ini hanya untuk admin.");
            return;
        }

        ChannelHistory::init($this->bot, $this->config);
        $result = ChannelHistory::removeChannel($userId);
        $this->bot->sendMessage($chatId, $result['message'], 'Markdown');
    }

    // ==========================================
    // ADMIN COMMANDS
    // ==========================================

    /**
     * Handle /admin command
     */
    public function handleAdmin($chatId, $userId) {
        $this->adminHandler->showPanel($chatId, $userId);
    }

    /**
     * Handle /userstats command
     */
    public function handleUserStats($chatId, $userId) {
        $this->adminHandler->showUserStats($chatId, $userId);
    }

    /**
     * Handle /broadcast command
     */
    public function handleBroadcast($chatId, $userId) {
        $this->adminHandler->initiateBroadcast($chatId, $userId, 'general');
    }

    /**
     * Handle /maintenance command
     */
    public function handleMaintenance($chatId, $userId) {
        $this->adminHandler->initiateBroadcast($chatId, $userId, 'maintenance');
    }

    /**
     * Handle /promo command
     */
    public function handlePromo($chatId, $userId) {
        $this->adminHandler->initiateBroadcast($chatId, $userId, 'promo');
    }

    /**
     * Handle /blockuser command
     */
    public function handleBlockUser($chatId, $userId, $args) {
        $targetUserId = (int)trim($args);

        if (empty($targetUserId)) {
            $this->bot->sendMessage($chatId, "Usage: /blockuser <user_id>");
            return;
        }

        $this->adminHandler->blockUser($chatId, $userId, $targetUserId);
    }

    /**
     * Handle /unblockuser command
     */
    public function handleUnblockUser($chatId, $userId, $args) {
        $targetUserId = (int)trim($args);

        if (empty($targetUserId)) {
            $this->bot->sendMessage($chatId, "Usage: /unblockuser <user_id>");
            return;
        }

        $this->adminHandler->unblockUser($chatId, $userId, $targetUserId);
    }

    /**
     * Handle /userlog command
     */
    public function handleUserLog($chatId, $userId, $args) {
        $targetUserId = (int)trim($args);

        if (empty($targetUserId)) {
            $this->bot->sendMessage($chatId, "Usage: /userlog <user_id>");
            return;
        }

        $this->adminHandler->viewUserLog($chatId, $userId, $targetUserId);
    }

    /**
     * Handle /exportusers command
     */
    public function handleExportUsers($chatId, $userId) {
        $this->adminHandler->exportUsers($chatId, $userId);
    }

    /**
     * Handle /viewlogs command
     */
    public function handleViewLogs($chatId, $userId, $args = '') {
        $lines = !empty($args) ? (int)$args : 50;
        $this->adminHandler->viewLogs($chatId, $userId, $lines);
    }

    /**
     * Handle /cleanlogs command
     */
    public function handleCleanLogs($chatId, $userId) {
        $this->adminHandler->cleanLogs($chatId, $userId);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin($userId) {
        return $this->adminHandler->isAdmin($userId);
    }

    /**
     * Handle /maintenancestatus command
     */
    public function handleMaintenanceStatus($chatId, $userId) {
        $this->adminHandler->maintenanceStatus($chatId, $userId);
    }

    /**
     * Handle /maintenanceon command
     */
    public function handleMaintenanceOn($chatId, $userId, $args = '') {
        $this->adminHandler->maintenanceOn($chatId, $userId, $args);
    }

    /**
     * Handle /maintenanceoff command
     */
    public function handleMaintenanceOff($chatId, $userId) {
        $this->adminHandler->maintenanceOff($chatId, $userId);
    }

    /**
     * Handle /maintenancemsg command
     */
    public function handleMaintenanceMsg($chatId, $userId, $args = '') {
        $this->adminHandler->maintenanceMessage($chatId, $userId, $args);
    }

    /**
     * Handle /maintenancebroadcast command
     */
    public function handleMaintenanceBroadcast($chatId, $userId) {
        $this->adminHandler->maintenanceBroadcast($chatId, $userId);
    }

}
