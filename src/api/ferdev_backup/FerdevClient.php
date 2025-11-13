<?php

namespace JosskiTools\Api;

/**
 * Ferdev API Client
 * Base class untuk semua API Ferdev
 */
class FerdevClient {
    private $baseUrl = 'https://api.ferdev.my.id';
    private $apiKey;
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Make API request
     */
    protected function request($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        // Add API key to params
        if ($this->apiKey) {
            $params['apikey'] = $this->apiKey;
        }
        
        // Build query string
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return [
                'status' => false,
                'message' => 'cURL Error: ' . $error
            ];
        }
        
        $result = json_decode($response, true);
        if ($result === null) {
            return [
                'status' => false,
                'message' => 'Invalid JSON response',
                'raw' => $response
            ];
        }
        
        return $result;
    }
    
    /**
     * Get API base URL
     */
    public function getBaseUrl() {
        return $this->baseUrl;
    }
}
