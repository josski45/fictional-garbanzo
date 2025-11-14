<?php
/**
 * Webhook Entry Point - Josski Tools Bot
 *
 * Features:
 * - Dynamic maintenance mode checking
 * - Admin whitelist support
 * - Comprehensive error handling
 * - Request logging
 */

// Error reporting - ENABLE untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to Telegram
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Log raw input for debugging
$content = file_get_contents('php://input');
file_put_contents(__DIR__ . '/../logs/webhook_raw.log', date('[Y-m-d H:i:s] ') . $content . "\n", FILE_APPEND);

$update = json_decode($content, true);

// Quick validation
if (!$update) {
    file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "No update received\n", FILE_APPEND);
    http_response_code(200);
    exit;
}

// Load autoloader FIRST before any class usage
try {
    // Coba gunakan Composer autoload dulu
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "Using composer autoload\n", FILE_APPEND);
    } 
    // Jika tidak ada, gunakan autoload sederhana kita
    elseif (file_exists(__DIR__ . '/../autoload.php')) {
        require_once __DIR__ . '/../autoload.php';
        file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "Using custom autoload.php\n", FILE_APPEND);
    } 
    else {
        file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "ERROR: No autoloader found\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
} catch (\Exception $e) {
    file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "ERROR loading autoload: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    exit;
}

// Load config
if (!file_exists(__DIR__ . '/../config/config.php')) {
    file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "ERROR: config/config.php not found\n", FILE_APPEND);
    http_response_code(500);
    exit;
}

$config = require __DIR__ . '/../config/config.php';

if (!isset($config['bot_token']) || empty($config['bot_token'])) {
    file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "ERROR: bot_token not configured\n", FILE_APPEND);
    http_response_code(500);
    exit;
}

// NOW we can safely use classes (autoloader is ready)
try {
    file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "Initializing Logger and MaintenanceManager...\n", FILE_APPEND);
    
    // Initialize logger - using fully qualified class name
    \JosskiTools\Utils\Logger::init($config['directories']['logs'] ?? null);
    
    file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "Logger initialized successfully\n", FILE_APPEND);

    // Initialize maintenance manager
    \JosskiTools\Utils\MaintenanceManager::init($config['directories']['data'] ?? null);
    
    file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "MaintenanceManager initialized\n", FILE_APPEND);

    // Initialize user manager
    \JosskiTools\Utils\UserManager::init();

    // Get user/chat ID
    $chatId = null;
    $userId = null;

    if (isset($update['message'])) {
        $chatId = $update['message']['chat']['id'] ?? null;
        $userId = $update['message']['from']['id'] ?? null;
    } elseif (isset($update['callback_query'])) {
        $chatId = $update['callback_query']['message']['chat']['id'] ?? null;
        $userId = $update['callback_query']['from']['id'] ?? null;
    }

    // Check maintenance mode
    if (\JosskiTools\Utils\MaintenanceManager::isEnabled()) {
        // Check if user can bypass maintenance (admin whitelist)
        if (!$userId || !\JosskiTools\Utils\MaintenanceManager::canBypass($userId)) {

            \JosskiTools\Utils\Logger::info("Request blocked - Maintenance mode", [
                'user_id' => $userId,
                'chat_id' => $chatId
            ]);

            // Send maintenance message
            $bot = new \JosskiTools\Utils\TelegramBot($config['bot_token']);

            if ($chatId) {
                $maintenanceMsg = \JosskiTools\Utils\MaintenanceManager::getMessage();
                $bot->sendMessage($chatId, $maintenanceMsg, 'Markdown');
            }

            http_response_code(200);
            exit;
        }

        // Admin bypassed maintenance
        \JosskiTools\Utils\Logger::info("Admin bypassed maintenance mode", [
            'user_id' => $userId
        ]);
    }

    // Process request normally - include main bot logic
    if (!file_exists(__DIR__ . '/../index.php')) {
        \JosskiTools\Utils\Logger::error("index.php not found!");
        file_put_contents(__DIR__ . '/../logs/webhook.log', date('[Y-m-d H:i:s] ') . "ERROR: index.php not found\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
    
    require_once __DIR__ . '/../index.php';

} catch (\Exception $e) {
    // Log exception
    \JosskiTools\Utils\Logger::exception($e, [
        'context' => 'webhook',
        'update' => $update
    ]);

    // Try to notify user
    try {
        if ($chatId) {
            $bot = new \JosskiTools\Utils\TelegramBot($config['bot_token']);
            $bot->sendMessage(
                $chatId,
                "❌ Terjadi kesalahan. Silakan coba lagi atau hubungi admin."
            );
        }
    } catch (\Exception $notifyError) {
        // Ignore notification errors
    }

    http_response_code(200);
    exit;
}

http_response_code(200);
