<?php
/**
 * Main Bot Logic - Message Router
 * 
 * This file is called by webhook.php to process incoming updates
 * Note: Autoloader is already loaded by webhook.php
 */

// Log that index.php is being executed
file_put_contents(__DIR__ . '/logs/webhook.log', date('[Y-m-d H:i:s] ') . "index.php started\n", FILE_APPEND);

// Use fully qualified class names (no need for 'use' statements since autoloader is ready)
try {
    file_put_contents(__DIR__ . '/logs/webhook.log', date('[Y-m-d H:i:s] ') . "Initializing bot...\n", FILE_APPEND);
    
    // Initialize bot
    $bot = new \JosskiTools\Utils\TelegramBot($config['bot_token']);
    
    // Initialize session manager
    $sessionManager = new \JosskiTools\Utils\SessionManager(
        $config['directories']['sessions'],
        $config['session']['timeout']
    );
    
    // Initialize stats manager
    $statsManager = new \JosskiTools\Utils\StatsManager();
    
    // Initialize handlers
    $commandHandler = new \JosskiTools\Handlers\CommandHandler($bot, $sessionManager, $config);
    $callbackHandler = new \JosskiTools\Handlers\CallbackHandler($bot, $sessionManager, $config);
    $messageHandler = new \JosskiTools\Handlers\MessageHandler($bot, $sessionManager, $config);
    
    file_put_contents(__DIR__ . '/logs/webhook.log', date('[Y-m-d H:i:s] ') . "Handlers initialized\n", FILE_APPEND);
    
    // Route update to appropriate handler
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'User';
        $chatType = $message['chat']['type'] ?? 'private';
        
        file_put_contents(__DIR__ . '/logs/webhook.log', date('[Y-m-d H:i:s] ') . "Message from user $userId in chat $chatId\n", FILE_APPEND);
        
        // Log incoming message
        \JosskiTools\Utils\Logger::info("Incoming message", [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'chat_type' => $chatType,
            'text' => isset($message['text']) ? substr($message['text'], 0, 100) : '(media)',
        ]);
        
        // Handle commands
        if (isset($message['text']) && strpos($message['text'], '/') === 0) {
            $command = explode(' ', $message['text'])[0];
            $command = str_replace('@' . ($config['bot_username'] ?? ''), '', $command);
            
            file_put_contents(__DIR__ . '/logs/webhook.log', date('[Y-m-d H:i:s] ') . "Command: $command\n", FILE_APPEND);
            
            switch ($command) {
                case '/start':
                    file_put_contents(__DIR__ . '/logs/webhook.log', date('[Y-m-d H:i:s] ') . "Calling handleStart\n", FILE_APPEND);
                    $commandHandler->handleStart($chatId, $userId, $username, $chatType);
                    break;
                    
                case '/help':
                    $commandHandler->handleHelp($chatId, $userId, $chatType);
                    break;
                    
                case '/stats':
                    $commandHandler->handleStats($chatId, $userId);
                    break;
                    
                case '/history':
                    $commandHandler->handleHistory($chatId, $userId);
                    break;
                    
                case '/cancel':
                    $commandHandler->handleCancel($chatId, $userId);
                    break;
                    
                case '/ekstrakhar':
                    $commandHandler->handleEkstrakHar($chatId, $userId, $message);
                    break;
                    
                case '/admin':
                    $commandHandler->handleAdmin($chatId, $userId);
                    break;
                    
                case '/broadcast':
                    $commandHandler->handleBroadcast($chatId, $userId, $message);
                    break;
                    
                case '/maintenance':
                    $commandHandler->handleMaintenance($chatId, $userId, $message);
                    break;
                    
                case '/donate':
                    $commandHandler->handleDonate($chatId, $userId);
                    break;
                    
                default:
                    // Delegate unsupported commands to message handler for extended features
                    $messageHandler->handleTextMessage($chatId, $userId, $message);
                    break;
            }
        }
        // Handle document (HAR file)
        elseif (isset($message['document'])) {
            $messageHandler->handleDocument($message, $chatId, $userId);
        }
        // Handle photo
        elseif (isset($message['photo'])) {
            $messageHandler->handlePhoto($chatId, $userId, $message);
        }
        // Handle regular text messages (URLs for download)
        elseif (isset($message['text'])) {
            $messageHandler->handleTextMessage($chatId, $userId, $message);
        }
        // Handle other media types
        else {
            $bot->sendMessage(
                $chatId,
                "ℹ️ Tipe pesan ini tidak didukung. Silakan kirim URL, file HAR, atau gunakan /help.",
                'Markdown'
            );
        }
        
    } elseif (isset($update['callback_query'])) {
        // Handle callback queries (inline button clicks)
        $callbackQuery = $update['callback_query'];
        $callbackHandler->handleCallback($callbackQuery);
        
    } elseif (isset($update['inline_query'])) {
        // Handle inline queries (optional, if you implement inline mode)
        \JosskiTools\Utils\Logger::info("Inline query received", [
            'query_id' => $update['inline_query']['id'],
            'user_id' => $update['inline_query']['from']['id'],
        ]);
        // You can implement inline query handler if needed
    }
    
    // Cleanup old sessions periodically if utility exists
    if (method_exists($sessionManager, 'cleanExpiredSessions')) {
        $sessionManager->cleanExpiredSessions();
    }
    
} catch (\Exception $e) {
    // Log error
    \JosskiTools\Utils\Logger::exception($e, [
        'context' => 'index.php',
        'update' => $update ?? null
    ]);
    
    // Try to send error message to user
    if (isset($chatId)) {
        try {
            $bot->sendMessage(
                $chatId,
                "❌ *Terjadi kesalahan!*\n\nMohon coba lagi dalam beberapa saat atau hubungi admin.",
                'Markdown'
            );
        } catch (\Exception $notifyError) {
            // Ignore notification errors
            \JosskiTools\Utils\Logger::error("Failed to send error notification", [
                'error' => $notifyError->getMessage()
            ]);
        }
    }
}
