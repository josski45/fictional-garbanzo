<?php

namespace JosskiTools\Api;

use JosskiTools\Utils\Logger;

/**
 * SSSTikProClient - Scraper for fetching TikTok user videos via ssstikpro.net
 */
class SSSTikProClient {
    private $baseUrl = 'https://ssstikpro.net/api/ajaxSearch';
    private $perPage = 10;
    private $timeout = 30;

    /**
     * Fetch videos for given username and page.
     */
    public function getUserVideos(string $username, int $page = 0): array {
        $username = ltrim(trim($username), '@');

        if ($username === '') {
            return [
                'success' => false,
                'error' => 'Username is required'
            ];
        }

        $page = max(0, $page);
        $cursor = $page > 0 ? (string)($page * 1000000000000) : '0';

        $url = "https://www.tiktok.com/@{$username}";
        $response = $this->makeRequest($url, $cursor, $page);

        if (!$response['success']) {
            return $response;
        }

        return $response;
    }

    /**
     * Fetch up to 6 pages of videos (TikTokUserHandler expectation).
     */
    public function getAllUserVideos(string $username, int $maxPages = 6): array {
        $username = ltrim(trim($username), '@');

        if ($username === '') {
            return [
                'success' => false,
                'error' => 'Username is required'
            ];
        }

        $allVideos = [];
        $page = 0;
        $lastResult = null;

        while ($page < $maxPages) {
            $result = $this->getUserVideos($username, $page);
            if (!$result['success']) {
                // If first page fails, return error
                if ($page === 0) {
                    return $result;
                }
                // Otherwise, just stop
                break;
            }

            $lastResult = $result;
            $videos = $result['videos'] ?? [];
            $allVideos = array_merge($allVideos, $videos);

            if (!$result['has_more'] || empty($videos)) {
                break;
            }

            $page++;
        }

        return [
            'success' => true,
            'username' => $lastResult['username'] ?? $username,
            'nickname' => $lastResult['nickname'] ?? null,
            'total_pages' => $page + 1,
            'videos' => $allVideos,
            'total_videos' => $lastResult['total_videos'] ?? count($allVideos)
        ];
    }

    /**
     * Make HTTP request to SSSTikPro API
     */
    private function makeRequest(string $userUrl, string $cursor, int $page): array {
        $postData = http_build_query([
            'q' => $userUrl,
            'cursor' => $cursor,
            'page' => (string)$page,
            'lang' => 'en'
        ]);

        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'accept-language: en-US,en;q=0.9',
                'dnt: 1',
                'origin: https://ssstikpro.net',
                'referer: https://ssstikpro.net/',
                'sec-ch-ua: "Chromium";v="142", "Brave";v="142", "Not_A Brand";v="99"',
                'sec-ch-ua-mobile: ?1',
                'sec-ch-ua-platform: "Android"',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: same-origin',
                'sec-gpc: 1',
                'x-requested-with: XMLHttpRequest'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            Logger::error('SSSTikProClient cURL error', [
                'url' => $userUrl,
                'error' => $curlError ?: 'Unknown cURL error',
                'http_code' => $httpCode
            ]);

            return [
                'success' => false,
                'error' => 'Unable to contact TikTok service. Please try again later.'
            ];
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            Logger::error('SSSTikProClient invalid JSON response', [
                'url' => $userUrl,
                'snippet' => substr($response, 0, 200)
            ]);

            return [
                'success' => false,
                'error' => 'Invalid response from TikTok service'
            ];
        }

        if (($json['status'] ?? '') !== 'ok') {
            $message = $json['message'] ?? 'Failed to fetch videos';
            Logger::warning('SSSTikProClient API failure', [
                'url' => $userUrl,
                'status' => $json['status'] ?? 'unknown',
                'message' => $message
            ]);

            return [
                'success' => false,
                'error' => $message
            ];
        }

        // Parse HTML response
        $html = $json['data'] ?? '';
        $videos = $this->parseVideosFromHtml($html);
        
        $username = $this->extractUsernameFromUrl($userUrl);
        $hasMore = !empty($json['next_cursor'] ?? null);

        return [
            'success' => true,
            'username' => $username,
            'nickname' => null,
            'page' => $page,
            'per_page' => $this->perPage,
            'videos' => $videos,
            'has_more' => $hasMore,
            'cursor' => $json['next_cursor'] ?? null,
            'total_videos' => count($videos)
        ];
    }

    /**
     * Parse videos from HTML response
     */
    private function parseVideosFromHtml(string $html): array {
        if (empty($html)) {
            return [];
        }

        $videos = [];
        
        // Match video items using regex
        preg_match_all('/<div class="video-item">.*?<\/div>\s*<\/div>\s*<\/div>/s', $html, $matches);
        
        foreach ($matches[0] as $videoHtml) {
            $video = $this->parseVideoItem($videoHtml);
            if ($video) {
                $videos[] = $video;
            }
        }

        return $videos;
    }

    /**
     * Parse individual video item
     */
    private function parseVideoItem(string $html): ?array {
        // Extract thumbnail
        $thumbnail = null;
        if (preg_match('/<img src="([^"]+)"/', $html, $m)) {
            $thumbnail = $m[1];
        }

        // Extract views
        $views = null;
        if (preg_match('/<span>([^<]+)<\/span>\s*<\/div>\s*<\/div>\s*<\/div>\s*<div class="pro-information">/', $html, $m)) {
            $views = trim($m[1]);
        }

        // Extract title
        $title = '';
        if (preg_match('/<p class="text-title">([^<]*)<\/p>/', $html, $m)) {
            $title = trim(strip_tags($m[1]));
        }

        // Extract download URL - Updated regex to handle attributes in any order
        $downloadUrl = null;
        if (preg_match('/<a[^>]+class="pro-dl-link"[^>]+href="([^"]+)"/', $html, $m)) {
            $downloadUrl = html_entity_decode($m[1]);
        } elseif (preg_match('/<a[^>]+href="([^"]+)"[^>]+class="pro-dl-link"/', $html, $m)) {
            $downloadUrl = html_entity_decode($m[1]);
        }

        // Extract filename for ID
        $id = null;
        if (preg_match('/data-name="SSSTikPro\.net_(\d+)\.mp4"/', $html, $m)) {
            $id = $m[1];
        }

        if (!$downloadUrl) {
            Logger::warning('SSSTikProClient: No download URL found in video item', [
                'html_snippet' => substr($html, 0, 200)
            ]);
            return null;
        }

        return [
            'id' => $id,
            'title' => $title ?: 'No title',
            'download_url' => $downloadUrl,
            'thumbnail' => $thumbnail,
            'views' => $views ?: 'N/A',
            'duration' => null,
            'create_time' => null,
            'share_url' => null
        ];
    }

    /**
     * Extract username from TikTok URL
     */
    private function extractUsernameFromUrl(string $url): string {
        if (preg_match('/@([^\/\?]+)/', $url, $m)) {
            return $m[1];
        }
        return '';
    }
}
