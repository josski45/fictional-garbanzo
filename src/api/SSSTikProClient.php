<?php

namespace JosskiTools\Api;

/**
 * SSSTik Pro Client - Download ALL videos from TikTok username
 */
class SSSTikProClient {
    
    private $baseUrl = 'https://ssstikpro.net/api/ajaxSearch';
    
    /**
     * Get all videos from TikTok username
     * 
     * @param string $username TikTok username (with or without @)
     * @param int $page Page number (0-5)
     * @param string $cursor Cursor for pagination
     * @return array Response data
     */
    public function getUserVideos($username, $page = 0, $cursor = '0') {
        // Remove @ if exists
        $username = ltrim($username, '@');
        
        // Build TikTok profile URL
        $profileUrl = "https://www.tiktok.com/@{$username}";
        
        // Prepare POST data
        $postData = http_build_query([
            'q' => $profileUrl,
            'cursor' => $cursor,
            'page' => (string)$page,
            'lang' => 'en'
        ]);
        
        // Prepare headers
        $headers = [
            'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Accept-Language: en-US,en;q=0.9',
            'DNT: 1',
            'Origin: https://ssstikpro.net',
            'Referer: https://ssstikpro.net/',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'X-Requested-With: XMLHttpRequest'
        ];
        
        // Initialize cURL
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $httpCode
            ];
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['status']) || $data['status'] !== 'ok') {
            return [
                'success' => false,
                'error' => 'Invalid response from server'
            ];
        }
        
        // Check if data key exists
        if (!isset($data['data']) || empty($data['data'])) {
            return [
                'success' => false,
                'error' => 'No data returned from API'
            ];
        }
        
        // Parse HTML to extract video data
        $videos = $this->parseVideoData($data['data']);
        
        return [
            'success' => true,
            'username' => $username,
            'page' => $page,
            'next_cursor' => $data['next_cursor'] ?? null,
            'has_more' => !empty($data['next_cursor']),
            'total_videos' => count($videos),
            'videos' => $videos
        ];
    }
    
    /**
     * Parse HTML data to extract video information
     * 
     * @param string $html HTML content
     * @return array Video data
     */
    private function parseVideoData($html) {
        $videos = [];
        
        // Check if HTML is empty or null
        if (empty($html)) {
            return $videos;
        }
        
        // Use DOMDocument to parse HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        // Find all video items
        $videoItems = $xpath->query('//div[@class="video-item"]');
        
        foreach ($videoItems as $item) {
            $video = [];
            
            // Get thumbnail
            $thumbnail = $xpath->query('.//img[@alt="poster"]/@src', $item);
            if ($thumbnail->length > 0) {
                $video['thumbnail'] = $thumbnail->item(0)->nodeValue;
            }
            
            // Get view count
            $viewCount = $xpath->query('.//div[@class="count-view"]//span/text()', $item);
            if ($viewCount->length > 0) {
                $video['views'] = trim($viewCount->item(0)->nodeValue);
            }
            
            // Get title
            $title = $xpath->query('.//p[@class="text-title"]/text()', $item);
            if ($title->length > 0) {
                $video['title'] = trim($title->item(0)->nodeValue);
            }
            
            // Get download link
            $downloadLink = $xpath->query('.//a[@class="pro-dl-link"]/@href', $item);
            if ($downloadLink->length > 0) {
                $video['download_url'] = $downloadLink->item(0)->nodeValue;
            }
            
            // Get filename
            $filename = $xpath->query('.//a[@class="pro-dl-link"]/@data-name', $item);
            if ($filename->length > 0) {
                $video['filename'] = $filename->item(0)->nodeValue;
            }
            
            if (!empty($video)) {
                $videos[] = $video;
            }
        }
        
        return $videos;
    }
    
    /**
     * Get all videos from all pages (0-5)
     * WARNING: This will make 6 API calls!
     * 
     * @param string $username TikTok username
     * @return array All videos from all pages
     */
    public function getAllUserVideos($username) {
        $allVideos = [];
        $cursor = '0';
        
        for ($page = 0; $page <= 5; $page++) {
            $result = $this->getUserVideos($username, $page, $cursor);
            
            if (!$result['success']) {
                break;
            }
            
            $allVideos = array_merge($allVideos, $result['videos']);
            
            // Update cursor for next page
            if (!empty($result['next_cursor'])) {
                $cursor = $result['next_cursor'];
            }
            
            // Stop if no more videos
            if (!$result['has_more']) {
                break;
            }
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 second
        }
        
        return [
            'success' => true,
            'username' => $username,
            'total_videos' => count($allVideos),
            'videos' => $allVideos
        ];
    }
}
