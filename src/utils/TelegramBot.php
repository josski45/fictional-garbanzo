<?php

namespace JosskiTools\Utils;

/**
 * Telegram Bot API Client
 */
class TelegramBot {
    public $token;
    private $apiUrl;
    private $lastEditTime = [];
    
    public function __construct($token) {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
    }
    
    /**
     * Make API request
     */
    public function request($method, $params = []) {
        $url = $this->apiUrl . $method;
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/telegram_api.log';

        $logEntry = date('[Y-m-d H:i:s] ') . "Request {$method} " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);

        // If any of the params is a CURLFile we must send multipart/form-data.
        // Otherwise send JSON to preserve UTF-8 (emoji) without escaping.
        $hasFile = false;
        foreach ($params as $val) {
            if ($val instanceof \CURLFile) {
                $hasFile = true;
                break;
            }
        }

        if ($hasFile) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            $json = json_encode($params, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return [
                'ok' => false,
                'error_code' => $httpCode,
                'description' => 'cURL Error: ' . $error
            ];
        }
        
        $result = json_decode($response, true);
        if ($result === null) {
            @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Response {$method} INVALID_JSON: {$response}\n", FILE_APPEND);
            return [
                'ok' => false,
                'description' => 'Invalid JSON response: ' . $response
            ];
        }

        @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Response {$method} " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        return $result;
    }
    
    /**
     * Send message
     */
    public function sendMessage($chatId, $text, $parseMode = null, $replyMarkup = null, $replyToMessageId = null) {
        $params = [
            'chat_id' => $chatId,
            'text' => $text
        ];
        
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        
        if ($replyToMessageId) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }
        
        return $this->request('sendMessage', $params);
    }
    
    /**
     * Send document
     */
    public function sendDocument($chatId, $filePath, $caption = null) {
        $params = [
            'chat_id' => $chatId,
            'document' => new \CURLFile($filePath)
        ];
        
        if ($caption) {
            $params['caption'] = $caption;
        }
        
        return $this->request('sendDocument', $params);
    }
    
    /**
     * Send video (URL or file)
     */
    public function sendVideo($chatId, $video, $caption = null, $parseMode = null, $keyboard = null) {
        error_log("=== SEND VIDEO START ===");
        error_log("ChatId: {$chatId}");
        error_log("Video URL: {$video}");
        error_log("Caption: " . ($caption ?? 'NULL'));
        
        $params = [
            'chat_id' => $chatId,
            'video' => $video
        ];
        
        if ($caption) {
            $params['caption'] = $caption;
        }
        
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        
        $result = $this->request('sendVideo', $params);
        
        error_log("sendVideo result: " . json_encode($result));
        
        if (!($result['ok'] ?? false)) {
            error_log("ERROR sendVideo: " . ($result['description'] ?? 'Unknown error'));
        }
        
        return $result;
    }
    
    /**
     * Send audio (URL or file)
     */
    public function sendAudio($chatId, $audio, $caption = null, $parseMode = null, $keyboard = null) {
        error_log("=== SEND AUDIO START ===");
        error_log("ChatId: {$chatId}");
        error_log("Audio URL: {$audio}");
        error_log("Caption: " . ($caption ?? 'NULL'));
        
        $params = [
            'chat_id' => $chatId,
            'audio' => $audio
        ];

        // If provided path is local file, convert to CURLFile for upload
        if (is_string($audio) && file_exists($audio)) {
            $realPath = realpath($audio) ?: $audio;
            $params['audio'] = new \CURLFile($realPath);
            error_log("Sending audio as uploaded file: {$realPath}");
        }
        
        if ($caption) {
            $params['caption'] = $caption;
        }
        
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        
        $result = $this->request('sendAudio', $params);
        
        error_log("sendAudio result: " . json_encode($result));
        
        if (!($result['ok'] ?? false)) {
            error_log("ERROR sendAudio: " . ($result['description'] ?? 'Unknown error'));
        }
        
        return $result;
    }
    
    /**
     * Send photo (URL or file)
     */
    public function sendPhoto($chatId, $photo, $caption = null, $parseMode = null, $keyboard = null) {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo
        ];

        if (is_string($photo) && file_exists($photo)) {
            $realPath = realpath($photo) ?: $photo;
            $params['photo'] = new \CURLFile($realPath);
        }
        
        if ($caption) {
            $params['caption'] = $caption;
        }
        
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        
        return $this->request('sendPhoto', $params);
    }
    
    /**
     * Edit message text
     */
    public function editMessage($chatId, $messageId, $text, $parseMode = null) {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text
        ];
        
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        
        return $this->request('editMessageText', $params);
    }
    
    /**
     * Delete message
     */
    public function deleteMessage($chatId, $messageId) {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }
    
    /**
     * Send loading message with animated progress bar
     * Returns message ID for future updates
     */
    public function sendLoadingMessage($chatId, $text = "Processing", $replyToMessageId = null) {
        $message = "⏳ {$text}...\n\n";
        $message .= "▱▱▱▱▱▱▱▱▱▱ 0%";
        
        $params = [
            'chat_id' => $chatId,
            'text' => $message
        ];
        
        if ($replyToMessageId) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }
        
        $result = $this->request('sendMessage', $params);
        
        // Store last update time to prevent flooding
        $messageId = $result['result']['message_id'] ?? null;
        if ($messageId) {
            $this->lastEditTime[$messageId] = microtime(true);
        }
        
        return $messageId;
    }
    
    /**
     * Update loading progress bar
     * Anti-flood: Minimum 1 second between edits
     */
    public function updateLoadingMessage($chatId, $messageId, $text, $progress = 0) {
        if (!$messageId) return false;
        
        // Anti-flood check: minimum 1 second between edits
        $now = microtime(true);
        $lastEdit = $this->lastEditTime[$messageId] ?? 0;
        $timeSinceLastEdit = $now - $lastEdit;
        
        if ($timeSinceLastEdit < 1.0) {
            // Too soon, wait remaining time
            $waitTime = 1.0 - $timeSinceLastEdit;
            usleep((int)($waitTime * 1000000)); // Convert to microseconds
        }
        
        $progress = max(0, min(100, $progress)); // Clamp between 0-100
        $barLength = 10;
        $filledLength = round($barLength * ($progress / 100));
        
        $bar = str_repeat('▰', $filledLength) . str_repeat('▱', $barLength - $filledLength);
        
        $loadingText = "⏳ {$text}...\n\n";
        $loadingText .= "{$bar} {$progress}%";
        
        $result = $this->editMessage($chatId, $messageId, $loadingText);
        
        // Update last edit time
        if ($result['ok'] ?? false) {
            $this->lastEditTime[$messageId] = microtime(true);
            return true;
        }
        
        return false;
    }
    
    /**
     * Download file
     */
    public function downloadFile($fileId, $destination) {
        // Get file path
        $fileInfo = $this->request('getFile', ['file_id' => $fileId]);
        
        if (!$fileInfo['ok']) {
            return false;
        }
        
        $filePath = $fileInfo['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
        
        // Download file
        $fileContent = file_get_contents($fileUrl);
        if ($fileContent === false) {
            return false;
        }
        
        return file_put_contents($destination, $fileContent) !== false;
    }
    
    /**
     * Get bot info
     */
    public function getMe() {
        return $this->request('getMe');
    }
    
    /**
     * Set webhook
     */
    public function setWebhook($url) {
        return $this->request('setWebhook', ['url' => $url]);
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook() {
        return $this->request('deleteWebhook');
    }
    
    /**
     * Get webhook info
     */
    public function getWebhookInfo() {
        return $this->request('getWebhookInfo');
    }
    
    /**
     * Edit message text
     */
    public function editMessageText($chatId, $messageId, $text, $parseMode = null, $replyMarkup = null) {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text
        ];
        
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->request('editMessageText', $params);
    }

    /**
     * Edit caption for media messages
     */
    public function editMessageCaption($chatId, $messageId, $caption, $parseMode = null, $replyMarkup = null) {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('editMessageCaption', $params);
    }
    
    /**
     * Answer callback query
     */
    public function answerCallbackQuery($callbackQueryId, $text = null, $showAlert = false) {
        $params = [
            'callback_query_id' => $callbackQueryId
        ];
        
        if ($text) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }
        
        return $this->request('answerCallbackQuery', $params);
    }
}
