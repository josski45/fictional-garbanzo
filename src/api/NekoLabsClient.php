<?php

namespace JosskiTools\Api;

use JosskiTools\Utils\Logger;

/**
 * NekoLabs API Client - All-in-one downloader for TikTok, YouTube, Instagram, Facebook, etc.
 * API Documentation: https://api.nekolabs.web.id
 */
class NekoLabsClient {

    private $baseUrl = 'https://api.nekolabs.web.id';
    private $version = 'v1'; // Default version
    private $timeout = 30;
    private $maxRetries = 3;

    /**
     * Constructor
     *
     * @param string $version API version (v1, v2, v3, v4)
     */
    public function __construct($version = 'v1') {
        $this->version = $version;
    }

    /**
     * Download content from any supported platform
     *
     * @param string $url URL to download
     * @return array Response data
     */
    public function download($url) {
        $endpoint = "/downloader/aio/{$this->version}";

        Logger::apiRequest('NekoLabs', $endpoint, ['url' => $url]);

        $result = $this->makeRequest($endpoint, ['url' => $url]);

        if ($result['success']) {
            Logger::apiResponse('NekoLabs', true, [
                'source' => $result['result']['source'] ?? 'unknown',
                'type' => $result['result']['type'] ?? 'unknown'
            ]);
        } else {
            Logger::apiResponse('NekoLabs', false, [
                'error' => $result['error'] ?? 'Unknown error'
            ]);
        }

        return $result;
    }

    /**
     * Make HTTP request to API
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array Response data
     */
    private function makeRequest($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;

        // Add query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $retryDelay = 2; // Start with 2 seconds

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                Logger::debug("NekoLabs API attempt {$attempt}/{$this->maxRetries}", ['url' => $url]);

                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept: application/json',
                        'Accept-Language: en-US,en;q=0.9'
                    ]
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                // Handle cURL errors
                if ($curlError) {
                    Logger::error("NekoLabs cURL error (attempt {$attempt})", [
                        'error' => $curlError,
                        'url' => $url
                    ]);

                    if ($attempt < $this->maxRetries) {
                        sleep($retryDelay);
                        $retryDelay *= 2; // Exponential backoff
                        continue;
                    }

                    return [
                        'success' => false,
                        'error' => 'Connection error: ' . $curlError,
                        'status_code' => 0
                    ];
                }

                // Handle HTTP errors
                if ($httpCode === 429) {
                    Logger::warning("NekoLabs rate limit exceeded", ['attempt' => $attempt]);

                    if ($attempt < $this->maxRetries) {
                        sleep($retryDelay * 2); // Wait longer for rate limits
                        $retryDelay *= 2;
                        continue;
                    }

                    return [
                        'success' => false,
                        'error' => 'Rate limit exceeded. Please try again later.',
                        'status_code' => 429
                    ];
                }

                if ($httpCode === 400) {
                    return [
                        'success' => false,
                        'error' => 'Invalid parameters or URL format',
                        'status_code' => 400
                    ];
                }

                if ($httpCode === 405) {
                    return [
                        'success' => false,
                        'error' => 'Method not allowed',
                        'status_code' => 405
                    ];
                }

                if ($httpCode === 500) {
                    Logger::error("NekoLabs server error (attempt {$attempt})", [
                        'http_code' => $httpCode,
                        'url' => $url
                    ]);

                    if ($attempt < $this->maxRetries) {
                        sleep($retryDelay);
                        $retryDelay *= 2;
                        continue;
                    }

                    return [
                        'success' => false,
                        'error' => 'Server error. Please try again later.',
                        'status_code' => 500
                    ];
                }

                if ($httpCode !== 200) {
                    return [
                        'success' => false,
                        'error' => "HTTP error: {$httpCode}",
                        'status_code' => $httpCode
                    ];
                }

                // Parse JSON response
                $data = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Logger::error("NekoLabs JSON parse error", [
                        'error' => json_last_error_msg(),
                        'response' => substr($response, 0, 200)
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Invalid JSON response',
                        'status_code' => $httpCode
                    ];
                }

                // Check API success flag
                if (!($data['success'] ?? false)) {
                    $errorMsg = $data['message'] ?? $data['error'] ?? 'Unknown error';

                    return [
                        'success' => false,
                        'error' => $errorMsg,
                        'status_code' => $httpCode,
                        'data' => $data
                    ];
                }

                // Success!
                return [
                    'success' => true,
                    'result' => $data['result'] ?? $data,
                    'timestamp' => $data['timestamp'] ?? null,
                    'responseTime' => $data['responseTime'] ?? null,
                    'status_code' => $httpCode
                ];

            } catch (\Exception $e) {
                Logger::exception($e, [
                    'attempt' => $attempt,
                    'url' => $url
                ]);

                if ($attempt < $this->maxRetries) {
                    sleep($retryDelay);
                    $retryDelay *= 2;
                    continue;
                }

                return [
                    'success' => false,
                    'error' => 'Exception: ' . $e->getMessage(),
                    'status_code' => 0
                ];
            }
        }

        // Should not reach here, but just in case
        return [
            'success' => false,
            'error' => 'Max retries exceeded',
            'status_code' => 0
        ];
    }

    /**
     * Set API version
     */
    public function setVersion($version) {
        $this->version = $version;
    }

    /**
     * Get current API version
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * Set timeout
     */
    public function setTimeout($timeout) {
        $this->timeout = $timeout;
    }

    /**
     * Test API connection
     */
    public function test() {
        $testUrl = 'https://www.tiktok.com/@tiktok/video/7106594312292453675';
        return $this->download($testUrl);
    }

    /**
     * Get supported platforms
     */
    public static function getSupportedPlatforms() {
        return [
            'tiktok',
            'youtube',
            'instagram',
            'facebook',
            'twitter',
            'spotify',
            'soundcloud',
            'capcut',
            'pinterest',
            'reddit',
            'threads',
            'snackvideo'
        ];
    }

    /**
     * Detect platform from URL
     */
    public static function detectPlatform($url) {
        $patterns = [
            'tiktok' => ['tiktok.com', 'vt.tiktok.com', 'vm.tiktok.com'],
            'youtube' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
            'instagram' => ['instagram.com', 'instagr.am'],
            'facebook' => ['facebook.com', 'fb.com', 'fb.watch'],
            'twitter' => ['twitter.com', 'x.com'],
            'spotify' => ['spotify.com', 'open.spotify.com'],
            'soundcloud' => ['soundcloud.com'],
            'capcut' => ['capcut.com'],
            'pinterest' => ['pinterest.com', 'pin.it'],
            'reddit' => ['reddit.com', 'redd.it'],
            'threads' => ['threads.net'],
            'snackvideo' => ['snackvideo.com']
        ];

        $urlLower = strtolower($url);

        foreach ($patterns as $platform => $domains) {
            foreach ($domains as $domain) {
                if (strpos($urlLower, $domain) !== false) {
                    return $platform;
                }
            }
        }

        return 'unknown';
    }
}
