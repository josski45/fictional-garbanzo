<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\Logger;
use JosskiTools\Utils\UserLogger;
use JosskiTools\Utils\UserManager;
use JosskiTools\Utils\ChannelHistory;
use JosskiTools\Helpers\KeyboardHelper;

/**
 * Message Handler - Handle all incoming messages
 */
class MessageHandler {

    private $bot;
    private $sessionManager;
    private $config;
    private $commandHandler;
    private $downloadHandler;
    private $tiktokUserHandler;

    public function __construct($bot, $sessionManager, $config) {
        $this->bot = $bot;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
        $this->commandHandler = new CommandHandler($bot, $sessionManager, $config);
        $this->downloadHandler = new DownloadHandler($bot, $sessionManager, $config);
        $this->tiktokUserHandler = new TikTokUserHandler($bot, $sessionManager, $config);

        // Initialize logging
        Logger::init($config['directories']['logs'] ?? null);
        UserLogger::init($config['directories']['logs'] ?? null);
        UserManager::init();
    }
    
    /**
     * Handle regular text message entry point from index.php
     */
    public function handleTextMessage($chatId, $userId, array $message) {
        $this->handle($message);
    }

    /**
     * Handle unsupported photo uploads gracefully
     */
    public function handlePhoto($chatId, $userId, array $message) {
        Logger::info("Photo received but feature not supported", [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);

        $this->bot->sendMessage(
            $chatId,
            "📷 Saat ini bot belum bisa memproses foto langsung. Kirim link atau gunakan menu yang tersedia ya."
        );
    }
    
    /**
     * Handle incoming message
     */
    public function handle($message) {
        Logger::debug("Message handler called", ['message_id' => $message['message_id'] ?? 'unknown']);

        $chatId = $message['chat']['id'] ?? null;
        $userId = $message['from']['id'] ?? null;
        $username = $message['from']['username'] ?? 'Unknown';
        $text = $message['text'] ?? '';
        if ($text === '' && isset($message['caption'])) {
            $text = $message['caption'];
        }
        $chatType = $message['chat']['type'] ?? 'private';

        if (!$chatId || !$userId) {
            Logger::warning("Missing chatId or userId in message");
            return;
        }

        // Register/update user
        UserManager::addUser($userId, [
            'username' => $username,
            'chat_type' => $chatType
        ]);
        
        // Handle new chat members (when bot is added to group)
        if (isset($message['new_chat_members'])) {
            try {
                foreach ($message['new_chat_members'] as $member) {
                    if ($member['is_bot'] ?? false) {
                        $this->handleBotAddedToGroup($chatId, $chatType);
                        return;
                    }
                }
            } catch (\Exception $e) {
                // Silent fail, continue processing
            }
        }
        
        // Handle document upload (HAR file)
        if (isset($message['document'])) {
            $this->handleDocument($message);
            return;
        }
        
        if (isset($message['forward_from_chat']) && $chatType === 'private') {
            if ($this->handleForwardedChannelMessage($chatId, $userId, $message)) {
                return;
            }
        }

        if (empty($text)) {
            error_log("Empty text, skipping");
            return;
        }
        
        error_log("=== MESSAGE HANDLER START ===");
        error_log("ChatId: {$chatId}, UserId: {$userId}, Text: {$text}");
        error_log("Chat Type: {$chatType}");
        
        // Parse command
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';
        
        // In groups, only respond to commands or when bot is mentioned
        if (in_array($chatType, ['group', 'supergroup'])) {
            // Remove @botusername from command if present
            $command = preg_replace('/@\w+$/', '', $command);
            
            // Only process commands starting with / or links
            if (strpos($command, '/') !== 0 && !$this->containsLink($text)) {
                error_log("Group: Not a command or link, skipping");
                return;
            }
        }
        
        // Handle keyboard button text (only in private chat)
        if ($chatType === 'private') {
            $buttonText = trim($text);
            
            error_log("Checking keyboard button: {$buttonText}");
            
            // Check for keyboard buttons first
            if ($this->handleKeyboardButton($chatId, $userId, $buttonText, $message)) {
                error_log("Keyboard button handled, returning");
                return;
            }
            
            error_log("Not a keyboard button, continuing...");
        }
        
        // Check for session states (awaiting input) FIRST before auto-detect - only in private
        // This ensures manual mode (keyboard button) takes priority over auto-detect
        if ($chatType === 'private') {
            error_log("Checking session state...");
            if ($this->handleSessionState($chatId, $userId, $text, $message)) {
                error_log("Session state handled, returning");
                return;
            }
            error_log("No session state, continuing...");
        }
        
        // Auto-detect links (works in both private and group)
        // Only runs if NOT in manual mode (no active session)
        error_log("Checking auto-detect links...");
        if ($this->handleAutoDetectLinks($chatId, $userId, $text, $message)) {
            error_log("Auto-detect handled, returning");
            return;
        }
        error_log("No auto-detect match, continuing to command handler...");
        
        // Handle commands
        $this->handleCommand($chatId, $userId, $username, $command, $args, $chatType, $message);
    }
    
    /**
     * Check if text contains a link
     */
    private function containsLink($text) {
        return preg_match('/(https?:\/\/[^\s]+)/i', $text);
    }
    
    /**
     * Get bot username from token
     */
    private function getBotUsername() {
        // You can cache this or get from config
        static $botUsername = null;
        if ($botUsername === null) {
            try {
                $result = $this->bot->request('getMe', []);
                $botUsername = $result['result']['username'] ?? 'bot';
            } catch (\Exception $e) {
                $botUsername = 'bot';
            }
        }
        return $botUsername;
    }
    
    /**
     * Handle when bot is added to group
     */
    private function handleBotAddedToGroup($chatId, $chatType) {
        $message = "👋 <b>Halo! Terima kasih telah menambahkan saya ke grup ini!</b>\n\n";
        $message .= "🤖 <b>JOSS HELPER BOT</b>\n";
        $message .= "════════════════════\n\n";
        
        if (in_array($chatType, ['group', 'supergroup'])) {
            $message .= "📌 <b>Cara Menggunakan Bot di Grup:</b>\n\n";
            $message .= "1️⃣ Gunakan perintah dengan /\n";
            $message .= "   Contoh: /start, /help, /menu\n\n";
            $message .= "2️⃣ Atau langsung kirim link untuk download\n";
            $message .= "   • TikTok, Facebook, YouTube\n";
            $message .= "   • Spotify, CapCut\n\n";
            $message .= "3️⃣ Bot akan otomatis mendeteksi link\n\n";
        } else {
            $message .= "📌 <b>Fitur Utama:</b>\n";
            $message .= "• Download video TikTok, Facebook, YouTube\n";
            $message .= "• Download audio Spotify\n";
            $message .= "• Download template CapCut\n";
            $message .= "• Auto-detect link\n\n";
        }
        
        $message .= "════════════════════\n";
        $message .= "Gunakan /help untuk melihat semua perintah!\n";
        $message .= "Gunakan /menu untuk melihat menu utama!";
        
        $this->bot->sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Handle keyboard button presses
     */
    private function handleKeyboardButton($chatId, $userId, $buttonText, $message = null) {
        $messageId = $message['message_id'] ?? null;

        $normalized = trim($buttonText);
        $normalizedLower = function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);

        // Main menu buttons
        if ($normalized === '📥 Downloader' || strpos($normalizedLower, 'downloader') !== false) {
            $this->sendDownloaderMenu($chatId);
            return true;
        }

        if ($normalized === '📚 Help' || strpos($normalizedLower, 'help') !== false) {
            $this->commandHandler->handleHelp($chatId);
            return true;
        }

        if ($normalized === '💝 Donasi' || strpos($normalizedLower, 'donasi') !== false) {
            $this->commandHandler->handleDonate($chatId, $userId, []);
            return true;
        }

        if ($normalized === '🎛️ Menu' || strpos($normalizedLower, 'menu') !== false) {
            $this->commandHandler->handleMenu($chatId);
            return true;
        }

        // Downloader menu buttons
        if ($normalized === '🎵 TikTok' || strpos($normalizedLower, 'tiktok') !== false) {
            $this->activateDownloadMode($chatId, $userId, 'tiktok', 'TikTok', $messageId);
            return true;
        }

        if ($normalized === '📘 Facebook' || strpos($normalizedLower, 'facebook') !== false) {
            $this->activateDownloadMode($chatId, $userId, 'facebook', 'Facebook', $messageId);
            return true;
        }

        if ($normalized === '🎧 Spotify' || strpos($normalizedLower, 'spotify') !== false) {
            $this->activateDownloadMode($chatId, $userId, 'spotify', 'Spotify', $messageId);
            return true;
        }

        if ($normalized === '📹 YouTube MP3' || strpos($normalizedLower, 'youtube mp3') !== false) {
            $this->activateDownloadMode($chatId, $userId, 'ytmp3', 'YouTube MP3', $messageId);
            return true;
        }

        if ($normalized === '🎬 YouTube MP4' || strpos($normalizedLower, 'youtube mp4') !== false) {
            $this->activateDownloadMode($chatId, $userId, 'ytmp4', 'YouTube MP4', $messageId);
            return true;
        }

        if ($normalized === '🎨 CapCut' || strpos($normalizedLower, 'capcut') !== false) {
            $this->activateDownloadMode($chatId, $userId, 'capcut', 'CapCut', $messageId);
            return true;
        }

        if ($normalized === '🏠 Main Menu' || strpos($normalizedLower, 'main menu') !== false) {
            $this->sessionManager->clearSession($userId);
            $keyboard = KeyboardHelper::getMainKeyboard();
            $this->bot->sendMessage($chatId, "🏠 *Main Menu*\n\nSelect an option:", 'Markdown', $keyboard);
            return true;
        }

        if ($normalized === '🔙 Menu Awal' || strpos($normalizedLower, 'menu awal') !== false) {
            $this->sessionManager->clearSession($userId);
            $keyboard = KeyboardHelper::getMainKeyboard();
            $this->bot->sendMessage(
                $chatId,
                "🏠 *Menu utama siap!*\nPilih fitur favoritmu lewat tombol di bawah.",
                'Markdown',
                $keyboard
            );
            return true;
        }

        if ($normalized === '❌ Cancel' || strpos($normalizedLower, 'cancel') !== false) {
            $this->commandHandler->handleCancel($chatId, $userId);
            return true;
        }

        return false;
    }

    private function handleForwardedChannelMessage($chatId, $userId, array $message) {
        // Only admin can setup channel
        $adminIds = $this->config['admin_ids'] ?? [];
        if (!in_array($userId, $adminIds)) {
            return false;
        }

        $forwardChat = $message['forward_from_chat'] ?? null;

        if (!$forwardChat || ($forwardChat['type'] ?? '') !== 'channel') {
            return false;
        }

        ChannelHistory::init($this->bot, $this->config);

        $channelId = (string)($forwardChat['id'] ?? '');
        if ($channelId === '') {
            return false;
        }

        if (strpos($channelId, '-100') !== 0 && (int)$channelId > 0) {
            $channelId = '-100' . ltrim($channelId, '-');
        }

        $title = $forwardChat['title'] ?? 'Channel';
        $username = $forwardChat['username'] ?? null;

        $result = ChannelHistory::setupChannel($userId, $channelId);

        $lines = [];
        $lines[] = "📡 *Channel Terdeteksi!*";
        $lines[] = '';
        $lines[] = "Nama: {$title}";
        if ($username) {
            $lines[] = "Username: @{$username}";
        }
        $lines[] = "ID: `{$channelId}`";
        $lines[] = '';
        $lines[] = $result['message'];

        if (!($result['success'] ?? false)) {
            $lines[] = '';
            $lines[] = "Pastikan bot sudah jadi admin di channel tersebut lalu forward ulang untuk mencoba lagi.";
        } else {
            $lines[] = '';
            $lines[] = "🚀 Semua hasil download berikutnya akan otomatis disalin ke channel ini.";
        }

        $this->bot->sendMessage($chatId, implode("\n", $lines), 'Markdown');

        return true;
    }

    /**
     * Auto-detect links from various platforms and handle accordingly
     * Supports: TikTok, Facebook, YouTube, Spotify, CapCut
     */
    private function handleAutoDetectLinks($chatId, $userId, $text, $message = null) {
        // Skip if it's a command
        if (strpos(trim($text), '/') === 0) {
            return false;
        }
        
        // Extract URL from text
        $urlPattern = '/(https?:\/\/[^\s]+)/i';
        if (!preg_match($urlPattern, $text, $matches)) {
            return false;
        }
        
        $url = $matches[1];
        
        // TikTok Detection
        if (preg_match('/(www\.|vt\.|vm\.)?tiktok\.com/i', $url)) {
            return $this->handleTikTokLink($chatId, $userId, $url);
        }
        
        // Facebook Detection
        if (preg_match('/(www\.)?(facebook\.com|fb\.watch|fb\.com)/i', $url)) {
            $this->bot->sendMessage($chatId, "📘 *Facebook Video Detected!*\n\nDownloading...", 'Markdown');
            $this->downloadHandler->handle($chatId, $url, 'facebook');
            return true;
        }
        
        // YouTube Detection
        if (preg_match('/(www\.)?(youtube\.com|youtu\.be)/i', $url)) {
            // Ask user: MP3 or MP4?
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎵 MP3 (Audio)', 'callback_data' => "yt_auto_mp3_{$userId}"],
                        ['text' => '📹 MP4 (Video)', 'callback_data' => "yt_auto_mp4_{$userId}"]
                    ],
                    [
                        ['text' => '❌ Cancel', 'callback_data' => 'download_cancel']
                    ]
                ]
            ];
            
            // Store URL and message ID in session for callback (AUTO-DETECT MODE)
            $messageId = $message['message_id'] ?? null;
            $this->sessionManager->setState($userId, 'youtube_url_pending_auto', [
                'url' => $url,
                'message_id' => $messageId
            ]);
            
            $this->bot->sendMessage($chatId, "📹 *YouTube Video Detected!*\n\nPilih format:", 'Markdown', $keyboard);
            return true;
        }
        
        // Spotify Detection
        if (preg_match('/(open\.)?spotify\.com/i', $url)) {
            $this->bot->sendMessage($chatId, "🎧 *Spotify Track Detected!*\n\nDownloading...", 'Markdown');
            $this->downloadHandler->handle($chatId, $url, 'spotify');
            return true;
        }
        
        // CapCut Detection
        if (preg_match('/capcut\.com/i', $url)) {
            $this->bot->sendMessage($chatId, "🎨 *CapCut Template Detected!*\n\nDownloading...", 'Markdown');
            $this->downloadHandler->handle($chatId, $url, 'capcut');
            return true;
        }
        
        // Instagram Detection
        if (preg_match('/(www\.)?(instagram\.com|instagr\.am)/i', $url)) {
            $this->bot->sendMessage($chatId, "📷 *Instagram Media Detected!*\n\nDownloading...", 'Markdown');
            $this->downloadHandler->handle($chatId, $url, 'instagram');
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle TikTok link detection
     * - Video link → Download video
     * - Profile link → Fetch user videos
     */
    private function handleTikTokLink($chatId, $userId, $url) {
        // Check if it's a profile/user link
        // Pattern: https://www.tiktok.com/@username
        if (preg_match('/@([a-zA-Z0-9._]+)(?:\/)?$/i', $url, $userMatch)) {
            $username = $userMatch[1];
            
            $confirmMsg = "🔍 *TikTok Profile Detected!*\n\n";
            $confirmMsg .= "Username: @{$username}\n\n";
            $confirmMsg .= "Mau lihat video dari user ini?";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Yes, Show Videos', 'callback_data' => "ttuser_{$username}_0"],
                        ['text' => '📥 Download ALL', 'callback_data' => "ttall_{$username}"]
                    ],
                    [
                        ['text' => '❌ Cancel', 'callback_data' => 'ttall_cancel']
                    ]
                ]
            ];
            
            $this->bot->sendMessage($chatId, $confirmMsg, 'Markdown', $keyboard);
            return true;
        }
        
        // Check if it's a video link
        // Patterns: /video/, vt.tiktok.com, vm.tiktok.com
        if (preg_match('/\/video\/|vt\.tiktok|vm\.tiktok/i', $url)) {
            $this->bot->sendMessage($chatId, "🎵 *TikTok Video Detected!*\n\nDownloading...", 'Markdown');
            $this->downloadHandler->handle($chatId, $url, 'tiktok');
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle session states
     */
    private function handleSessionState($chatId, $userId, $text, $message) {
        error_log("=== CHECKING SESSION STATE ===");
        error_log("UserId: {$userId}, Text: {$text}");
        
        $session = $this->sessionManager->getSession($userId);
        
        error_log("Session retrieved: " . json_encode($session));
        
        if (!$session) {
            error_log("No session found, returning false");
            return false;
        }
        
        $state = $session['state'] ?? '';
        error_log("Session state: {$state}");
        
        // Auto-detect URL and handle download
        $urlPattern = '/(https?:\/\/[^\s]+)/i';
        if (preg_match($urlPattern, $text, $matches)) {
            $url = $matches[1];
            error_log("URL detected: {$url}");
            
            // Check if we're in a download mode
            if (strpos($state, 'awaiting_') === 0 && strpos($state, '_url') !== false) {
                $platform = str_replace(['awaiting_', '_url'], '', $state);
                error_log("Download mode detected! Platform: {$platform}");
                
                // Get message ID for reply
                $messageId = $message['message_id'] ?? null;
                error_log("Message ID for reply: {$messageId}");
                
                // Clear session first
                $this->sessionManager->clearSession($userId);
                error_log("Session cleared");
                
                // Direct download (manual mode - no format selection)
                error_log("About to call downloadHandler->handle()");
                $this->downloadHandler->handle($chatId, $url, $platform, $messageId);
                error_log("Download handler call completed");
                return true;
            } else {
                error_log("NOT in download mode. State: {$state}");
            }
        } else {
            error_log("No URL pattern detected in text");
        }
        
        // Handle HAR file decryption key
        if ($state === 'awaiting_decryption_key') {
            $this->handleDecryptionKey($chatId, $userId, $text, $message);
            return true;
        }
        
        // Handle TikTok username input
        if ($state === 'awaiting_tiktok_username') {
            $username = trim($text);
            $username = ltrim($username, '@'); // Remove @ if present
            
            if (empty($username)) {
                $this->bot->sendMessage($chatId, "❌ Invalid username!");
                return true;
            }
            
            $this->sessionManager->clearSession($userId);
            $this->tiktokUserHandler->handle($chatId, $userId, $username);
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle commands
     */
    private function handleCommand($chatId, $userId, $username, $command, $args, $chatType = 'private', array $message = []) {
        switch ($command) {
            case '/start':
                $this->commandHandler->handleStart($chatId, $userId, $username, $chatType, $args);
                break;
                
            case '/help':
                $this->commandHandler->handleHelp($chatId);
                break;
                
            case '/menu':
                $this->commandHandler->handleMenu($chatId);
                break;
                
            case '/cancel':
                $this->commandHandler->handleCancel($chatId, $userId);
                break;

            case '/maintenance':
                $this->commandHandler->handleMaintenance($chatId, $userId, $args);
                break;

            case '/get':
                $this->commandHandler->handleGet($chatId, $userId, $args);
                break;

            case '/history':
                $this->commandHandler->handleHistory($chatId, $userId);
                break;

            case '/clearhistory':
                $this->commandHandler->handleClearHistory($chatId, $userId);
                break;

            case '/favorites':
                $this->commandHandler->handleFavorites($chatId, $userId);
                break;

            case '/setupchannel':
                $this->commandHandler->handleSetupChannel($chatId, $userId, $args);
                break;

            case '/channelinfo':
                $this->commandHandler->handleChannelInfo($chatId, $userId);
                break;

            case '/removechannel':
                $this->commandHandler->handleRemoveChannel($chatId, $userId);
                break;

            case '/share':
                $this->commandHandler->handleShare($chatId, $userId, $message, $args);
                break;
                
            // Hidden commands
            case '/codex':
                $this->commandHandler->handleCodex($chatId, $userId, $args);
                break;
                
            case '/ekstrakhar':
                $this->commandHandler->handleEkstrakhar($chatId, $userId);
                break;
                
            // Download commands
            case '/tiktok':
            case '/tt':
                $this->downloadHandler->handle($chatId, $args, 'tiktok');
                break;
                
            case '/facebook':
            case '/fb':
                $this->downloadHandler->handle($chatId, $args, 'facebook');
                break;
                
            case '/spotify':
                $this->downloadHandler->handle($chatId, $args, 'spotify');
                break;
                
            case '/ytmp3':
                $this->downloadHandler->handle($chatId, $args, 'ytmp3');
                break;
                
            case '/ytmp4':
                $this->downloadHandler->handle($chatId, $args, 'ytmp4');
                break;
                
            case '/capcut':
                $this->downloadHandler->handle($chatId, $args, 'capcut');
                break;
                
            // TikTok User Videos
            case '/ttuser':
            case '/tiktokuser':
                $username = trim($args);
                $username = ltrim($username, '@'); // Remove @ if present
                $this->tiktokUserHandler->handle($chatId, $userId, $username);
                break;
                
            // TikTok User - Download ALL
            case '/ttall':
            case '/tiktokall':
                $username = trim($args);
                $username = ltrim($username, '@');
                $this->tiktokUserHandler->handleDownloadAll($chatId, $userId, $username);
                break;
                
            default:
                // Unknown command - maybe it's just a regular message
                break;
        }
    }
    
    /**
     * Activate download mode for a platform
     */
    private function activateDownloadMode($chatId, $userId, $platform, $platformName, $replyToMessageId = null) {
        $this->sessionManager->setState($userId, 'awaiting_' . $platform . '_url');
        
        $emoji = [
            'tiktok' => '🎵',
            'facebook' => '📘',
            'spotify' => '🎧',
            'ytmp3' => '📹',
            'ytmp4' => '🎬',
            'capcut' => '🎨'
        ];
        
        $message = "{$emoji[$platform]} *{$platformName} DOWNLOADER*\n\n";
        $message .= "📩 *Reply pesan ini dengan link {$platformName}-mu*\n\n";
        $message .= "Link akan otomatis diproses! 🚀";
        
        $keyboard = KeyboardHelper::getDownloaderKeyboard();
        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard, $replyToMessageId);
    }
    
    /**
     * Send downloader menu
     */
    private function sendDownloaderMenu($chatId) {
        $message = "📥 *DOWNLOADER MENU*\n\n";
        $message .= "Pilih platform atau kirim link langsung:\n\n";
        $message .= "🎵 *TikTok* - Download TikTok videos\n";
        $message .= "📘 *Facebook* - Download FB videos\n";
        $message .= "🎧 *Spotify* - Download Spotify songs\n";
        $message .= "📹 *YouTube* - Download YT audio/video\n";
        $message .= "🎨 *CapCut* - Download CapCut templates\n\n";
        $message .= "💡 Tip: Gunakan button di bawah!";
        
        $keyboard = KeyboardHelper::getDownloaderKeyboard();
        
        try {
            $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
        } catch (\Exception $e) {
            $this->bot->sendMessage($chatId, $message, 'Markdown');
        }
    }
    
    /**
     * Handle chat member updates (when bot is added/removed from group)
     */
    public function handleChatMemberUpdate($chatMember) {
        $chat = $chatMember['chat'] ?? null;
        $newStatus = $chatMember['new_chat_member']['status'] ?? null;
        $oldStatus = $chatMember['old_chat_member']['status'] ?? null;
        $userId = $chatMember['new_chat_member']['user']['id'] ?? null;
        
        if (!$chat || !$userId) {
            return;
        }
        
        $chatId = $chat['id'];
        $chatType = $chat['type'] ?? 'private';
        
        // Get bot info to check if this update is about the bot itself
        try {
            $botInfo = $this->bot->request('getMe', []);
            $botId = $botInfo['result']['id'] ?? null;
            
            if ($botId && $userId === $botId) {
                // This update is about our bot
                if (in_array($newStatus, ['member', 'administrator']) && in_array($oldStatus, ['left', 'kicked'])) {
                    // Bot was added to group
                    $this->handleBotAddedToGroup($chatId, $chatType);
                } elseif (in_array($newStatus, ['left', 'kicked']) && in_array($oldStatus, ['member', 'administrator'])) {
                    // Bot was removed from group
                    // Log it but don't send message (we can't)
                    $logFile = __DIR__ . '/../../logs/webhook.log';
                    @file_put_contents($logFile, date('Y-m-d H:i:s') . " | Bot removed from chat: {$chatId}\n", FILE_APPEND);
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }
    
    /**
     * Handle document upload
     */
    public function handleDocument($message, $chatId = null, $userId = null) {
        Logger::info("Document received", [
            'chat_id' => $chatId ?? ($message['chat']['id'] ?? null),
            'user_id' => $userId ?? ($message['from']['id'] ?? null),
            'file_name' => $message['document']['file_name'] ?? null,
        ]);

        $chatId = $chatId ?? ($message['chat']['id'] ?? null);
        if (!$chatId) {
            Logger::warning("Document handler missing chat ID");
            return;
        }

        $fileName = $message['document']['file_name'] ?? 'document';

        $this->bot->sendMessage(
            $chatId,
            "📄 File *{$fileName}* diterima, namun pemrosesan HAR belum tersedia di versi ini.\n\n" .
            "Silakan kirim link langsung atau gunakan menu downloader.",
            'Markdown'
        );
    }
    
    /**
     * Handle decryption key input
     */
    private function handleDecryptionKey($chatId, $userId, $text, $message) {
        // Implementation untuk handle decryption key
        // Akan dipindahkan dari webhook.php yang lama
    }
}
