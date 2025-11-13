<?php

namespace JosskiTools\Api\Ferdev;

require_once __DIR__ . '/FerdevClient.php';

/**
 * Ferdev Search & Info API
 */
class SearchTools extends \JosskiTools\Api\FerdevClient {
    
    /**
     * Google Search
     */
    public function google($query, $limit = 10) {
        return $this->request('/api/google', [
            'q' => $query,
            'limit' => $limit
        ]);
    }
    
    /**
     * YouTube Search
     */
    public function youtubeSearch($query, $limit = 10) {
        return $this->request('/api/youtube-search', [
            'q' => $query,
            'limit' => $limit
        ]);
    }
    
    /**
     * Get weather info
     */
    public function weather($city) {
        return $this->request('/api/weather', ['city' => $city]);
    }
    
    /**
     * Wikipedia search
     */
    public function wikipedia($query, $lang = 'id') {
        return $this->request('/api/wikipedia', [
            'q' => $query,
            'lang' => $lang
        ]);
    }
}
