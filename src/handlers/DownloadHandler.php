<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\StatsManager;
use JosskiTools\Utils\Logger;
use JosskiTools\Utils\UserLogger;
use JosskiTools\Utils\UserManager;
use JosskiTools\Utils\RateLimiter;
use JosskiTools\Utils\DownloadHistory;
use JosskiTools\Utils\DonationManager;
use JosskiTools\Utils\ChannelHistory;
use JosskiTools\Api\NekoLabsClient;
use JosskiTools\Responses\NekoLabsResponseHandler;
use JosskiTools\Helpers\ErrorHelper;

/**
 * Download Handler - Handle all download operations using NekoLabs API
 */
class DownloadHandler {

    private $bot;
    private $sessionManager;
    private $config;
    private $statsManager;
    private $nekoLabsClient;

    public function __construct($bot, $sessionManager, $config) {
        $this->bot = $bot;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
        $this->statsManager = new StatsManager();

        // Initialize NekoLabs client with default version v1
        $apiVersion = $config['NEKOLABS_API_VERSION'] ?? 'v1';
        $this->nekoLabsClient = new NekoLabsClient($apiVersion);

        // Initialize logging and utilities
        Logger::init($config['directories']['logs'] ?? null);
        UserLogger::init($config['directories']['logs'] ?? null);
        UserManager::init();
        RateLimiter::init($config['directories']['data'] ?? null);
        DownloadHistory::init($config['directories']['data'] ?? null);
        DonationManager::init($config['directories']['data'] ?? null);
        ChannelHistory::init($bot, $config);
    }

    /**
     * Handle download command
     */
    public function handle($chatId, $url, $platform = null, $replyToMessageId = null) {
        Logger::info("Download request started", [
            'chat_id' => $chatId,
            'platform' => $platform,
            'url' => $url
        ]);

        // Register/update user
        UserManager::addUser($chatId, [
            'username' => null, // Will be updated by CommandHandler if available
            'last_platform' => $platform
        ]);

        // Check rate limit
        $rateLimitCheck = RateLimiter::check($chatId);
        if (!$rateLimitCheck['allowed']) {
            Logger::warning("Rate limit exceeded", [
                'user_id' => $chatId,
                'reason' => $rateLimitCheck['reason']
            ]);

            $this->bot->sendMessage($chatId, $rateLimitCheck['message']);
            return;
        }

        // Log user activity
        UserLogger::logDownload($chatId, $platform ?? 'auto', $url);

        // Validate URL
        if (empty($url)) {
            Logger::warning("Empty URL provided", ['chat_id' => $chatId]);
            UserLogger::logError($chatId, "Empty URL provided");

            $this->bot->sendMessage(
                $chatId,
                "❌ Please provide a URL\n\nExample: /download https://example.com/video"
            );
            return;
        }

        $url = trim($url);

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Logger::warning("Invalid URL format", ['chat_id' => $chatId, 'url' => $url]);
            UserLogger::logError($chatId, "Invalid URL format", ['url' => $url]);

            $this->bot->sendMessage($chatId, "❌ Invalid URL format!");
            $this->statsManager->incrementRequest(false);
            return;
        }

        // Detect platform if not specified
        if (!$platform) {
            $platform = NekoLabsClient::detectPlatform($url);
            Logger::debug("Platform detected", ['platform' => $platform, 'url' => $url]);
        }

        // Send loading animation
        $loadingMsgId = $this->bot->sendLoadingMessage($chatId, "Initializing", $replyToMessageId);

        try {
            // Update loading - Step 1: Validating
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Validating URL", 20);
            }

            // Update loading - Step 2: Fetching
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Fetching data", 40);
            }

            // Call NekoLabs API
            $result = $this->nekoLabsClient->download($url);

            // Check if API call was successful
            if (!$result['success']) {
                Logger::error("NekoLabs API error", [
                    'chat_id' => $chatId,
                    'error' => $result['error'] ?? 'Unknown error',
                    'url' => $url
                ]);

                UserLogger::logError($chatId, "API Error", [
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                if ($loadingMsgId) {
                    $this->bot->deleteMessage($chatId, $loadingMsgId);
                }

                $errorMsg = "❌ Download failed: " . ($result['error'] ?? 'Unknown error');

                // Add helpful message based on error
                if (isset($result['status_code'])) {
                    switch ($result['status_code']) {
                        case 400:
                            $errorMsg .= "\n\n💡 Please check if the URL is valid and accessible.";
                            break;
                        case 429:
                            $errorMsg .= "\n\n⏳ Rate limit exceeded. Please try again in a few moments.";
                            break;
                        case 500:
                            $errorMsg .= "\n\n🔧 Server error. Please try again later.";
                            break;
                    }
                }

                $this->bot->sendMessage($chatId, $errorMsg);
                $this->statsManager->incrementRequest(false);
                return;
            }

            // Update loading - Step 3: Processing
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Processing media", 70);
            }

            // Update loading - Step 4: Preparing
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Preparing download", 90);
            }

            // Track successful request
            $this->statsManager->incrementRequest(true);

            Logger::info("Download successful", [
                'chat_id' => $chatId,
                'source' => $result['result']['source'] ?? 'unknown',
                'type' => $result['result']['type'] ?? 'unknown'
            ]);

            UserLogger::log($chatId, "Download successful", [
                'source' => $result['result']['source'] ?? 'unknown',
                'type' => $result['result']['type'] ?? 'unknown'
            ]);

            // Add to download history
            DownloadHistory::addDownload(
                $chatId,
                $url,
                $result['result']['source'] ?? $platform ?? 'unknown',
                $result['result']['title'] ?? null,
                $result['result']['type'] ?? null
            );

            // Handle response using NekoLabsResponseHandler
            $responseHandler = new NekoLabsResponseHandler($this->bot);
            $responseHandler->handle($chatId, $result['result'], $loadingMsgId);

            // Forward to user's history channel if setup
            if (ChannelHistory::hasChannel($chatId)) {
                try {
                    // Get first media from result
                    $firstMedia = $result['result']['medias'][0] ?? null;

                    if ($firstMedia && isset($firstMedia['url'])) {
                        ChannelHistory::sendToChannel(
                            $chatId,
                            $firstMedia['url'],
                            $firstMedia['type'] ?? 'video',
                            [
                                'platform' => $result['result']['source'] ?? 'unknown',
                                'title' => $result['result']['title'] ?? 'No title',
                                'url' => $url
                            ]
                        );

                        Logger::debug("Forwarded to user's history channel", [
                            'user_id' => $chatId,
                            'platform' => $result['result']['source'] ?? 'unknown'
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the download
                    Logger::warning("Failed to forward to history channel", [
                        'user_id' => $chatId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

        } catch (\Exception $e) {
            Logger::exception($e, [
                'chat_id' => $chatId,
                'url' => $url,
                'platform' => $platform
            ]);

            UserLogger::logError($chatId, "Exception occurred", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            if ($loadingMsgId) {
                $this->bot->deleteMessage($chatId, $loadingMsgId);
            }

            // Use error helper for exception messages
            $errorMsg = ErrorHelper::getErrorMessage(null, $e->getMessage());

            // Add contact info for persistent errors
            if (strpos($errorMsg, 'Server') !== false || strpos($errorMsg, '500') !== false) {
                $errorMsg .= "\n\n💡 If this persists, contact admin";
            }

            $this->bot->sendMessage($chatId, $errorMsg);
            $this->statsManager->incrementRequest(false);
        }
    }

    /**
     * Handle download with specific API version
     */
    public function handleWithVersion($chatId, $url, $version, $platform = null, $replyToMessageId = null) {
        // Set API version
        $this->nekoLabsClient->setVersion($version);

        Logger::info("Download request with specific version", [
            'chat_id' => $chatId,
            'version' => $version,
            'platform' => $platform
        ]);

        // Call regular handle method
        $this->handle($chatId, $url, $platform, $replyToMessageId);
    }

    /**
     * Get supported platforms
     */
    public function getSupportedPlatforms() {
        return NekoLabsClient::getSupportedPlatforms();
    }

    /**
     * Test API connection
     */
    public function testApi($chatId) {
        Logger::info("API test requested", ['chat_id' => $chatId]);
        UserLogger::logCommand($chatId, "test_api");

        $this->bot->sendMessage($chatId, "🧪 Testing NekoLabs API connection...");

        try {
            $result = $this->nekoLabsClient->test();

            if ($result['success']) {
                $message = "✅ **API Connection Successful**\n\n";
                $message .= "🌐 **Version:** " . $this->nekoLabsClient->getVersion() . "\n";
                $message .= "⚡ **Response Time:** " . ($result['responseTime'] ?? 'N/A') . "\n";
                $message .= "📦 **Source:** " . ($result['result']['source'] ?? 'N/A');

                $this->bot->sendMessage($chatId, $message, 'Markdown');

                Logger::info("API test successful", ['chat_id' => $chatId]);
            } else {
                $message = "❌ **API Connection Failed**\n\n";
                $message .= "**Error:** " . ($result['error'] ?? 'Unknown error');

                $this->bot->sendMessage($chatId, $message, 'Markdown');

                Logger::error("API test failed", [
                    'chat_id' => $chatId,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Logger::exception($e, ['chat_id' => $chatId, 'action' => 'test_api']);

            $this->bot->sendMessage(
                $chatId,
                "❌ API test failed: " . $e->getMessage()
            );
        }
    }
}
