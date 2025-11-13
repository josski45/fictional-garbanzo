<?php

namespace JosskiTools\Responses;

/**
 * Spotify Response Handler
 */
class SpotifyResponse {
    
    private $bot;
    
    public function __construct($bot) {
        $this->bot = $bot;
    }
    
    /**
     * Send Spotify response
     */
    public function send($chatId, $data, $downloadUrl = null, $loadingMsgId = null) {
        if ($loadingMsgId) {
            $this->bot->deleteMessage($chatId, $loadingMsgId);
        }
        
        $title = $data['title'] ?? 'Unknown Track';
        $artist = $data['artist'] ?? 'Unknown Artist';
        $album = $data['album'] ?? '';
        $duration = $data['duration'] ?? '';
        $cover = $data['cover'] ?? null;
        
        $caption = "━━━━━━━━━━━━━━━━━━━━\n";
        $caption .= "✅ SPOTIFY DOWNLOADER\n";
        $caption .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $caption .= "🎵 " . $title . "\n";
        $caption .= "👤 Artist: " . $artist . "\n";
        
        if (!empty($album)) {
            $caption .= "💿 Album: " . $album . "\n";
        }
        
        if (!empty($duration)) {
            $caption .= "⏱️ Duration: " . $duration . "\n";
        }
        
        $caption .= "\n🎧 Sending audio...\n";
        $caption .= "━━━━━━━━━━━━━━━━━━━━";
        
        // Send audio
        if ($downloadUrl) {
            try {
                $this->bot->sendAudio($chatId, $downloadUrl, $caption);
            } catch (\Exception $e) {
                // Fallback: send as text with link
                $caption .= "\n\n🎵 Download: {$downloadUrl}";
                $this->bot->sendMessage($chatId, $caption);
            }
        } else {
            $caption .= "\n\n❌ Download URL not available";
            $this->bot->sendMessage($chatId, $caption);
        }
    }
}
