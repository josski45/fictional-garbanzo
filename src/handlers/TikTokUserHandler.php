<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\UserLogger;
use JosskiTools\Api\SSSTikProClient;
use JosskiTools\Helpers\ErrorHelper;
use JosskiTools\Helpers\KeyboardHelper;

/**
 * TikTok User Videos Handler - Download ALL videos from TikTok username
 */
class TikTokUserHandler {
    
    private $bot;
    private $sessionManager;
    private $config;
    
    public function __construct($bot, $sessionManager, $config) {
        $this->bot = $bot;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
    }
    
    /**
     * Handle /ttuser or /tiktokuser command
     * 
     * @param int $chatId Chat ID
     * @param int $userId User ID
     * @param string $username TikTok username
     * @param int $page Page number (0-5)
     */
    public function handle($chatId, $userId, $username = '', $page = 0) {
        // Log command usage
        UserLogger::logCommand($userId, '/ttuser', [
            'username' => $username,
            'page' => $page
        ]);
        
        // Stats manager not needed in modular version
        // global $statsManager;
        
        // If no username provided, activate mode
        if (empty($username)) {
            $this->activateMode($chatId, $userId);
            return;
        }
        
        // Validate page number
        if ($page < 0 || $page > 5) {
            $this->bot->sendMessage($chatId, "❌ Invalid page number! Page must be between 0-5");
            return;
        }
        
        // Send loading message and reuse it for status updates
        $loadingMsg = $this->bot->sendMessage($chatId, "⏳ Fetching videos from @{$username}...\n\nPage: {$page}");
        $loadingMsgId = $loadingMsg['result']['message_id'] ?? null;
        
        try {
            // Fetch videos
            $client = new SSSTikProClient();
            $result = $client->getUserVideos($username, $page);
            
            if (!$result['success']) {
                $errorMsg = ErrorHelper::getErrorMessage(null, $result['error'] ?? 'Failed to fetch videos');

                if ($loadingMsgId) {
                    $this->bot->editMessageText($chatId, $loadingMsgId, $errorMsg);
                } else {
                    $this->bot->sendMessage($chatId, $errorMsg);
                }

                return;
            }

            if ($loadingMsgId) {
                $this->bot->editMessageText(
                    $chatId,
                    $loadingMsgId,
                    $this->buildStatusMessage($result, true),
                    'Markdown'
                );
            }

            // Send results
            $this->sendVideoList($chatId, $result, $loadingMsgId);
            
            // Increment success counter
            // Stats tracking removed for modular version
            
        } catch (\Exception $e) {
            $errorMsg = ErrorHelper::getErrorMessage(null, $e->getMessage());

            if ($loadingMsgId) {
                $this->bot->editMessageText($chatId, $loadingMsgId, $errorMsg . "\n\n💡 If this persists, contact admin");
            } else {
                $this->bot->sendMessage($chatId, $errorMsg . "\n\n💡 If this persists, contact admin");
            }
        }
    }
    
    /**
     * Handle download ALL videos (all pages)
     */
    public function handleDownloadAll($chatId, $userId, $username = '') {
        // Log command usage
        UserLogger::logCommand($userId, '/ttall', [
            'username' => $username
        ]);
        
        // Stats manager not needed in modular version
        // global $statsManager;
        
        if (empty($username)) {
            $this->bot->sendMessage($chatId, "❌ Please provide TikTok username\n\nUsage: `/ttall @username`", 'Markdown');
            return;
        }
        
        // Send warning message
        $warningMsg = "⚠️ *WARNING*\n\n";
        $warningMsg .= "Ini akan download SEMUA video dari @{$username}\n";
        $warningMsg .= "(up to 6 pages = ~60 videos)\n\n";
        $warningMsg .= "Proses ini akan memakan waktu lama!\n\n";
        $warningMsg .= "Lanjutkan?";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Yes, Continue', 'callback_data' => "ttall_confirm_{$username}"],
                    ['text' => '❌ Cancel', 'callback_data' => 'ttall_cancel']
                ]
            ]
        ];
        
        $this->bot->sendMessage($chatId, $warningMsg, 'Markdown', $keyboard);
    }
    
    /**
     * Process download all videos after confirmation
     */
    public function processDownloadAll($chatId, $username) {
        // Stats manager not needed in modular version
        // global $statsManager;
        
        // Send processing message
        $processingMsg = $this->bot->sendMessage($chatId, "⏳ Processing...\n\nFetching ALL videos from @{$username}\n\nThis may take a while... ☕");
        $processingMsgId = $processingMsg['result']['message_id'] ?? null;
        
        try {
            $client = new SSSTikProClient();
            $result = $client->getAllUserVideos($username);
            
            if (!$result['success']) {
                $errorMsg = ErrorHelper::getErrorMessage(null, 'Failed to fetch videos');

                if ($processingMsgId) {
                    $this->bot->editMessageText($chatId, $processingMsgId, $errorMsg);
                } else {
                    $this->bot->sendMessage($chatId, $errorMsg);
                }

                return;
            }

            if ($processingMsgId) {
                $this->bot->editMessageText(
                    $chatId,
                    $processingMsgId,
                    "✅ Found {$result['total_videos']} videos!\n\nPreparing to send..."
                );
            }
            
            // Send videos in batches
            $this->sendVideosBatch($chatId, $result['videos'], $username);
            
            $completionText = "✅ *Download Complete!*\n\n";
            $completionText .= "Username: @{$username}\n";
            $completionText .= "Total Videos: {$result['total_videos']}\n\n";
            $completionText .= "All download links have been sent! 🎉";

            if ($processingMsgId) {
                $this->bot->editMessageText($chatId, $processingMsgId, $completionText, 'Markdown');
            } else {
                $this->bot->sendMessage($chatId, $completionText, 'Markdown');
            }
            
            // Stats tracking removed for modular version
            
        } catch (\Exception $e) {
            $errorMsg = ErrorHelper::getErrorMessage(null, $e->getMessage());

            if ($processingMsgId) {
                $this->bot->editMessageText($chatId, $processingMsgId, $errorMsg . "\n\n💡 If this persists, contact admin");
            } else {
                $this->bot->sendMessage($chatId, $errorMsg . "\n\n💡 If this persists, contact admin");
            }
        }
    }
    
    /**
     * Send video list to user - AUTO DOWNLOAD
     */
    private function sendVideoList($chatId, $result, $statusMessageId = null) {
        $username = $result['username'];
        $page = $result['page'];
        $videos = $result['videos'];
        $hasMore = $result['has_more'];
        
        $headerMessage = $this->buildStatusMessage($result, true);
        $activeMessageId = $statusMessageId;

        if ($statusMessageId) {
            $this->bot->editMessageText($chatId, $statusMessageId, $headerMessage, 'Markdown');
        } else {
            $sentMessage = $this->bot->sendMessage($chatId, $headerMessage, 'Markdown');
            $activeMessageId = $sentMessage['result']['message_id'] ?? null;
        }
        
        // Send all videos directly (auto download)
        $this->sendVideoBatch($chatId, $videos, 1);
        
        $keyboard = $this->buildNavigationKeyboard($username, $page, $hasMore);

        $completionMessage = $this->buildStatusMessage($result, false) . "\n";
        $completionMessage .= $hasMore && $page < 5
            ? "➡️ More videos available on next page"
            : "📌 No more videos";

        if ($activeMessageId) {
            $this->bot->editMessageText($chatId, $activeMessageId, $completionMessage, 'Markdown', $keyboard);
        } else {
            $this->bot->sendMessage($chatId, $completionMessage, 'Markdown', $keyboard);
        }
    }
    
    /**
     * Send batch of videos - AUTO DOWNLOAD AND SEND
     */
    private function sendVideoBatch($chatId, $videos, $startNum) {
        foreach ($videos as $index => $video) {
            $num = $startNum + $index;
            $title = $video['title'] ?? 'No Title';
            $views = $video['views'] ?? 'N/A';
            $downloadUrl = $video['download_url'] ?? null;
            
            if (!$downloadUrl) {
                // Skip if no download URL
                $this->bot->sendMessage($chatId, "❌ Video #{$num}: No download URL available");
                continue;
            }
            
            try {
                // Create caption
                $caption = "📹 Video #{$num}\n";
                $caption .= "📝 {$title}\n";
                $caption .= "👁️ {$views} views";
                
                // Send video directly by URL
                $result = $this->bot->sendVideo($chatId, $downloadUrl, $caption);
                
                // Check if send was successful
                if (!isset($result['ok']) || !$result['ok']) {
                    // If URL send fails, notify user with download link
                    $fallbackMsg = "⚠️ Video #{$num}: {$title}\n";
                    $fallbackMsg .= "📥 Download: {$downloadUrl}";
                    $this->bot->sendMessage($chatId, $fallbackMsg);
                }
                
                // Small delay to avoid rate limit (Telegram allows max 30 messages/second)
                usleep(100000); // 0.1 second delay
                
            } catch (\Exception $e) {
                // If exception, send download link as fallback
                $fallbackMsg = "⚠️ Video #{$num}: {$title}\n";
                $fallbackMsg .= "📥 Download: {$downloadUrl}\n";
                $fallbackMsg .= "Error: " . $e->getMessage();
                $this->bot->sendMessage($chatId, $fallbackMsg);
            }
        }
    }
    
    /**
     * Send videos in batches for download all
     */
    private function sendVideosBatch($chatId, $videos, $username) {
        $totalVideos = count($videos);
        $batchSize = 10;
        
        for ($i = 0; $i < $totalVideos; $i += $batchSize) {
            $batch = array_slice($videos, $i, $batchSize);
            $batchNum = floor($i / $batchSize) + 1;
            $totalBatches = ceil($totalVideos / $batchSize);
            
            // Send batch header
            $message = "📦 *Batch {$batchNum}/{$totalBatches}*\n";
            $message .= "Videos " . ($i + 1) . "-" . min($i + $batchSize, $totalVideos) . " of {$totalVideos}\n";
            $message .= "⏳ Sending...\n";
            
            $this->bot->sendMessage($chatId, $message, 'Markdown');
            
            // Send videos in this batch
            $this->sendVideoBatch($chatId, $batch, $i + 1);
            
            // Send batch completion message
            $completionMsg = "✅ Batch {$batchNum}/{$totalBatches} completed!\n";
            if ($i + $batchSize < $totalVideos) {
                $completionMsg .= "⏳ Continuing with next batch...";
            }
            $this->bot->sendMessage($chatId, $completionMsg);
            
            // Small delay between batches
            if ($i + $batchSize < $totalVideos) {
                sleep(2); // 2 seconds delay between batches
            }
        }
    }
    
    /**
     * Activate TikTok user mode
     */
    private function activateMode($chatId, $userId) {
        $this->sessionManager->setState($userId, 'awaiting_tiktok_username');
        
        $message = "📱 *TIKTOK USER DOWNLOADER*\n\n";
        $message .= "✅ Mode aktif!\n\n";
        $message .= "Kirim username TikTok (dengan atau tanpa @)\n\n";
        $message .= "📋 Contoh:\n";
        $message .= "`@siigma`\n";
        $message .= "atau\n";
        $message .= "`siigma`\n\n";
        $message .= "💡 Tip: Saya akan ambil video dari page 0 dulu";
        
        $keyboard = KeyboardHelper::getCancelKeyboard();
        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }

    /**
     * Build status message for header/completion states.
     */
    private function buildStatusMessage(array $result, bool $isProcessing): string {
        $username = $this->escapeMarkdown($result['username'] ?? 'unknown');
        $nickname = $result['nickname'] ?? null;
        $page = (int)($result['page'] ?? 0);
        $count = count($result['videos'] ?? []);

        $message = "📱 *TIKTOK USER VIDEOS*\n\n";
        $message .= "👤 Username: @{$username}\n";

        if (!empty($nickname)) {
            $message .= "🧾 Name: " . $this->escapeMarkdown($nickname) . "\n";
        }

        $message .= "📄 Page: {$page}\n";
        $message .= "📹 Videos Found: {$count}\n\n";
        $message .= $isProcessing
            ? "⏳ Sending videos... Please wait..."
            : "✅ Videos sent! Scroll up to view them.";

        return $message;
    }

    /**
     * Build navigation keyboard for pagination.
     */
    private function buildNavigationKeyboard(string $username, int $page, bool $hasMore): array {
        $keyboard = ['inline_keyboard' => []];

        if ($page > 0) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '⬅️ Previous Page', 'callback_data' => "ttuser_{$username}_" . ($page - 1)]
            ];
        }

        if ($hasMore && $page < 5) {
            $keyboard['inline_keyboard'][] = [
                ['text' => 'Next Page ➡️', 'callback_data' => "ttuser_{$username}_" . ($page + 1)]
            ];
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => '📥 Download ALL Videos (up to 60)', 'callback_data' => "ttall_{$username}"]
        ];

        return $keyboard;
    }

    /**
     * Escape Telegram Markdown special characters.
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
}
