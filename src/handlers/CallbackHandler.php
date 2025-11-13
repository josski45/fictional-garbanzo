<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Helpers\KeyboardHelper;

/**
 * Callback Handler - Handle callback queries from inline keyboards
 */
class CallbackHandler {
    
    private $bot;
    private $sessionManager;
    private $config;
    private $tiktokUserHandler;
    private $downloadHandler;
    
    public function __construct($bot, $sessionManager, $config) {
        $this->bot = $bot;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
        $this->tiktokUserHandler = new TikTokUserHandler($bot, $sessionManager, $config);
        $this->downloadHandler = new DownloadHandler($bot, $sessionManager, $config);
    }
    
    /**
     * Handle callback query
     */
    public function handle($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $userId = $callbackQuery['from']['id'] ?? $chatId;
        $callbackId = $callbackQuery['id'];
        $data = $callbackQuery['data'];
        
        $this->bot->answerCallbackQuery($callbackId);
        
        // Parse callback data
        $parts = explode('_', $data, 3);
        $action = $parts[0];
        
        switch ($action) {
            case 'menu':
                $this->handleMenuCallback($chatId, $data);
                break;
                
            case 'ttuser':
                $this->handleTikTokUserCallback($chatId, $data);
                break;
                
            case 'ttall':
                $this->handleTikTokAllCallback($chatId, $data);
                break;
                
            case 'yt':
                // Check if it's auto-detect mode or manual mode
                if (strpos($data, 'yt_auto_') === 0) {
                    $this->handleYouTubeAutoCallback($chatId, $userId, $data, $callbackQuery);
                } else {
                    $this->handleYouTubeCallback($chatId, $userId, $data, $callbackQuery);
                }
                break;
                
            case 'download':
                $this->handleDownloadCallback($chatId, $data);
                break;
                
            default:
                // Legacy switch
                switch ($data) {
                    case 'menu_downloader':
                        $this->sendDownloaderMenu($chatId);
                        break;
                        
                    case 'menu_help':
                        require_once __DIR__ . '/CommandHandler.php';
                        $commandHandler = new CommandHandler($this->bot, $this->sessionManager, $this->config);
                        $commandHandler->handleHelp($chatId);
                        break;
                }
                break;
        }
    }
    
    /**
     * Handle menu callbacks
     */
    private function handleMenuCallback($chatId, $data) {
        switch ($data) {
            case 'menu_downloader':
                $this->sendDownloaderMenu($chatId);
                break;
                
            case 'menu_help':
                require_once __DIR__ . '/CommandHandler.php';
                $commandHandler = new CommandHandler($this->bot, $this->sessionManager, $this->config);
                $commandHandler->handleHelp($chatId);
                break;
        }
    }
    
    /**
     * Handle TikTok user pagination callbacks
     * Format: ttuser_USERNAME_PAGE
     */
    private function handleTikTokUserCallback($chatId, $data) {
        $parts = explode('_', $data, 3);
        
        if (count($parts) < 3) {
            $this->bot->sendMessage($chatId, "❌ Invalid callback data");
            return;
        }
        
        $username = $parts[1];
        $page = (int)$parts[2];
        
        $this->tiktokUserHandler->handle($chatId, 0, $username, $page);
    }
    
    /**
     * Handle TikTok download all callbacks
     * Format: ttall_USERNAME or ttall_confirm_USERNAME or ttall_cancel
     */
    private function handleTikTokAllCallback($chatId, $data) {
        $parts = explode('_', $data, 3);
        
        if (count($parts) < 2) {
            $this->bot->sendMessage($chatId, "❌ Invalid callback data");
            return;
        }
        
        $subAction = $parts[1];
        
        if ($subAction === 'cancel') {
            $this->bot->sendMessage($chatId, "❌ Cancelled");
            return;
        }
        
        if ($subAction === 'confirm') {
            $username = $parts[2] ?? '';
            if (empty($username)) {
                $this->bot->sendMessage($chatId, "❌ Invalid username");
                return;
            }
            
            $this->tiktokUserHandler->processDownloadAll($chatId, $username);
        } else {
            // Format: ttall_USERNAME
            $username = $subAction;
            $this->tiktokUserHandler->handleDownloadAll($chatId, 0, $username);
        }
    }
    
    /**
     * Handle YouTube format selection callbacks - AUTO-DETECT MODE
     * Format: yt_auto_mp3_USERID or yt_auto_mp4_USERID
     * This is when user sends a YouTube link directly (not via keyboard button)
     */
    private function handleYouTubeAutoCallback($chatId, $userId, $data, $callbackQuery) {
        // Debug logging
        error_log("YouTube AUTO callback - ChatId: {$chatId}, UserId: {$userId}, Data: {$data}");
        
        $parts = explode('_', $data, 4);
        
        if (count($parts) < 3) {
            $this->bot->sendMessage($chatId, "❌ Invalid callback data");
            return;
        }
        
        $format = $parts[2]; // mp3 or mp4
        
        // Get URL from session
        $session = $this->sessionManager->getSession($userId);
        
        // Debug logging
        error_log("Session data: " . json_encode($session));
        
        // Check if session state is youtube_url_pending_auto
        if (!$session || $session['state'] !== 'youtube_url_pending_auto') {
            error_log("Session check failed - Session: " . ($session ? 'exists' : 'null') . ", State: " . ($session['state'] ?? 'none'));
            $this->bot->sendMessage($chatId, "❌ Session expired. Please send the link again.");
            return;
        }
        
        // Get URL and message ID from session data
        $url = $session['data']['url'] ?? '';
        $replyToMessageId = $session['data']['message_id'] ?? null;
        
        error_log("URL from session: {$url}, Reply to: {$replyToMessageId}");
        
        if (empty($url)) {
            $this->bot->sendMessage($chatId, "❌ URL not found");
            return;
        }
        
        // Delete the "YouTube Video Detected!" message
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        if ($messageId) {
            try {
                $this->bot->deleteMessage($chatId, $messageId);
            } catch (\Exception $e) {
                // Silently fail if can't delete
            }
        }
        
        // Clear session
        $this->sessionManager->clearSession($userId);
        
        error_log("=== ABOUT TO CALL DOWNLOAD HANDLER ===");
        error_log("Format: {$format}, ChatId: {$chatId}, URL: {$url}, ReplyTo: {$replyToMessageId}");
        
        try {
            // Download based on format with reply to original message
            if ($format === 'mp3') {
                error_log("Calling downloadHandler->handle() for ytmp3");
                $this->downloadHandler->handle($chatId, $url, 'ytmp3', $replyToMessageId);
                error_log("Download handler completed for ytmp3");
            } else if ($format === 'mp4') {
                error_log("Calling downloadHandler->handle() for ytmp4");
                $this->downloadHandler->handle($chatId, $url, 'ytmp4', $replyToMessageId);
                error_log("Download handler completed for ytmp4");
            }
        } catch (\Exception $e) {
            // Log error for debugging
            error_log("=== EXCEPTION IN DOWNLOAD HANDLER ===");
            error_log("YouTube download error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->bot->sendMessage($chatId, "❌ Error: " . $e->getMessage());
        }
        
        error_log("=== CALLBACK HANDLER COMPLETED ===");
    }
    
    /**
     * Handle YouTube format selection callbacks - MANUAL MODE
     * Format: yt_mp3_USERID or yt_mp4_USERID  
     * This is when user clicks keyboard button "📹 YouTube MP3/MP4" first
     */
    private function handleYouTubeCallback($chatId, $userId, $data, $callbackQuery) {
        // This shouldn't be called anymore since manual mode goes direct to download
        // But keep for backward compatibility
        $this->bot->sendMessage($chatId, "❌ Please use the auto-detect feature by sending YouTube link directly.");
    }
    
    /**
     * Handle download action callbacks
     */
    private function handleDownloadCallback($chatId, $data) {
        if ($data === 'download_cancel') {
            $this->bot->sendMessage($chatId, "❌ Cancelled");
        }
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
}
