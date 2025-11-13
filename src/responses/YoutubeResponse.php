<?php

namespace JosskiTools\Responses;

/**
 * YouTube Response Handler
 */
class YoutubeResponse {
    
    private $bot;
    
    public function __construct($bot) {
        $this->bot = $bot;
    }
    
    /**
     * Send YouTube response
     */
    public function send($chatId, $data, $type = 'ytmp3', $loadingMsgId = null) {
        // Debug logging
        error_log("YoutubeResponse::send - Type: {$type}, ChatId: {$chatId}");
        error_log("Data received: " . json_encode($data));
        
        if ($loadingMsgId) {
            $this->bot->deleteMessage($chatId, $loadingMsgId);
        }
        
        $title = $data['title'] ?? 'Unknown Title';
        $channel = $data['channel'] ?? 'Unknown Channel';
        $duration = $data['duration'] ?? '';
        
        // Try multiple possible keys for download URL (dlink is from Ferdev API)
        $downloadUrl = $data['dlink'] ?? $data['url'] ?? $data['download'] ?? $data['link'] ?? $data['download_url'] ?? null;
        
        error_log("Download URL found: " . ($downloadUrl ?? 'NULL'));
        
        $isAudio = ($type === 'ytmp3');
        
        // Escape HTML special characters
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $channel = htmlspecialchars($channel, ENT_QUOTES, 'UTF-8');
        
        // Format caption with HTML
        $caption = $isAudio ? "🎵 <b>YouTube Audio</b>\n\n" : "📹 <b>YouTube Video</b>\n\n";
        $caption .= "📌 <b>{$title}</b>\n";
        
        if (!empty($channel) && $channel !== 'Unknown Channel') {
            $caption .= "👤 {$channel}\n";
        }
        
        // Format duration from seconds to MM:SS or HH:MM:SS
        if (!empty($duration) && is_numeric($duration)) {
            $seconds = intval($duration);
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            
            if ($hours > 0) {
                $formattedDuration = sprintf("%d:%02d:%02d", $hours, $minutes, $secs);
            } else {
                $formattedDuration = sprintf("%d:%02d", $minutes, $secs);
            }
            
            $caption .= "⏱️ {$formattedDuration}\n";
        }
        
        // Add keyboard for back to menu
        require_once __DIR__ . '/../helpers/KeyboardHelper.php';
        $keyboard = \JosskiTools\Helpers\KeyboardHelper::getMainKeyboard();
        
        // Send media file with caption
        if ($downloadUrl) {
            try {
                if ($isAudio) {
                    error_log("Attempting to send audio file...");
                    error_log("Audio URL: {$downloadUrl}");
                    // Send audio with caption and keyboard
                    $result = $this->bot->sendAudio($chatId, $downloadUrl, $caption, 'HTML', $keyboard);
                    if ($result['ok'] ?? false) {
                        error_log("Audio sent successfully");
                    } else {
                        error_log("Audio send failed: " . json_encode($result));
                        // Fallback: send download link if audio fails
                        $messageText = $caption . "\n\n";
                        $messageText .= "🎵 <a href=\"{$downloadUrl}\">Klik untuk Download</a>";
                        $this->bot->sendMessage($chatId, $messageText, 'HTML', $keyboard);
                    }
                } else {
                    error_log("Attempting to send video file...");
                    error_log("Video URL: {$downloadUrl}");
                    // Send video with caption and keyboard
                    $result = $this->bot->sendVideo($chatId, $downloadUrl, $caption, 'HTML', $keyboard);
                    if ($result['ok'] ?? false) {
                        error_log("Video sent successfully");
                    } else {
                        error_log("Video send failed: " . json_encode($result));
                        // Fallback: send download link if video fails
                        $messageText = $caption . "\n\n";
                        $messageText .= "📹 <a href=\"{$downloadUrl}\">Klik untuk Download</a>";
                        $this->bot->sendMessage($chatId, $messageText, 'HTML', $keyboard);
                    }
                }
            } catch (\Exception $e) {
                // Fallback: send download link if exception occurs
                error_log("EXCEPTION sending media file: " . $e->getMessage());
                error_log("Exception trace: " . $e->getTraceAsString());
                
                $messageText = $caption . "\n\n";
                $messageText .= ($isAudio ? "🎵" : "📹") . " <a href=\"{$downloadUrl}\">Klik untuk Download</a>";
                try {
                    $this->bot->sendMessage($chatId, $messageText, 'HTML', $keyboard);
                    error_log("Fallback message sent with download link");
                } catch (\Exception $e2) {
                    error_log("Error sending fallback message: " . $e2->getMessage());
                }
            }
        } else {
            $caption .= "\n❌ Download URL not available from API";
            error_log("No download URL found in API response");
            $this->bot->sendMessage($chatId, $caption, 'HTML', $keyboard);
        }
    }
}
