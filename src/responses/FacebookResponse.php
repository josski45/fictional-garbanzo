<?php

namespace JosskiTools\Responses;

/**
 * Facebook Response Handler
 */
class FacebookResponse {
    
    private $bot;
    
    public function __construct($bot) {
        $this->bot = $bot;
    }
    
    /**
     * Send Facebook response
     */
    public function send($chatId, $data, $loadingMsgId = null) {
        if ($loadingMsgId) {
            $this->bot->deleteMessage($chatId, $loadingMsgId);
        }
        
        $title = $data['title'] ?? 'Facebook Video';
        $hdUrl = $data['hd'] ?? null;
        $sdUrl = $data['sd'] ?? null;
        $thumbnail = $data['thumbnail'] ?? null;
        
        $caption = "━━━━━━━━━━━━━━━━━━━━\n";
        $caption .= "✅ FACEBOOK DOWNLOADER\n";
        $caption .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $caption .= "📌 " . $title . "\n\n";
        $caption .= "🎬 Available Qualities:\n";
        
        // Try to send HD video directly
        if ($hdUrl) {
            $caption .= "• HD Quality (sending...)\n";
            try {
                $this->bot->sendVideo($chatId, $hdUrl, $caption);
                return;
            } catch (\Exception $e) {
                // HD failed, try SD
            }
        }
        
        // Try SD
        if ($sdUrl) {
            $caption .= "• SD Quality (sending...)\n";
            try {
                $this->bot->sendVideo($chatId, $sdUrl, $caption);
                return;
            } catch (\Exception $e) {
                // Both failed, send as links
            }
        }
        
        // Fallback: send as text with links
        $caption .= "\n\n📹 Download Links:\n";
        if ($hdUrl) $caption .= "HD: {$hdUrl}\n";
        if ($sdUrl) $caption .= "SD: {$sdUrl}\n";
        
        $this->bot->sendMessage($chatId, $caption);
    }
}
