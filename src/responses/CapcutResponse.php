<?php

namespace JosskiTools\Responses;

/**
 * CapCut Response Handler
 */
class CapcutResponse {
    
    private $bot;
    
    public function __construct($bot) {
        $this->bot = $bot;
    }
    
    /**
     * Send CapCut response
     */
    public function send($chatId, $data, $loadingMsgId = null) {
        if ($loadingMsgId) {
            $this->bot->deleteMessage($chatId, $loadingMsgId);
        }
        
        $title = $data['title'] ?? 'Unknown Title';
        $date = $data['date'] ?? 'Unknown';
        $likes = isset($data['likes']) ? number_format($data['likes']) : 'Unknown';
        $pengguna = isset($data['pengguna']) ? number_format($data['pengguna']) : 'Unknown';
        $author = $data['author']['name'] ?? 'Unknown';
        $videoUrl = $data['videoUrl'] ?? null;
        $posterUrl = $data['posterUrl'] ?? null;
        
        $caption = "━━━━━━━━━━━━━━━━━━━━\n";
        $caption .= "✅ CAPCUT DOWNLOADER\n";
        $caption .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $caption .= "📌 " . $title . "\n\n";
        $caption .= "👤 Creator: " . $author . "\n";
        $caption .= "📅 Date: " . $date . "\n";
        $caption .= "❤️ Likes: " . $likes . "\n";
        $caption .= "👥 Users: " . $pengguna . "\n\n";
        $caption .= "🎨 Sending template video...\n";
        $caption .= "━━━━━━━━━━━━━━━━━━━━";
        
        // Send video directly
        if ($videoUrl) {
            try {
                $this->bot->sendVideo($chatId, $videoUrl, $caption);
            } catch (\Exception $e) {
                $caption .= "\n\n📹 Video: {$videoUrl}";
                $this->bot->sendMessage($chatId, $caption);
            }
        } else {
            $caption .= "\n\n❌ Video URL not available";
            $this->bot->sendMessage($chatId, $caption);
        }
    }
}
