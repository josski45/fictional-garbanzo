<?php

/**
 * Verify & Update Webhook (Modular Version)
 * webhook.php is now using modular architecture
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/utils/TelegramBot.php';

use JosskiTools\Utils\TelegramBot;

$config = require __DIR__ . '/config/config.php';
$bot = new TelegramBot($config['bot_token']);

// Use existing webhook URL (webhook.php is now modular)
$webhookUrl = $config['webhook_url'];

echo "═══════════════════════════════════════════════════\n";
echo "🔄 VERIFYING WEBHOOK (MODULAR VERSION)\n";
echo "═══════════════════════════════════════════════════\n\n";
echo "Webhook URL: {$webhookUrl}\n\n";

echo "Setting/verifying webhook...\n";

$result = $bot->setWebhook($webhookUrl);

if ($result['ok']) {
    echo "\n✅ Webhook updated successfully!\n\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "📊 WEBHOOK INFO\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    $info = $bot->getWebhookInfo();
    if ($info['ok']) {
        $webhookInfo = $info['result'];
        echo "URL: " . ($webhookInfo['url'] ?? 'Not set') . "\n";
        echo "Has custom certificate: " . (($webhookInfo['has_custom_certificate'] ?? false) ? 'Yes' : 'No') . "\n";
        echo "Pending updates: " . ($webhookInfo['pending_update_count'] ?? 0) . "\n";
        echo "Max connections: " . ($webhookInfo['max_connections'] ?? 40) . "\n";
        
        if (!empty($webhookInfo['last_error_message'])) {
            echo "\n⚠️  Last error: " . $webhookInfo['last_error_message'] . "\n";
            echo "Last error date: " . date('Y-m-d H:i:s', $webhookInfo['last_error_date'] ?? 0) . "\n";
        } else {
            echo "\n✅ No errors!\n";
        }
        
        if (!empty($webhookInfo['allowed_updates'])) {
            echo "\nAllowed updates: " . implode(', ', $webhookInfo['allowed_updates']) . "\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "🚀 BOT IS USING MODULAR ARCHITECTURE!\n";
    echo "═══════════════════════════════════════════════════\n\n";
    echo "✅ webhook.php = Modular version\n";
    echo "✅ webhook_old.php = Backup (old monolithic)\n\n";
    echo "Features available:\n";
    echo "✅ Auto-detect links (TikTok, Facebook, YouTube, Spotify, CapCut)\n";
    echo "✅ TikTok User Videos (/ttuser, /ttall)\n";
    echo "✅ Modular handlers (cleaner code)\n";
    echo "✅ Better error handling\n";
    echo "✅ Session management\n\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "🧪 TEST COMMANDS:\n";
    echo "═══════════════════════════════════════════════════\n\n";
    echo "/start                     - Welcome message\n";
    echo "/ttuser @siigma            - TikTok user videos\n";
    echo "/ttall @siigma             - Download all videos\n\n";
    echo "Auto-detect (no command):\n";
    echo "https://vt.tiktok.com/abc/ - Auto download video\n";
    echo "https://tiktok.com/@siigma - Show profile options\n";
    echo "https://youtu.be/abc123    - Ask format (MP3/MP4)\n\n";
    
} else {
    echo "\n❌ Failed to set webhook!\n";
    echo "Error: " . ($result['description'] ?? 'Unknown error') . "\n\n";
    
    if (isset($result['error_code'])) {
        echo "Error code: " . $result['error_code'] . "\n\n";
        
        if ($result['error_code'] == 400) {
            echo "💡 Possible causes:\n";
            echo "   - Invalid webhook URL format\n";
            echo "   - URL not accessible from internet\n";
            echo "   - Missing HTTPS (Telegram requires HTTPS)\n\n";
        }
    }
}

echo "═══════════════════════════════════════════════════\n";
echo "Current webhook URL: {$webhookUrl}\n";
echo "Backup file: webhook_old.php\n";
echo "═══════════════════════════════════════════════════\n\n";
