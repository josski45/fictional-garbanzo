<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use JosskiTools\Utils\TelegramBot;

// Helper function untuk cek bot token valid
function isBotTokenValid($token) {
    return !empty($token) && $token !== 'YOUR_BOT_TOKEN_HERE';
}

$action = $_GET['action'] ?? 'menu';
?>
<!DOCTYPE html>
<html>
<head>
    <title>JOSS HELPER - Test Suite</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); padding: 40px; }
        h1 { color: #2c3e50; margin-bottom: 10px; font-size: 32px; }
        h2 { color: #34495e; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 3px solid #3498db; }
        h3 { color: #555; margin: 20px 0 10px 0; font-size: 18px; }
        h4 { color: #666; margin: 15px 0 8px 0; font-size: 16px; }
        .subtitle { color: #7f8c8d; margin-bottom: 30px; font-size: 16px; }
        .menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin: 30px 0; }
        .menu-item { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; text-decoration: none; display: block; transition: all 0.3s; position: relative; overflow: hidden; }
        .menu-item:before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: rgba(255,255,255,0.1); transition: left 0.3s; }
        .menu-item:hover:before { left: 100%; }
        .menu-item:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .menu-item h3 { color: white; margin: 0 0 10px 0; font-size: 20px; }
        .menu-item p { opacity: 0.9; font-size: 14px; line-height: 1.5; }
        .status-ok { color: #27ae60; font-weight: bold; }
        .status-error { color: #e74c3c; font-weight: bold; }
        .status-warn { color: #f39c12; font-weight: bold; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 20px; border-radius: 8px; overflow-x: auto; border-left: 4px solid #3498db; font-size: 13px; line-height: 1.6; }
        .back-btn { display: inline-block; background: #95a5a6; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; margin-top: 30px; transition: background 0.3s; font-weight: 500; }
        .back-btn:hover { background: #7f8c8d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #3498db; color: white; font-weight: 500; }
        tr:hover { background: #f8f9fa; }
        .info-box { padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid; }
        .info-success { background: #e8f5e9; border-left-color: #27ae60; }
        .info-error { background: #ffebee; border-left-color: #e74c3c; }
        .info-warn { background: #fff3e0; border-left-color: #f39c12; }
        .info-box h3 { margin-top: 0; }
        code { background: #ecf0f1; padding: 3px 8px; border-radius: 4px; font-family: 'Courier New', monospace; color: #e74c3c; }
    </style>
</head>
<body>
<div class="container">
    <h1>🦊 JOSS HELPER - Test Suite</h1>
    <p class="subtitle">Diagnostic & Testing Tools for Bot Development</p>

<?php

if ($action === 'menu') {
    ?>
    <div class="menu">
        <a href="?action=debug" class="menu-item" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <h3>🔍 Debug Config</h3>
            <p>Check .env file loading and configuration values</p>
        </a>
        <a href="?action=webhook" class="menu-item">
            <h3>🔗 Webhook Status</h3>
            <p>Check webhook configuration, URL, and last errors</p>
        </a>
        <a href="?action=setwebhook" class="menu-item">
            <h3>🔧 Set Webhook</h3>
            <p>Configure webhook URL for this bot</p>
        </a>
        <a href="?action=bot" class="menu-item">
            <h3>🤖 Test Bot Message</h3>
            <p>Send test message directly to verify bot API</p>
        </a>
        <a href="?action=load" class="menu-item">
            <h3>⚙️ Load Test</h3>
            <p>Test class initialization and file permissions</p>
        </a>
        <a href="?action=logs" class="menu-item">
            <h3>📋 View Logs</h3>
            <p>Display recent logs for debugging</p>
        </a>
        <a href="?action=phpinfo" class="menu-item">
            <h3>ℹ️ PHP Info</h3>
            <p>Show complete PHP configuration</p>
        </a>
    </div>
    <?php

} elseif ($action === 'debug') {
    echo "<h2>🔍 Debug Configuration</h2>";
    
    // Check .env file
    $envPaths = [
        __DIR__ . '/../.env',
        dirname(__DIR__) . '/.env',
        dirname(dirname(__FILE__)) . '/.env',
    ];
    
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $envPaths[] = $_SERVER['DOCUMENT_ROOT'] . '/../.env';
        $envPaths[] = dirname($_SERVER['DOCUMENT_ROOT']) . '/.env';
    }
    
    echo "<h3>1. .env File Detection:</h3>";
    echo "<table><tr><th>Path</th><th>Exists</th><th>Readable</th></tr>";
    
    $envFound = false;
    $envPath = '';
    foreach ($envPaths as $path) {
        $exists = file_exists($path);
        $readable = $exists && is_readable($path);
        
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($path) . "</code></td>";
        echo "<td>" . ($exists ? "<span class='status-ok'>✅ Yes</span>" : "<span class='status-error'>❌ No</span>") . "</td>";
        echo "<td>" . ($readable ? "<span class='status-ok'>✅ Yes</span>" : "<span class='status-error'>❌ No</span>") . "</td>";
        echo "</tr>";
        
        if ($readable && !$envFound) {
            $envFound = true;
            $envPath = $path;
        }
    }
    echo "</table>";
    
    if ($envFound) {
        echo "<div class='info-box info-success'>";
        echo "<h3 class='status-ok'>✅ .env File Found!</h3>";
        echo "<p><strong>Path:</strong> <code>" . htmlspecialchars($envPath) . "</code></p>";
        
        // Show .env contents (masked sensitive data)
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);
        echo "<h4>.env Contents (sensitive data masked):</h4>";
        echo "<pre style='background: #2c3e50; color: #2ecc71;'>";
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                echo htmlspecialchars($line) . "\n";
            } elseif (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, ' "\'');
                
                // Mask sensitive values
                if (!empty($value) && $value !== '') {
                    $valueLen = strlen($value);
                    if ($valueLen > 10) {
                        $masked = substr($value, 0, 5) . str_repeat('*', $valueLen - 10) . substr($value, -5);
                    } else {
                        $masked = substr($value, 0, 2) . str_repeat('*', max(0, $valueLen - 4)) . substr($value, -2);
                    }
                    echo htmlspecialchars($key) . "=" . htmlspecialchars($masked) . "\n";
                } else {
                    echo htmlspecialchars($key) . "=<span style='color: #e74c3c;'>(empty)</span>\n";
                }
            }
        }
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ .env File Not Found!</h3>";
        echo "<p>Create <code>.env</code> file in your project root directory.</p>";
        echo "<p><strong>Expected locations checked:</strong></p>";
        echo "<ul style='margin-left: 20px; line-height: 1.8;'>";
        foreach ($envPaths as $path) {
            echo "<li><code>" . htmlspecialchars($path) . "</code></li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    // Show environment variables BEFORE loading config
    echo "<h3>2. Environment Variables (via getenv):</h3>";
    echo "<table><tr><th>Variable</th><th>Value</th><th>Status</th></tr>";
    $envVars = ['BOT_TOKEN', 'WEBHOOK_URL', 'SECRET_KEY', 'FERDEV_API_KEY', 'ADMIN_IDS', 'DEFAULT_ENCRYPTION_KEY'];
    foreach ($envVars as $var) {
        $value = getenv($var);
        $isSet = $value !== false && !empty($value);
        
        if ($isSet) {
            $valueLen = strlen($value);
            if ($valueLen > 10) {
                $masked = substr($value, 0, 5) . str_repeat('*', $valueLen - 10) . substr($value, -5);
            } else {
                $masked = substr($value, 0, 2) . str_repeat('*', max(0, $valueLen - 4)) . substr($value, -2);
            }
        } else {
            $masked = '(not set)';
        }
        
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($var) . "</code></td>";
        echo "<td>" . htmlspecialchars($masked) . "</td>";
        echo "<td>" . ($isSet ? "<span class='status-ok'>✅ Set</span>" : "<span class='status-error'>❌ Not Set</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Load and check config
    echo "<h3>3. Configuration Values (from config.php):</h3>";
    $configPath = __DIR__ . '/../config/config.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
        
        echo "<table><tr><th>Key</th><th>Value</th><th>Status</th></tr>";
        
        $configKeys = [
            'bot_token' => 'YOUR_BOT_TOKEN_HERE',
            'webhook_url' => 'https://yourdomain.com/webhook.php',
            'secret_key' => 'JSK',
            'default_encryption_key' => 'Match&Ocean',
            'FERDEV_API_KEY' => ''
        ];
        
        foreach ($configKeys as $key => $defaultValue) {
            if (isset($config[$key])) {
                $value = $config[$key];
                $isDefault = ($value === $defaultValue || empty($value));
                
                if (!empty($value)) {
                    $valueLen = strlen($value);
                    if ($valueLen > 10) {
                        $masked = substr($value, 0, 5) . str_repeat('*', $valueLen - 10) . substr($value, -5);
                    } else {
                        $masked = substr($value, 0, 2) . str_repeat('*', max(0, $valueLen - 4)) . substr($value, -2);
                    }
                } else {
                    $masked = '(empty)';
                }
                
                echo "<tr>";
                echo "<td><code>" . htmlspecialchars($key) . "</code></td>";
                echo "<td>" . htmlspecialchars($masked) . "</td>";
                echo "<td>" . ($isDefault ? "<span class='status-warn'>⚠️ Default/Empty</span>" : "<span class='status-ok'>✅ Configured</span>") . "</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        // Show admin IDs
        echo "<h3>4. Admin Configuration:</h3>";
        if (!empty($config['admin_ids'])) {
            echo "<div class='info-box info-success'>";
            echo "<h3 class='status-ok'>✅ Admin IDs Configured</h3>";
            echo "<p><strong>Admin IDs:</strong> " . implode(', ', $config['admin_ids']) . "</p>";
            echo "</div>";
        } else {
            echo "<div class='info-box info-warn'>";
            echo "<h3 class='status-warn'>⚠️ No Admin IDs</h3>";
            echo "<p>Set ADMIN_IDS in .env file (comma-separated user IDs)</p>";
            echo "</div>";
        }
        
        // Summary
        echo "<h3>5. Configuration Summary:</h3>";
        $allConfigured = true;
        $issues = [];
        
        if (isset($config['bot_token']) && !isBotTokenValid($config['bot_token'])) {
            $allConfigured = false;
            $issues[] = "BOT_TOKEN not configured";
        }
        if (isset($config['webhook_url']) && $config['webhook_url'] === 'https://yourdomain.com/webhook.php') {
            $allConfigured = false;
            $issues[] = "WEBHOOK_URL still using default value";
        }
        if (!isset($config['admin_ids']) || empty($config['admin_ids'])) {
            $issues[] = "ADMIN_IDS not set (optional but recommended)";
        }
        if (!isset($config['FERDEV_API_KEY']) || empty($config['FERDEV_API_KEY'])) {
            $issues[] = "FERDEV_API_KEY not set (required for some features)";
        }
        
        if ($allConfigured) {
            echo "<div class='info-box info-success'>";
            echo "<h3 class='status-ok'>✅ All Critical Settings Configured!</h3>";
            echo "<p>Your bot is ready to use.</p>";
            if (!empty($issues)) {
                echo "<p><strong>Optional settings not configured:</strong></p>";
                echo "<ul style='margin-left: 20px;'>";
                foreach ($issues as $issue) {
                    echo "<li>" . htmlspecialchars($issue) . "</li>";
                }
                echo "</ul>";
            }
            echo "</div>";
        } else {
            echo "<div class='info-box info-error'>";
            echo "<h3 class='status-error'>❌ Configuration Issues Found!</h3>";
            echo "<p><strong>Please fix these issues:</strong></p>";
            echo "<ul style='margin-left: 20px;'>";
            foreach ($issues as $issue) {
                echo "<li>" . htmlspecialchars($issue) . "</li>";
            }
            echo "</ul>";
            echo "<p><strong>Edit your <code>.env</code> file and set the required values.</strong></p>";
            echo "</div>";
        }
        
    } else {
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Config file not found!</h3>";
        echo "<p><strong>Path:</strong> <code>" . htmlspecialchars($configPath) . "</code></p>";
        echo "</div>";
    }

} elseif ($action === 'webhook') {
    // Load config with error handling
    $configPath = __DIR__ . '/../config/config.php';
    if (!file_exists($configPath)) {
        echo "<h2>🔗 Webhook Status Check</h2>";
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Config file not found!</h3>";
        echo "<p>Path: <code>" . htmlspecialchars($configPath) . "</code></p>";
        echo "</div>";
        echo "<a href='?action=menu' class='back-btn'>← Back to Menu</a></div></body></html>";
        exit;
    }
    
    $config = require $configPath;
    require_once __DIR__ . '/../src/utils/TelegramBot.php';
    
    // Check if $config is defined
    if (!isset($config) || !is_array($config)) {
        echo "<h2>🔗 Webhook Status Check</h2>";
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Config variable not defined!</h3>";
        echo "<p>The config.php file exists but didn't return an array.</p>";
        echo "<p>Please check your <code>config/config.php</code> file.</p>";
        echo "</div>";
        echo "<a href='?action=debug' class='back-btn' style='background: #f39c12;'>🔍 Check Debug</a> ";
        echo "<a href='?action=menu' class='back-btn'>← Back to Menu</a></div></body></html>";
        exit;
    }
    
    if (!isset($config['bot_token']) || !isBotTokenValid($config['bot_token'])) {
        echo "<h2>🔗 Webhook Status Check</h2>";
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Bot token not configured!</h3>";
        echo "<p><strong>Current value:</strong> <code>" . htmlspecialchars($config['bot_token']) . "</code></p>";
        echo "<p>Edit <code>.env</code> file and set <code>BOT_TOKEN</code> with your actual bot token.</p>";
        echo "<p>Get your bot token from <a href='https://t.me/BotFather' target='_blank'>@BotFather</a></p>";
        echo "</div>";
        echo "<a href='?action=debug' class='back-btn' style='background: #f39c12;'>🔍 Check Debug</a> ";
        echo "<a href='?action=menu' class='back-btn'>← Back to Menu</a></div></body></html>";
        exit;
    }
    
    $bot = new TelegramBot($config['bot_token']);
    $info = $bot->getWebhookInfo();
    
    echo "<h2>🔗 Webhook Status Check</h2>";
    
    if (empty($info['result']['url'])) {
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>⚠️ WEBHOOK NOT SET!</h3>";
        echo "<p>Use the <a href='?action=setwebhook'>Set Webhook</a> menu to configure it.</p>";
        echo "</div>";
    } else {
        echo "<div class='info-box info-success'>";
        echo "<h3 class='status-ok'>✅ Webhook Active</h3>";
        echo "<p><strong>URL:</strong> " . htmlspecialchars($info['result']['url']) . "</p>";
        echo "<p><strong>Pending Updates:</strong> " . ($info['result']['pending_update_count'] ?? 0) . "</p>";
        echo "</div>";
        
        if (isset($info['result']['last_error_date'])) {
            echo "<div class='info-box info-warn'>";
            echo "<h3 class='status-warn'>⚠️ Last Error</h3>";
            echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s', $info['result']['last_error_date']) . "</p>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($info['result']['last_error_message']) . "</p>";
            echo "</div>";
        }
    }
    
    echo "<h3>Full Webhook Info:</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

} elseif ($action === 'setwebhook') {
    // Load config with error handling
    $configPath = __DIR__ . '/../config/config.php';
    if (!file_exists($configPath)) {
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Config file not found!</h3>";
        echo "<p>Path: <code>" . htmlspecialchars($configPath) . "</code></p>";
        echo "</div>";
        echo "<a href='?action=menu' class='back-btn'>← Back to Menu</a></div></body></html>";
        exit;
    }
    
    $config = require $configPath;
    require_once __DIR__ . '/../src/utils/TelegramBot.php';
    
    if (!isset($config['bot_token']) || !isBotTokenValid($config['bot_token'])) {
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Bot token not configured!</h3>";
        echo "<p><strong>Current value:</strong> <code>" . htmlspecialchars($config['bot_token']) . "</code></p>";
        echo "<p>Edit <code>.env</code> file and set <code>BOT_TOKEN</code>.</p>";
        echo "</div>";
        echo "<a href='?action=debug' class='back-btn' style='background: #f39c12;'>🔍 Check Debug</a> ";
        echo "<a href='?action=menu' class='back-btn'>← Back to Menu</a></div></body></html>";
        exit;
    }
    
    $bot = new TelegramBot($config['bot_token']);
    
    echo "<h2>🔧 Set Webhook</h2>";
    
    // Get current URL automatically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['PHP_SELF']);
    $baseUrl = $protocol . '://' . $host . $scriptPath;
    
    // Allow custom webhook filename
    $webhookFile = $_GET['file'] ?? 'webhook.php';
    $webhookUrl = $baseUrl . '/' . $webhookFile;
    
    // Validate webhook file exists
    $webhookFilePath = __DIR__ . '/' . $webhookFile;
    $fileExists = file_exists($webhookFilePath);
    
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Set webhook
        $result = $bot->setWebhook($webhookUrl);
        
        if ($result['ok']) {
            echo "<div class='info-box info-success'>";
            echo "<h3 class='status-ok'>✅ Webhook Set Successfully!</h3>";
            echo "<p><strong>URL:</strong> " . htmlspecialchars($webhookUrl) . "</p>";
            echo "<p>Bot is now active and ready to receive updates.</p>";
            echo "</div>";
            
            // Show webhook info
            $info = $bot->getWebhookInfo();
            echo "<h3>Webhook Info:</h3>";
            echo "<pre>" . htmlspecialchars(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        } else {
            echo "<div class='info-box info-error'>";
            echo "<h3 class='status-error'>❌ Failed to Set Webhook</h3>";
            echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";
            echo "</div>";
        }
    } else {
        // Show form to input custom webhook filename
        echo "<div class='info-box info-warn'>";
        echo "<h3>⚠️ Confirm Webhook Setup</h3>";
        echo "<p><strong>Base URL:</strong> <code>" . htmlspecialchars($baseUrl) . "/</code></p>";
        echo "<p><strong>Webhook File:</strong></p>";
        echo "<form method='GET' style='margin:15px 0;'>";
        echo "<input type='hidden' name='action' value='setwebhook'>";
        echo "<input type='text' name='file' value='" . htmlspecialchars($webhookFile) . "' style='padding:10px; width:300px; border:2px solid #3498db; border-radius:5px; font-size:14px;' placeholder='webhook.php'>";
        echo "<button type='submit' style='padding:10px 20px; background:#3498db; color:white; border:none; border-radius:5px; cursor:pointer; margin-left:10px; font-size:14px;'>🔄 Preview</button>";
        echo "</form>";
        
        // File validation
        if (!$fileExists) {
            echo "<div class='info-box info-error' style='margin-top: 15px;'>";
            echo "<h3 class='status-error'>⚠️ File Not Found!</h3>";
            echo "<p><strong>File:</strong> <code>" . htmlspecialchars($webhookFile) . "</code></p>";
            echo "<p><strong>Path:</strong> <code>" . htmlspecialchars($webhookFilePath) . "</code></p>";
            echo "<p>Pastikan file webhook sudah di-upload dan nama file benar.</p>";
            echo "</div>";
        } else {
            echo "<div class='info-box info-success' style='margin-top: 15px;'>";
            echo "<h3 class='status-ok'>✅ File Exists</h3>";
            echo "<p><strong>File:</strong> <code>" . htmlspecialchars($webhookFile) . "</code></p>";
            echo "</div>";
        }
        
        echo "<p><strong>Full Webhook URL:</strong></p>";
        echo "<code style='display:block; margin:10px 0; padding:15px; background:#2c3e50; color:#ecf0f1; border-radius:5px; font-size:14px;'>" . htmlspecialchars($webhookUrl) . "</code>";
        echo "<p><strong>Important:</strong></p>";
        echo "<ul style='margin-left:20px; line-height:1.8;'>";
        echo "<li>Pastikan URL menggunakan <strong>HTTPS</strong> (bukan HTTP)</li>";
        echo "<li>Port harus 80, 88, 443, atau 8443</li>";
        echo "<li>Certificate SSL harus valid</li>";
        echo "<li>File webhook harus accessible dan executable</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div style='margin-top:30px;'>";
        if ($fileExists) {
            echo "<a href='?action=setwebhook&file=" . urlencode($webhookFile) . "&confirm=yes' class='back-btn' style='background:#27ae60; margin-right:10px;'>✅ Confirm & Set Webhook</a>";
        } else {
            echo "<span class='back-btn' style='background:#95a5a6; opacity:0.5; cursor:not-allowed; margin-right:10px;'>⛔ File Not Found</span>";
        }
        echo "<a href='?action=menu' class='back-btn'>❌ Cancel</a>";
        echo "</div>";
    }

} elseif ($action === 'bot') {
    // Load config with error handling
    $configPath = __DIR__ . '/../config/config.php';
    if (!file_exists($configPath)) {
        echo "<h2>🤖 Testing Bot Message</h2>";
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Config file not found!</h3>";
        echo "<p>Path: <code>" . htmlspecialchars($configPath) . "</code></p>";
        echo "</div>";
        echo "<a href='?action=menu' class='back-btn'>← Back to Menu</a></div></body></html>";
        exit;
    }
    
    $config = require $configPath;
    require_once __DIR__ . '/../src/utils/TelegramBot.php';
    require_once __DIR__ . '/../src/utils/SessionManager.php';
    require_once __DIR__ . '/../src/utils/StatsManager.php';
    require_once __DIR__ . '/../src/utils/QuotesManager.php';
    
    if (!isset($config['bot_token']) || !isBotTokenValid($config['bot_token'])) {
        echo "<h2>🤖 Testing Bot Message</h2>";
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Bot token not configured!</h3>";
        echo "<p><strong>Current value:</strong> <code>" . htmlspecialchars($config['bot_token']) . "</code></p>";
        echo "<p>Edit <code>.env</code> file and set <code>BOT_TOKEN</code> with your actual bot token.</p>";
        echo "<p>Get your bot token from <a href='https://t.me/BotFather' target='_blank'>@BotFather</a></p>";
        echo "</div>";
        echo "<a href='?action=debug' class='back-btn' style='background: #f39c12;'>🔍 Check Debug</a> ";
        echo "<a href='?action=menu' class='back-btn'>← Back to Menu</a></div></body></html>";
        exit;
    }
    
    echo "<h2>🤖 Testing Bot Message</h2>";
    
    echo "<h3>1. Initialize Classes</h3>";
    try {
        $bot = new TelegramBot($config['bot_token']);
        echo "<span class='status-ok'>✅</span> TelegramBot<br>";
        
        $sessionManager = new SessionManager($config['directories']['sessions'], $config['session']['timeout']);
        echo "<span class='status-ok'>✅</span> SessionManager<br>";
        
        $statsManager = new StatsManager();
        echo "<span class='status-ok'>✅</span> StatsManager<br>";
        
        $quotesManager = new QuotesManager();
        echo "<span class='status-ok'>✅</span> QuotesManager<br><br>";
        
    } catch (Exception $e) {
        echo "<span class='status-error'>❌</span> Error: " . htmlspecialchars($e->getMessage());
        echo "</div></body></html>";
        exit;
    }
    
    echo "<h3>2. Send Test Message</h3>";
    
    // Form untuk input chat ID
    if (!isset($_GET['chatid']) || empty($_GET['chatid'])) {
        echo "<div class='info-box info-warn'>";
        echo "<h3>📝 Enter Your Chat ID</h3>";
        echo "<p>Get your chat ID by sending any message to <a href='https://t.me/userinfobot' target='_blank'>@userinfobot</a></p>";
        echo "<form method='GET' style='margin-top:15px;'>";
        echo "<input type='hidden' name='action' value='bot'>";
        echo "<input type='text' name='chatid' placeholder='Your Chat ID (e.g., 1234567890)' style='padding:10px; width:300px; border:2px solid #3498db; border-radius:5px; font-size:14px;' required>";
        echo "<button type='submit' style='padding:10px 20px; background:#27ae60; color:white; border:none; border-radius:5px; cursor:pointer; margin-left:10px; font-size:14px;'>📤 Send Test</button>";
        echo "</form>";
        echo "</div>";
    } else {
        $testChatId = trim($_GET['chatid']);
        
        // Validate chat ID
        if (!is_numeric($testChatId)) {
            echo "<div class='info-box info-error'>";
            echo "<h3 class='status-error'>❌ Invalid Chat ID</h3>";
            echo "<p>Chat ID must be a number. You entered: <code>" . htmlspecialchars($testChatId) . "</code></p>";
            echo "</div>";
        } else {
            echo "<p>Sending to: <code>$testChatId</code></p>";
            
            try {
                $testMessage = "🧪 *Test from test.php*\n\n✅ Bot works!\n⏰ " . date('H:i:s d/m/Y') . "\n\n_If you received this message, your bot is configured correctly!_";
                $result = $bot->sendMessage($testChatId, $testMessage, 'Markdown');
                
                if ($result['ok']) {
                    echo "<div class='info-box info-success'>";
                    echo "<h3 class='status-ok'>✅ Message Sent!</h3>";
                    echo "<p><strong>Message ID:</strong> " . $result['result']['message_id'] . "</p>";
                    echo "<p>Check your Telegram to see the test message!</p>";
                    echo "</div>";
                    
                    echo "<h3>Response:</h3>";
                    echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";
                } else {
                    echo "<div class='info-box info-error'>";
                    echo "<h3 class='status-error'>❌ Failed to Send</h3>";
                    echo "<p>The bot API returned an error. Check the response below:</p>";
                    echo "</div>";
                    echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";
                }
            } catch (Exception $e) {
                echo "<div class='info-box info-error'>";
                echo "<h3 class='status-error'>❌ Exception Occurred</h3>";
                echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                echo "</div>";
            }
        }
    }

} elseif ($action === 'load') {
    echo "<h2>⚙️ Load Test</h2>";
    
    echo "<h3>1. Required Files</h3>";
    $files = [
        '../config/config.php' => 'Config',
        '../src/utils/TelegramBot.php' => 'TelegramBot',
        '../src/utils/SessionManager.php' => 'SessionManager',
        '../src/utils/StatsManager.php' => 'StatsManager',
        '../src/utils/QuotesManager.php' => 'QuotesManager'
    ];
    
    echo "<table><tr><th>File</th><th>Status</th><th>Path</th></tr>";
    $allFilesExist = true;
    foreach ($files as $file => $name) {
        $fullPath = __DIR__ . '/' . $file;
        $exists = file_exists($fullPath);
        if (!$exists) $allFilesExist = false;
        
        echo "<tr>";
        echo "<td><strong>$name</strong></td>";
        echo "<td>" . ($exists ? "<span class='status-ok'>✅ Exists</span>" : "<span class='status-error'>❌ Missing</span>") . "</td>";
        echo "<td><code>" . htmlspecialchars($fullPath) . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>2. Required Directories</h3>";
    $dirs = ['logs', 'temp', 'sessions', 'data/cache', 'data/stats', 'downloads', 'results'];
    echo "<table><tr><th>Directory</th><th>Exists</th><th>Writable</th><th>Path</th></tr>";
    $allDirsOk = true;
    foreach ($dirs as $dir) {
        $path = __DIR__ . '/../' . $dir;
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        if (!$exists || !$writable) $allDirsOk = false;
        
        echo "<tr>";
        echo "<td><strong>$dir</strong></td>";
        echo "<td>" . ($exists ? "<span class='status-ok'>✅ Yes</span>" : "<span class='status-error'>❌ No</span>") . "</td>";
        echo "<td>" . ($writable ? "<span class='status-ok'>✅ Yes</span>" : "<span class='status-error'>❌ No</span>") . "</td>";
        echo "<td><code>" . htmlspecialchars($path) . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>3. Class Initialization Test</h3>";
    try {
        $config = require __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../src/utils/TelegramBot.php';
        require_once __DIR__ . '/../src/utils/SessionManager.php';
        require_once __DIR__ . '/../src/utils/StatsManager.php';
        require_once __DIR__ . '/../src/utils/QuotesManager.php';
        
        if (!isset($config['bot_token']) || !isBotTokenValid($config['bot_token'])) {
            echo "<div class='info-box info-warn'>";
            echo "<h3 class='status-warn'>⚠️ Bot Token Not Configured</h3>";
            echo "<p>Files and classes load successfully, but bot token needs to be set.</p>";
            echo "<p>Go to <a href='?action=debug'>Debug Config</a> to check configuration.</p>";
            echo "</div>";
        } else {
            $botClass = 'JosskiTools\\Utils\\TelegramBot';
            $bot = new $botClass($config['bot_token']);
            echo "<span class='status-ok'>✅</span> TelegramBot initialized<br>";
            
            $sessionManager = new SessionManager($config['directories']['sessions'], $config['session']['timeout']);
            echo "<span class='status-ok'>✅</span> SessionManager initialized<br>";
            
            $statsManager = new StatsManager();
            echo "<span class='status-ok'>✅</span> StatsManager initialized<br>";
            
            $quotesManager = new QuotesManager();
            echo "<span class='status-ok'>✅</span> QuotesManager initialized<br><br>";
            
            echo "<div class='info-box info-success'>";
            echo "<h3>✅ All Tests Passed!</h3>";
            echo "<p>webhook.php should work correctly.</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Initialization Failed</h3>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
        echo "</div>";
    }
    
    // Final summary
    echo "<h3>4. Summary</h3>";
    if ($allFilesExist && $allDirsOk) {
        echo "<div class='info-box info-success'>";
        echo "<h3 class='status-ok'>✅ Environment Ready</h3>";
        echo "<p>All files and directories are properly configured.</p>";
        echo "</div>";
    } else {
        echo "<div class='info-box info-error'>";
        echo "<h3 class='status-error'>❌ Environment Issues</h3>";
        echo "<p>Please fix the missing or non-writable items above.</p>";
        echo "</div>";
    }

} elseif ($action === 'logs') {
    echo "<h2>📋 Recent Logs</h2>";
    
    $logFiles = [
        'webhook.log' => 50,
        'error.log' => 30,
        'php_errors.log' => 30
    ];
    
    foreach ($logFiles as $filename => $lines) {
        $logPath = __DIR__ . '/../logs/' . $filename;
        echo "<h3>$filename</h3>";
        
        if (file_exists($logPath)) {
            $content = file_get_contents($logPath);
            if (!empty($content)) {
                $logLines = explode("\n", $content);
                $logLines = array_filter($logLines);
                $totalLines = count($logLines);
                $lastLines = array_slice($logLines, -$lines);
                
                echo "<p style='color: #7f8c8d;'>Showing last $lines lines (Total: $totalLines lines)</p>";
                echo "<pre>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
            } else {
                echo "<p style='color: #95a5a6; font-style: italic;'>File is empty</p>";
            }
        } else {
            echo "<p style='color: #e74c3c; font-style: italic;'>File not found: <code>" . htmlspecialchars($logPath) . "</code></p>";
        }
    }

} elseif ($action === 'phpinfo') {
    echo "<h2>ℹ️ PHP Information</h2>";
    echo "<div style='background: white; padding: 20px; border-radius: 8px;'>";
    phpinfo();
    echo "</div>";
}

?>

<a href="?action=menu" class="back-btn">← Back to Menu</a>

</div>
</body>
</html>