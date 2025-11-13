<?php

/**
 * Josski Tools Bot - Main Webhook Handler
 * Modular and clean architecture
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Load utilities
require_once __DIR__ . '/../src/utils/TelegramBot.php';
require_once __DIR__ . '/../src/utils/SessionManager.php';
require_once __DIR__ . '/../src/utils/HarParser.php';
require_once __DIR__ . '/../src/utils/Encryption.php';
require_once __DIR__ . '/../src/utils/StatsManager.php';
require_once __DIR__ . '/../src/utils/QuotesManager.php';

// Load API clients
require_once __DIR__ . '/../src/api/ferdev/FerdevClient.php';
require_once __DIR__ . '/../src/api/ferdev/Downloader.php';
require_once __DIR__ . '/../src/api/SSSTikProClient.php';

// Load helpers
require_once __DIR__ . '/../src/helpers/ErrorHelper.php';
require_once __DIR__ . '/../src/helpers/KeyboardHelper.php';

// Load handlers
require_once __DIR__ . '/../src/handlers/CommandHandler.php';
require_once __DIR__ . '/../src/handlers/CallbackHandler.php';
require_once __DIR__ . '/../src/handlers/MessageHandler.php';
require_once __DIR__ . '/../src/handlers/DownloadHandler.php';
require_once __DIR__ . '/../src/handlers/TikTokUserHandler.php';

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\SessionManager;
use JosskiTools\Utils\StatsManager;
use JosskiTools\Utils\QuotesManager;
use JosskiTools\Handlers\CommandHandler;
use JosskiTools\Handlers\CallbackHandler;
use JosskiTools\Handlers\MessageHandler;

// Load config
$config = require __DIR__ . '/../config/config.php';

// Setup error logging
$errorLogFile = __DIR__ . '/../logs/error.log';
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($errorLogFile) {
    $msg = date('Y-m-d H:i:s') . " | PHP Error [$errno]: $errstr in $errfile:$errline\n";
    @file_put_contents($errorLogFile, $msg, FILE_APPEND);
});

try {
    // Ensure directories exist
    foreach ($config['directories'] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // Initialize core services
    $bot = new TelegramBot($config['bot_token']);
    $sessionManager = new SessionManager($config['directories']['sessions'], $config['session']['timeout']);
    $statsManager = new StatsManager();
    $quotesManager = new QuotesManager();
    
} catch (Exception $e) {
    $logFile = __DIR__ . '/../logs/webhook.log';
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " | FATAL ERROR on init: " . $e->getMessage() . "\n", FILE_APPEND);
    @file_put_contents($errorLogFile, date('Y-m-d H:i:s') . " | FATAL ERROR on init: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    http_response_code(500);
    exit;
}

// Get webhook update
$content = file_get_contents('php://input');
$update = json_decode($content, true);

// Log incoming updates
$logFile = __DIR__ . '/../logs/webhook.log';
@file_put_contents($logFile, date('Y-m-d H:i:s') . ' | RAW: ' . $content . "\n", FILE_APPEND);

if (!$update) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | ERROR: Empty update received' . "\n", FILE_APPEND);
    http_response_code(200);
    exit;
}

// Route update
try {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Processing update...' . "\n", FILE_APPEND);
    
    if (isset($update['message'])) {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Update type: MESSAGE' . "\n", FILE_APPEND);
        error_log("Creating MessageHandler instance...");
        $messageHandler = new MessageHandler($bot, $sessionManager, $config);
        error_log("Calling messageHandler->handle()...");
        $messageHandler->handle($update['message']);
        error_log("messageHandler->handle() completed");
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Message handled successfully' . "\n", FILE_APPEND);
    } elseif (isset($update['callback_query'])) {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Update type: CALLBACK_QUERY' . "\n", FILE_APPEND);
        $callbackHandler = new CallbackHandler($bot, $sessionManager, $config);
        $callbackHandler->handle($update['callback_query']);
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Callback handled successfully' . "\n", FILE_APPEND);
    } elseif (isset($update['my_chat_member'])) {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Update type: MY_CHAT_MEMBER' . "\n", FILE_APPEND);
        // Handle when bot is added/removed from group/channel
        $messageHandler = new MessageHandler($bot, $sessionManager, $config);
        $messageHandler->handleChatMemberUpdate($update['my_chat_member']);
    } else {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | WARNING: Unknown update type' . "\n", FILE_APPEND);
    }
} catch (Exception $e) {
    // Log error
    $errorLog = __DIR__ . '/../logs/error.log';
    @file_put_contents($errorLog, date('Y-m-d H:i:s') . ' | EXCEPTION in webhook: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | FATAL ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
    
    // Try to notify user
    try {
        if (isset($update['message']['chat']['id'])) {
            $bot->sendMessage($update['message']['chat']['id'], "❌ Terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.");
        }
    } catch (Exception $notifyError) {
        // Silent fail
    }
} catch (Error $e) {
    // Catch PHP 7+ errors (like TypeError, ParseError, etc)
    $errorLog = __DIR__ . '/../logs/error.log';
    @file_put_contents($errorLog, date('Y-m-d H:i:s') . ' | FATAL PHP ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | FATAL PHP ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
}

http_response_code(200);
