<?php
/**
 * Maintenance Mode
 * Bot sedang dalam maintenance
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get webhook update
$content = file_get_contents('php://input');
$update = json_decode($content, true);

// Log
$logFile = __DIR__ . '/../logs/webhook.log';
@file_put_contents($logFile, date('Y-m-d H:i:s') . ' | MAINTENANCE MODE | ' . $content . "\n", FILE_APPEND);

if (!$update) {
    http_response_code(200);
    exit;
}

// Load config for bot token
require_once __DIR__ . '/../config/config.php';
$config = require __DIR__ . '/../config/config.php';

// Simple bot class for maintenance response
class MaintenanceBot {
    private $token;
    private $apiUrl;
    
    public function __construct($token) {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
    }
    
    public function request($method, $params = []) {
        $url = $this->apiUrl . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
    
    public function sendMessage($chatId, $text) {
        return $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }
}

$bot = new MaintenanceBot($config['bot_token']);

// Handle message
if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'] ?? null;
    
    if ($chatId) {
        $message = "🔧 *BOT DALAM MAINTENANCE*\n\n";
        $message .= "Maaf, bot sedang dalam perbaikan.\n\n";
        $message .= "⏰ Estimasi: 30-60 menit\n\n";
        $message .= "Kami sedang:\n";
        $message .= "• Upgrade sistem\n";
        $message .= "• Migrasi API\n";
        $message .= "• Perbaikan bug\n\n";
        $message .= "Terima kasih atas kesabarannya! 🙏";
        
        $bot->sendMessage($chatId, $message);
    }
}

// Handle callback query
if (isset($update['callback_query'])) {
    $chatId = $update['callback_query']['message']['chat']['id'] ?? null;
    
    if ($chatId) {
        $message = "🔧 *BOT DALAM MAINTENANCE*\n\n";
        $message .= "Maaf, bot sedang dalam perbaikan.\n";
        $message .= "Silakan coba lagi nanti.";
        
        $bot->sendMessage($chatId, $message);
    }
}

http_response_code(200);
