<?php

namespace JosskiTools\Utils;

class QuotesManager {
    private $cacheFile;
    private $apiUrl = 'https://quotes.domiadi.com/api';
    
    public function __construct($cacheDir = null) {
        if (!$cacheDir) {
            $cacheDir = __DIR__ . '/../../data/cache';
        }
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        
        $this->cacheFile = $cacheDir . '/daily_quote.json';
    }
    
    /**
     * Get daily quote (cached per day)
     */
    public function getDailyQuote() {
        $today = date('Y-m-d');
        
        // Check if cache exists and is from today
        if (file_exists($this->cacheFile)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            
            if ($cached && isset($cached['date']) && $cached['date'] === $today) {
                return $cached['quote'];
            }
        }
        
        // Fetch new quote
        $quote = $this->fetchQuote();
        
        if ($quote) {
            // Cache it
            $cache = [
                'date' => $today,
                'quote' => $quote,
                'cached_at' => date('Y-m-d H:i:s')
            ];
            
            file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
        }
        
        return $quote;
    }
    
    /**
     * Fetch quote from API
     */
    private function fetchQuote() {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if ($data && isset($data['quote']) && isset($data['from'])) {
                    return [
                        'text' => $data['quote'],
                        'author' => $data['from'],
                        'id' => $data['id'] ?? null
                    ];
                }
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get fallback quote if API fails
     */
    public function getFallbackQuote() {
        return [
            'text' => 'Every day is a chance to start anew.',
            'author' => 'Anonymous',
            'id' => null
        ];
    }
}
