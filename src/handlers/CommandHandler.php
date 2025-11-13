<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\Logger;
use JosskiTools\Utils\UserLogger;
use JosskiTools\Utils\UserManager;
use JosskiTools\Helpers\KeyboardHelper;

/**
 * Command Handler - Handle all bot commands
 */
class CommandHandler {

    private $bot;
    private $sessionManager;
    private $config;
    private $adminHandler;

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
    }
    
    /**
     * Handle /start command
     */
    public function handleStart($chatId, $userId, $username, $chatType = 'private') {
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
        $statsManager = new \JosskiTools\Utils\StatsManager();
        $stats = $statsManager->getStats();
        
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
    }
    
    /**
     * Handle /help command
     */
    public function handleHelp($chatId) {
        UserLogger::logCommand($chatId, '/help');
        $message = "📚 *Josski Tools Bot - Help*\n\n";
        $message .= "🎮 *NAVIGATION*\n";
        $message .= "• Gunakan keyboard button di bawah\n";
        $message .= "• Atau ketik command langsung\n\n";
        $message .= "*Commands:*\n";
        $message .= "/start - Start the bot\n";
        $message .= "/help - Show this help\n";
        $message .= "/menu - Show main menu\n\n";
        $message .= "*Downloader:*\n";
        $message .= "/capcut <url> - Download Capcut video\n";
        $message .= "/facebook <url> - Download Facebook video\n";
        $message .= "/spotify <url> - Download Spotify audio\n";
        $message .= "/tiktok <url> - Download TikTok video\n";
        $message .= "/ytmp3 <url> - Download YouTube audio\n";
        $message .= "/ytmp4 <url> - Download YouTube video\n\n";
        $message .= "*TikTok Special:*\n";
        $message .= "/ttuser <username> - Get videos from TikTok user\n";
        $message .= "/ttall <username> - Download ALL videos (up to 60)\n\n";
        $message .= "*Aliases:*\n";
        $message .= "/fb - Facebook | /tt - TikTok\n\n";
        $message .= "/cancel - Cancel operation\n\n";
        $message .= "Need help? Contact admin! 🚀";
        
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
        $this->sessionManager->clearState($userId);

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
    public function handleDonate($chatId, $userId) {
        UserLogger::logCommand($userId, '/donate');

        $message = DonationManager::getDonationInfo();

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💝 View Leaderboard', 'callback_data' => 'donate_leaderboard']],
                [['text' => '👤 My Profile', 'callback_data' => 'donate_profile']]
            ]
        ];

        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }

    /**
     * Handle /myprofile command (donor profile)
     */
    public function handleMyProfile($chatId, $userId) {
        UserLogger::logCommand($userId, '/myprofile');

        $message = DonationManager::getDonorProfile($userId);
        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /leaderboard command
     */
    public function handleLeaderboard($chatId, $userId) {
        UserLogger::logCommand($userId, '/leaderboard');

        $message = DonationManager::getLeaderboard(10);
        $this->bot->sendMessage($chatId, $message, 'Markdown');
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
     * Handle /setupchannel command
     */
    public function handleSetupChannel($chatId, $userId, $args = '') {
        UserLogger::logCommand($userId, '/setupchannel');

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
     * Handle /channelinfo command
     */
    public function handleChannelInfo($chatId, $userId) {
        UserLogger::logCommand($userId, '/channelinfo');

        ChannelHistory::init($this->bot, $this->config);
        $message = ChannelHistory::getChannelInfo($userId);
        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /removechannel command
     */
    public function handleRemoveChannel($chatId, $userId) {
        UserLogger::logCommand($userId, '/removechannel');

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

}
