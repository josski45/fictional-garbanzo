<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/utils/TelegramBot.php';

use JosskiTools\Utils\TelegramBot;

$config = require __DIR__ . '/config/config.php';
$bot = new TelegramBot($config['bot_token']);

echo "🧹 Clearing pending updates...\n\n";

// Get current updates
$webhookInfo = $bot->getWebhookInfo();
if ($webhookInfo['ok']) {
    $pending = $webhookInfo['result']['pending_update_count'] ?? 0;
    echo "Pending updates: $pending\n\n";
}

// Delete webhook temporarily
echo "Step 1: Deleting webhook...\n";
$result = $bot->deleteWebhook();
if ($result['ok']) {
    echo "✅ Webhook deleted\n";
} else {
    echo "❌ Failed: " . ($result['description'] ?? 'Unknown error') . "\n";
}

echo "\nStep 2: Getting updates to clear them...\n";
// This will fetch and clear all pending updates
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$config['bot_token']}/getUpdates?offset=-1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$updates = json_decode($response, true);
if ($updates['ok']) {
    echo "✅ Updates cleared\n";
}

echo "\nStep 3: Setting webhook again...\n";
$result = $bot->setWebhook($config['webhook_url']);
if ($result['ok']) {
    echo "✅ Webhook set successfully!\n";
} else {
    echo "❌ Failed: " . ($result['description'] ?? 'Unknown error') . "\n";
}

echo "\n✅ Done! Try sending /start to your bot now.\n";
