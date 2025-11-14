<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\Logger;
use JosskiTools\Utils\UserLogger;
use JosskiTools\Utils\ChannelHistory;
use JosskiTools\Utils\DownloadHistory;
use JosskiTools\Api\NekoLabsClient;

/**
 * BulkDownloadHandler - Handle bulk/playlist downloads
 */
class BulkDownloadHandler {

    private $bot;
    private $config;
    private $nekoLabsClient;
    private $maxBulkItems = 10; // Limit to prevent abuse

    public function __construct($bot, $config) {
        $this->bot = $bot;
        $this->config = $config;

        $apiVersion = $config['NEKOLABS_AIO_VERSION'] ?? ($config['NEKOLABS_API_VERSION'] ?? 'v5');
        $this->nekoLabsClient = new NekoLabsClient($apiVersion);

        Logger::init($config['directories']['logs'] ?? null);
        UserLogger::init($config['directories']['logs'] ?? null);
        ChannelHistory::init($bot, $config);
        DownloadHistory::init($config['directories']['data'] ?? null);
    }

    /**
     * Handle bulk download from multiple URLs
     */
    public function handleBulkDownload($chatId, $userId, $urls) {
        UserLogger::log($userId, "Bulk download initiated", ['url_count' => count($urls)]);

        // Validate URL count
        if (count($urls) > $this->maxBulkItems) {
            $this->bot->sendMessage(
                $chatId,
                "⚠️ Maximum {$this->maxBulkItems} URLs allowed per bulk download.\n\n" .
                "You provided: " . count($urls) . " URLs"
            );
            return;
        }

        if (empty($urls)) {
            $this->bot->sendMessage(
                $chatId,
                "❌ No valid URLs provided.\n\n" .
                "Usage: /bulk <url1> <url2> <url3> ..."
            );
            return;
        }

        Logger::info("Bulk download started", [
            'user_id' => $userId,
            'url_count' => count($urls)
        ]);

        // Send initial status message
        $statusMsg = $this->bot->sendMessage(
            $chatId,
            "📦 **Bulk Download Started**\n\n" .
            "Total URLs: " . count($urls) . "\n" .
            "Processing: 0/" . count($urls) . "\n\n" .
            "⏳ Please wait..."
        );

        $statusMsgId = $statusMsg['result']['message_id'] ?? null;

        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($urls as $index => $url) {
            $current = $index + 1;

            // Update status
            if ($statusMsgId) {
                $this->bot->editMessage(
                    $chatId,
                    $statusMsgId,
                    "📦 **Bulk Download**\n\n" .
                    "Processing: {$current}/" . count($urls) . "\n" .
                    "✅ Success: {$success}\n" .
                    "❌ Failed: {$failed}\n\n" .
                    "⏳ Downloading..."
                );
            }

            try {
                // Download using NekoLabs API
                $result = $this->nekoLabsClient->download($url);

                if ($result['success']) {
                    $success++;
                    $results[] = [
                        'url' => $url,
                        'success' => true,
                        'data' => $result['result']
                    ];

                    UserLogger::log($userId, "Bulk item success", [
                        'index' => $current,
                        'platform' => $result['result']['source'] ?? 'unknown'
                    ]);

                    // Add to download history
                    DownloadHistory::addDownload(
                        $userId,
                        $url,
                        $result['result']['source'] ?? 'unknown',
                        $result['result']['title'] ?? null,
                        $result['result']['type'] ?? null
                    );

                } else {
                    $failed++;
                    $results[] = [
                        'url' => $url,
                        'success' => false,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];

                    UserLogger::logError($userId, "Bulk item failed", [
                        'index' => $current,
                        'error' => $result['error'] ?? 'Unknown'
                    ]);
                }

            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'url' => $url,
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                Logger::exception($e, [
                    'context' => 'bulk_download',
                    'user_id' => $userId,
                    'index' => $current
                ]);
            }

            // Rate limiting - wait 2 seconds between requests
            if ($current < count($urls)) {
                sleep(2);
            }
        }

        // Final status update
        if ($statusMsgId) {
            $this->bot->editMessage(
                $chatId,
                $statusMsgId,
                "✅ **Bulk Download Complete**\n\n" .
                "Total: " . count($urls) . "\n" .
                "✅ Success: {$success}\n" .
                "❌ Failed: {$failed}\n\n" .
                "Sending results..."
            );
        }

        // Send results
        $this->sendBulkResults($chatId, $userId, $results);

        Logger::info("Bulk download completed", [
            'user_id' => $userId,
            'total' => count($urls),
            'success' => $success,
            'failed' => $failed
        ]);
    }

    /**
     * Send bulk download results
     */
    private function sendBulkResults($chatId, $userId, $results) {
        $successResults = array_filter($results, function($r) { return $r['success']; });
        $failedResults = array_filter($results, function($r) { return !$r['success']; });

        // Check if user has channel setup
        $hasChannel = ChannelHistory::hasChannel($userId);

        // Send successful downloads
        foreach ($successResults as $result) {
            try {
                $data = $result['data'];
                $platform = $data['source'] ?? 'unknown';
                $title = $data['title'] ?? 'No title';
                $url = $result['url'];

                // Prepare download metadata
                $downloadInfo = [
                    'platform' => $platform,
                    'title' => $title,
                    'url' => $url
                ];

                $sentMessages = [];

                // Get first media
                if (!empty($data['medias'])) {
                    $media = $data['medias'][0];
                    $mediaUrl = $media['url'] ?? null;
                    $mediaType = $media['type'] ?? 'unknown';

                    $caption = "📥 {$title}\n🌐 {$platform}";

                    // Send based on type
                    if ($mediaType === 'video' && $mediaUrl) {
                        $messageResponse = $this->bot->sendVideo($chatId, $mediaUrl, $caption);
                        if ($summary = $this->summarizeMessage($messageResponse, 'video')) {
                            $sentMessages[] = $summary;
                        }
                    } elseif ($mediaType === 'audio' && $mediaUrl) {
                        $messageResponse = $this->bot->sendAudio($chatId, $mediaUrl, $caption);
                        if ($summary = $this->summarizeMessage($messageResponse, 'audio')) {
                            $sentMessages[] = $summary;
                        }
                    } elseif ($mediaType === 'image' && $mediaUrl) {
                        $messageResponse = $this->bot->sendPhoto($chatId, $mediaUrl, $caption);
                        if ($summary = $this->summarizeMessage($messageResponse, 'photo')) {
                            $sentMessages[] = $summary;
                        }
                    } else {
                        // Fallback: send as text
                        $messageResponse = $this->bot->sendMessage($chatId, $caption . "\n\n📥 " . $mediaUrl);
                        if ($summary = $this->summarizeMessage($messageResponse, 'text')) {
                            $sentMessages[] = $summary;
                        }
                    }

                    if (!empty($sentMessages)) {
                        ChannelHistory::rememberMessages($userId, $sentMessages, $downloadInfo);
                    }

                    // Forward to channel if setup
                    if ($hasChannel && $mediaUrl) {
                        try {
                            $channelResult = ChannelHistory::sendToChannel(
                                $userId,
                                $mediaUrl,
                                $mediaType,
                                $downloadInfo
                            );

                            if (!empty($channelResult['token']) && !empty($sentMessages)) {
                                ChannelHistory::rememberMessages(
                                    $userId,
                                    $sentMessages,
                                    $downloadInfo,
                                    ['channel_token' => $channelResult['token']]
                                );
                            }
                        } catch (\Exception $e) {
                            Logger::warning("Failed to forward bulk item to channel", [
                                'user_id' => $userId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                // Rate limiting
                sleep(1);

            } catch (\Exception $e) {
                Logger::exception($e, ['context' => 'bulk_send_result']);
            }
        }

        // Send failure summary if any
        if (!empty($failedResults)) {
            $failedMsg = "❌ **Failed Downloads:**\n\n";

            foreach ($failedResults as $index => $result) {
                $num = $index + 1;
                $error = $result['error'] ?? 'Unknown error';
                $failedMsg .= "{$num}. ❌ {$error}\n";
            }

            $this->bot->sendMessage($chatId, $failedMsg);
        }
    }

    private function summarizeMessage($response, $type) {
        if (!is_array($response) || !($response['ok'] ?? false)) {
            return null;
        }

        $result = $response['result'] ?? [];
        $messageId = $result['message_id'] ?? null;
        if (!$messageId) {
            return null;
        }

        return [
            'message_id' => $messageId,
            'type' => $type,
            'file_id' => $this->extractFileId($result, $type)
        ];
    }

    private function extractFileId(array $result, $type) {
        switch ($type) {
            case 'video':
                return $result['video']['file_id'] ?? null;
            case 'audio':
                return $result['audio']['file_id'] ?? null;
            case 'photo':
                if (!empty($result['photo']) && is_array($result['photo'])) {
                    $photos = $result['photo'];
                    $last = end($photos);
                    return $last['file_id'] ?? null;
                }
                return null;
            default:
                return null;
        }
    }

    /**
     * Parse YouTube playlist (placeholder - requires YouTube API)
     */
    public function handleYouTubePlaylist($chatId, $userId, $playlistUrl) {
        $this->bot->sendMessage(
            $chatId,
            "🎬 **YouTube Playlist Download**\n\n" .
            "⚠️ This feature requires YouTube API integration.\n\n" .
            "For now, please use bulk download with individual URLs:\n" .
            "`/bulk <url1> <url2> <url3>`"
        );

        UserLogger::log($userId, "YouTube playlist requested", ['url' => $playlistUrl]);

        // TODO: Implement YouTube API integration
        // This would require:
        // 1. YouTube API key
        // 2. Parse playlist ID
        // 3. Fetch video URLs from playlist
        // 4. Call handleBulkDownload with video URLs
    }

    /**
     * Set max bulk items (admin)
     */
    public function setMaxBulkItems($max) {
        $this->maxBulkItems = $max;
        Logger::info("Max bulk items updated", ['max' => $max]);
    }

    /**
     * Get max bulk items
     */
    public function getMaxBulkItems() {
        return $this->maxBulkItems;
    }
}
