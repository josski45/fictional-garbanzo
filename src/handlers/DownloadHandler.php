<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\StatsManager;
use JosskiTools\Api\Ferdev\Downloader;
use JosskiTools\Helpers\ErrorHelper;

/**
 * Download Handler - Handle all download operations
 */
class DownloadHandler {
    
    private $bot;
    private $sessionManager;
    private $config;
    private $statsManager;
    
    public function __construct($bot, $sessionManager, $config) {
        $this->bot = $bot;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
        $this->statsManager = new StatsManager();
    }
    
    /**
     * Handle download command
     */
    public function handle($chatId, $url, $platform, $replyToMessageId = null) {
        error_log("=== DOWNLOAD HANDLER STARTED ===");
        error_log("ChatId: {$chatId}, Platform: {$platform}, URL: {$url}, ReplyTo: {$replyToMessageId}");
        
        // Log user activity
        $this->logUserActivity($chatId, $platform, $url);
        
        if (empty($url)) {
            error_log("ERROR: Empty URL provided");
            $this->bot->sendMessage($chatId, "❌ Please provide a URL\n\nExample: /{$platform} https://example.com/video");
            return;
        }
        
        $url = trim($url);
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->bot->sendMessage($chatId, "❌ Invalid URL format!");
            $this->statsManager->incrementRequest(false);
            return;
        }
        
        // Send loading animation with progress bar (reply to original message if provided)
        $loadingMsgId = $this->bot->sendLoadingMessage($chatId, "Initializing", $replyToMessageId);
        
        try {
            // Update loading animation - Step 1
            // Anti-flood: updateLoadingMessage already handles timing
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Validating URL", 20);
            }
            
            $downloader = new Downloader($this->config['FERDEV_API_KEY']);
            
            // Update loading animation - Step 2
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Fetching data", 40);
            }
            
            // Retry mechanism for API calls
            $maxRetries = 3;
            $retryDelay = 2; // seconds
            $result = null;
            $lastError = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                error_log("API Call Attempt {$attempt}/{$maxRetries}");
                
                try {
                    // Call the appropriate method based on platform
                    $result = $downloader->{$platform}($url);
                    
                    error_log("API Result (Attempt {$attempt}): " . json_encode($result));
                    
                    // Check if result is valid
                    if (!$result || !is_array($result)) {
                        $lastError = "Invalid response from server (not array or null)";
                        error_log("ERROR: {$lastError}");
                        
                        if ($attempt < $maxRetries) {
                            error_log("Retrying in {$retryDelay} seconds...");
                            if ($loadingMsgId) {
                                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Retrying... ({$attempt}/{$maxRetries})", 40 + ($attempt * 10));
                            }
                            sleep($retryDelay);
                            $retryDelay *= 2; // Exponential backoff
                            continue;
                        }
                        break;
                    }
                    
                    // Check status code
                    $statusCode = $result['status_code'] ?? $result['statusCode'] ?? 200;
                    error_log("API Status Code: {$statusCode}");
                    
                    if ($statusCode !== 200) {
                        $lastError = "Non-200 status code: {$statusCode}";
                        error_log("ERROR: {$lastError}");
                        
                        if ($attempt < $maxRetries) {
                            error_log("Retrying in {$retryDelay} seconds...");
                            if ($loadingMsgId) {
                                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Retrying... ({$attempt}/{$maxRetries})", 40 + ($attempt * 10));
                            }
                            sleep($retryDelay);
                            $retryDelay *= 2; // Exponential backoff
                            continue;
                        }
                        break;
                    }
                    
                    // Check success flag
                    $success = $result['success'] ?? false;
                    
                    if (!$success) {
                        $errorMessage = $result['message'] ?? $result['error'] ?? 'Unknown error';
                        $lastError = "API returned success=false: {$errorMessage}";
                        error_log("ERROR: {$lastError}");
                        
                        if ($attempt < $maxRetries) {
                            error_log("Retrying in {$retryDelay} seconds...");
                            if ($loadingMsgId) {
                                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Retrying... ({$attempt}/{$maxRetries})", 40 + ($attempt * 10));
                            }
                            sleep($retryDelay);
                            $retryDelay *= 2; // Exponential backoff
                            continue;
                        }
                        break;
                    }
                    
                    // Success! Break retry loop
                    error_log("API Call Successful on attempt {$attempt}");
                    break;
                    
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    error_log("API Exception (Attempt {$attempt}): {$lastError}");
                    
                    if ($attempt < $maxRetries) {
                        error_log("Retrying in {$retryDelay} seconds...");
                        if ($loadingMsgId) {
                            $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Retrying... ({$attempt}/{$maxRetries})", 40 + ($attempt * 10));
                        }
                        sleep($retryDelay);
                        $retryDelay *= 2; // Exponential backoff
                        continue;
                    }
                    break;
                }
            }
            
            // Check if we have a valid result after retries
            if (!$result || !is_array($result) || !($result['success'] ?? false)) {
                if ($loadingMsgId) {
                    $this->bot->deleteMessage($chatId, $loadingMsgId);
                }
                
                $errorMsg = "❌ API Error after {$maxRetries} attempts";
                if ($lastError) {
                    $errorMsg .= ": {$lastError}";
                }
                
                $this->bot->sendMessage($chatId, $errorMsg);
                $this->statsManager->incrementRequest(false);
                error_log("ERROR: All retry attempts failed");
                return;
            }
            
            // Update loading animation - Step 3
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Processing media", 70);
            }
            
            // Update loading animation - Step 4
            if ($loadingMsgId) {
                $this->bot->updateLoadingMessage($chatId, $loadingMsgId, "Preparing download", 90);
            }
            
            // Track successful request
            $this->statsManager->incrementRequest(true);
            
            $data = $result['data'];
            
            // Debug logging
            error_log("Platform: {$platform}");
            error_log("API Response: " . json_encode($result));
            error_log("Data extracted: " . json_encode($data));
            
            // Load response handlers
            require_once __DIR__ . '/../responses/ResponseHandler.php';
            $responseHandler = new \JosskiTools\Responses\ResponseHandler($this->bot);
            
            // Handle different platform responses
            $responseHandler->handle($chatId, $platform, $data, $result, $loadingMsgId);
            
        } catch (\Exception $e) {
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
     * Log user activity to separate log file
     */
    private function logUserActivity($chatId, $platform, $url) {
        $logsDir = __DIR__ . '/../../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0777, true);
        }
        
        $userLogFile = $logsDir . "/user_{$chatId}.log";
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "{$timestamp} | Platform: {$platform} | URL: {$url}\n";
        
        @file_put_contents($userLogFile, $logEntry, FILE_APPEND);
    }
}
