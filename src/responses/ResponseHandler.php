<?php

namespace JosskiTools\Responses;

/**
 * Response Handler - Route responses to appropriate handlers
 */
class ResponseHandler {
    
    private $bot;
    
    public function __construct($bot) {
        $this->bot = $bot;
    }
    
    /**
     * Handle response based on platform
     */
    public function handle($chatId, $platform, $data, $fullResult, $loadingMsgId = null) {
        // Load specific response handlers
        require_once __DIR__ . '/TiktokResponse.php';
        require_once __DIR__ . '/FacebookResponse.php';
        require_once __DIR__ . '/SpotifyResponse.php';
        require_once __DIR__ . '/YoutubeResponse.php';
        require_once __DIR__ . '/CapcutResponse.php';
        
        switch ($platform) {
            case 'tiktok':
                $handler = new TiktokResponse($this->bot);
                $handler->send($chatId, $data, $loadingMsgId);
                break;
                
            case 'facebook':
                $handler = new FacebookResponse($this->bot);
                $handler->send($chatId, $data, $loadingMsgId);
                break;
                
            case 'spotify':
                $handler = new SpotifyResponse($this->bot);
                $downloadUrl = $fullResult['download'] ?? null;
                $handler->send($chatId, $data, $downloadUrl, $loadingMsgId);
                break;
                
            case 'ytmp3':
            case 'ytmp4':
                $handler = new YoutubeResponse($this->bot);
                $handler->send($chatId, $data, $platform, $loadingMsgId);
                break;
                
            case 'capcut':
                $handler = new CapcutResponse($this->bot);
                $handler->send($chatId, $data, $loadingMsgId);
                break;
                
            default:
                if ($loadingMsgId) {
                    $this->bot->deleteMessage($chatId, $loadingMsgId);
                }
                $this->bot->sendMessage($chatId, "✅ Download successful!\n\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                break;
        }
    }
}
