<?php

namespace JosskiTools\Responses;

/**
 * TikTok Response Handler
 */
class TiktokResponse {
    
    private $bot;
    
    public function __construct($bot) {
        $this->bot = $bot;
    }
    
    /**
     * Send TikTok response
     */
    public function send($chatId, $data, $loadingMsgId = null) {
        error_log("TiktokResponse::send - Data received: " . json_encode($data));
        
        if ($loadingMsgId) {
            $this->bot->deleteMessage($chatId, $loadingMsgId);
        }
        
        $title = $data['title'] ?? 'TikTok Video';
        $author = $data['author']['name'] ?? $data['author'] ?? 'Unknown';
        $username = $data['author']['username'] ?? '';
        $stats = $data['stats'] ?? [];
        
        // Try multiple possible keys for video URL
        $videoUrl = $data['videoUrl'] ?? $data['video'] ?? $data['dlink'] ?? $data['url'] ?? $data['download'] ?? null;
        $musicUrl = $data['musicUrl'] ?? $data['music'] ?? $data['audio'] ?? null;
        
        error_log("TikTok Video URL: " . ($videoUrl ?? 'NULL'));
        error_log("TikTok Music URL: " . ($musicUrl ?? 'NULL'));
        
        $caption = "━━━━━━━━━━━━━━━━━━━━\n";
        $caption .= "✅ TIKTOK DOWNLOADER\n";
        $caption .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        if (!empty($title)) {
            $caption .= "📌 " . $title . "\n\n";
        }
        
        $caption .= "👤 Author: " . $author . "\n";
        if (!empty($username)) {
            $caption .= "📱 Username: " . $username . "\n";
        }
        
        if (!empty($stats)) {
            $caption .= "\n📊 Statistics:\n";
            if (isset($stats['play'])) $caption .= "▶️ Views: " . $stats['play'] . "\n";
            if (isset($stats['like'])) $caption .= "❤️ Likes: " . $stats['like'] . "\n";
            if (isset($stats['comment'])) $caption .= "💬 Comments: " . $stats['comment'] . "\n";
            if (isset($stats['share'])) $caption .= "🔄 Shares: " . $stats['share'] . "\n";
        }
        
        $caption .= "\n🎬 Sending video...\n";
        $caption .= "━━━━━━━━━━━━━━━━━━━━";
        
        // Send video directly
        if ($videoUrl) {
            try {
                $this->bot->sendVideo($chatId, $videoUrl, $caption);
                
                // Send music if available
                if ($musicUrl) {
                    $this->bot->sendAudio($chatId, $musicUrl, "🎵 TikTok Audio");
                }
            } catch (\Exception $e) {
                // Fallback: send as text with links
                $caption .= "\n\n📹 Video: {$videoUrl}";
                if ($musicUrl) {
                    $caption .= "\n🎵 Audio: {$musicUrl}";
                }
                $this->bot->sendMessage($chatId, $caption);
            }
        } else {
            $caption .= "\n\n❌ Video URL not available";
            $this->bot->sendMessage($chatId, $caption);
        }
    }
}
