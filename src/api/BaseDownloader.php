<?php

namespace JosskiTools\Api;

/**
 * Generic Downloader Interface
 * Akan diganti dengan API baru (non-Ferdev)
 */
abstract class BaseDownloader {
    
    protected $apiKey;
    protected $baseUrl;
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Make API request
     */
    protected function request($endpoint, $params = []) {
        // Base request implementation
        // Akan di-override oleh child class
        return [];
    }
    
    /**
     * TikTok Downloader
     * TODO: Implement dengan API baru
     */
    abstract public function tiktok($url);
    
    /**
     * Instagram Downloader
     * TODO: Implement dengan API baru
     */
    abstract public function instagram($url);
    
    /**
     * Facebook Downloader
     * TODO: Implement dengan API baru
     */
    abstract public function facebook($url);
    
    /**
     * YouTube MP3 Downloader
     * TODO: Implement dengan API baru
     */
    abstract public function ytmp3($url);
    
    /**
     * YouTube MP4 Downloader
     * TODO: Implement dengan API baru
     */
    abstract public function ytmp4($url);
    
    /**
     * Spotify Downloader
     * TODO: Implement dengan API baru
     */
    abstract public function spotify($url);
    
    /**
     * CapCut Downloader
     * TODO: Implement dengan API baru
     */
    abstract public function capcut($url);
}
