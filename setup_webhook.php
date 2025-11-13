<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/utils/TelegramBot.php';

use JosskiTools\Utils\TelegramBot;

$config = require __DIR__ . '/config/config.php';
$bot = new TelegramBot($config['bot_token']);

// Check if webhook URL is set
if (empty($config['webhook_url']) || $config['webhook_url'] === 'https://yourdomain.com/webhook.php') {
    die("❌ Error: Please set WEBHOOK_URL in config.php or .env file\n");
}

echo "Setting webhook to: {$config['webhook_url']}\n";

$result = $bot->setWebhook($config['webhook_url']);

if ($result['ok']) {
    echo "✅ Webhook set successfully!\n";
    echo "\nWebhook Info:\n";
    
    $info = $bot->getWebhookInfo();
    if ($info['ok']) {
        $webhookInfo = $info['result'];
        echo "URL: " . ($webhookInfo['url'] ?? 'Not set') . "\n";
        echo "Pending updates: " . ($webhookInfo['pending_update_count'] ?? 0) . "\n";
        echo "Last error: " . ($webhookInfo['last_error_message'] ?? 'None') . "\n";
    }
} else {
    echo "❌ Failed to set webhook!\n";
    echo "Error: " . ($result['description'] ?? 'Unknown error') . "\n";
}

echo "\n";
echo "Bot is ready to receive updates at:\n";
echo $config['webhook_url'] . "\n";
