<?php

namespace JosskiTools\Api\Ferdev;

require_once __DIR__ . '/FerdevClient.php';

/**
 * Ferdev AI Tools
 * ChatGPT, Image Generation, dll
 */
class AiTools extends \JosskiTools\Api\FerdevClient {
    
    /**
     * ChatGPT
     */
    public function chatgpt($prompt, $model = 'gpt-3.5-turbo') {
        return $this->request('/api/chatgpt', [
            'prompt' => $prompt,
            'model' => $model
        ]);
    }
    
    /**
     * Generate image with AI
     */
    public function generateImage($prompt, $style = 'realistic') {
        return $this->request('/api/ai-image', [
            'prompt' => $prompt,
            'style' => $style
        ]);
    }
    
    /**
     * Text to Speech
     */
    public function textToSpeech($text, $lang = 'id') {
        return $this->request('/api/tts', [
            'text' => $text,
            'lang' => $lang
        ]);
    }
}
