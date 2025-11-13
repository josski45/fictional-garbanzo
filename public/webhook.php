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

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to Telegram

// Get webhook update
$content = file_get_contents('php://input');
$update = json_decode($content, true);

// Quick validation
if (!$update) {
    http_response_code(200);
    exit;
}

// Load autoloader and config
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

$config = require __DIR__ . '/../config/config.php';

// Initialize utilities
use JosskiTools\Utils\Logger;
use JosskiTools\Utils\MaintenanceManager;
use JosskiTools\Utils\UserManager;
use JosskiTools\Utils\TelegramBot;

try {
    // Initialize logger
    Logger::init($config['directories']['logs'] ?? null);

    // Initialize maintenance manager
    MaintenanceManager::init($config['directories']['data'] ?? null);

    // Initialize user manager
    UserManager::init();

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
    if (MaintenanceManager::isEnabled()) {
        // Check if user can bypass maintenance (admin whitelist)
        if (!$userId || !MaintenanceManager::canBypass($userId)) {

            Logger::info("Request blocked - Maintenance mode", [
                'user_id' => $userId,
                'chat_id' => $chatId
            ]);

            // Send maintenance message
            $bot = new TelegramBot($config['bot_token']);

            if ($chatId) {
                $maintenanceMsg = MaintenanceManager::getMessage();
                $bot->sendMessage($chatId, $maintenanceMsg, 'Markdown');
            }

            http_response_code(200);
            exit;
        }

        // Admin bypassed maintenance
        Logger::info("Admin bypassed maintenance mode", [
            'user_id' => $userId
        ]);
    }

    // Process request normally - include main bot logic
    require_once __DIR__ . '/../index.php';

} catch (\Exception $e) {
    // Log exception
    Logger::exception($e, [
        'context' => 'webhook',
        'update' => $update
    ]);

    // Try to notify user
    try {
        if ($chatId) {
            $bot = new TelegramBot($config['bot_token']);
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
