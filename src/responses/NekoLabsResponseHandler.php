<?php

namespace JosskiTools\Responses;

use JosskiTools\Utils\Logger;

/**
 * NekoLabs Response Handler - Handle responses from NekoLabs API
 */
class NekoLabsResponseHandler {

    private $bot;
    private $requestedFormat;
    private $sentMessages = [];
    private $downloadContext = [];

    public function __construct($bot) {
        $this->bot = $bot;
    }

    /**
     * Handle response from NekoLabs API
     */
    public function handle($chatId, $result, $loadingMsgId = null, $requestedFormat = null, array $downloadContext = []) {
        $this->sentMessages = [];
        $this->requestedFormat = $requestedFormat;
        $this->downloadContext = $downloadContext;

        try {

            $source = $result['source'] ?? 'unknown';
            $type = $result['type'] ?? 'unknown';

            Logger::debug("Processing NekoLabs response", [
                'source' => $source,
                'type' => $type,
                'chat_id' => $chatId
            ]);

            // Delete loading message
            if ($loadingMsgId) {
                $this->bot->deleteMessage($chatId, $loadingMsgId);
            }

            // Route to appropriate handler based on type
            switch ($type) {
                case 'video':
                    $this->handleVideo($chatId, $result);
                    break;

                case 'audio':
                    Logger::info("Handling audio response", [
                        'chat_id' => $chatId,
                        'source' => $source,
                        'media_count' => count($result['medias'] ?? [])
                    ]);
                    $this->handleAudio($chatId, $result);
                    break;

                case 'image':
                    $this->handleImage($chatId, $result);
                    break;

                case 'multiple':
                    $this->handleMultiple($chatId, $result);
                    break;

                default:
                    $this->handleDefault($chatId, $result);
                    break;
            }

        } catch (\Exception $e) {
            Logger::exception($e, ['chat_id' => $chatId]);

            if ($loadingMsgId) {
                $this->bot->deleteMessage($chatId, $loadingMsgId);
            }

            $this->bot->sendMessage(
                $chatId,
                "❌ Error processing response: " . $e->getMessage()
            );
        } finally {
            $this->requestedFormat = null;
            $this->downloadContext = [];
        }

        return [
            'messages' => $this->sentMessages,
            'downloadInfo' => $this->buildDownloadInfo($result)
        ];
    }

    /**
     * Handle video response
     */
    private function handleVideo($chatId, $result) {
        $videoUrl = $result['medias'][0]['url'] ?? null;

        if (!$videoUrl) {
            $this->bot->sendMessage($chatId, "❌ No video URL found in response");
            return;
        }

        $caption = $this->buildCaption($result);

        // Try to send video
        $videoResult = $this->bot->sendVideo($chatId, $videoUrl, $caption, 'Markdown');
        $this->recordMessage($videoResult, 'video');

        if (!($videoResult['ok'] ?? false)) {
            Logger::warning("Failed to send video, sending as text", [
                'chat_id' => $chatId,
                'error' => $videoResult['description'] ?? 'Unknown'
            ]);

            // Fallback: send as text with download link
            $message = $caption . "\n\n📥 **Download:**\n" . $videoUrl;
            $fallback = $this->bot->sendMessage($chatId, $message, 'Markdown');
            $this->recordMessage($fallback, 'text');
        }
    }

    /**
     * Handle audio response
     */
    private function handleAudio($chatId, $result) {
        $audioUrl = $result['medias'][0]['url'] ?? null;

        if (!$audioUrl) {
            $this->bot->sendMessage($chatId, "❌ No audio URL found in response");
            return;
        }

        $caption = $this->buildCaption($result);
        $localFile = null;

        try {
            $extension = $this->guessExtension($audioUrl, '.m4a');
            $localFile = $this->downloadMediaToTemp($audioUrl, 'audio_', $extension);

            if ($localFile) {
                $audioResult = $this->bot->sendAudio($chatId, $localFile, $caption, 'Markdown');

                if ($audioResult['ok'] ?? false) {
                    $this->recordMessage($audioResult, 'audio');
                } else {
                    Logger::warning("Failed to send uploaded audio, falling back to link", [
                        'chat_id' => $chatId,
                        'error' => $audioResult['description'] ?? 'Unknown'
                    ]);

                    $this->sendAudioLinkFallback($chatId, $caption, $audioUrl, $audioResult['description'] ?? null);
                }
            } else {
                Logger::warning("Failed to download audio before sending", [
                    'chat_id' => $chatId,
                    'url' => $audioUrl
                ]);

                $audioResult = $this->bot->sendAudio($chatId, $audioUrl, $caption, 'Markdown');

                if ($audioResult['ok'] ?? false) {
                    $this->recordMessage($audioResult, 'audio');
                } else {
                    $this->sendAudioLinkFallback($chatId, $caption, $audioUrl, $audioResult['description'] ?? null);
                }
            }
        } finally {
            if ($localFile && file_exists($localFile)) {
                @unlink($localFile);
            }
        }
    }

    /**
     * Handle image response
     */
    private function handleImage($chatId, $result) {
        $imageUrl = $result['medias'][0]['url'] ?? null;

        if (!$imageUrl) {
            $this->bot->sendMessage($chatId, "❌ No image URL found in response");
            return;
        }

        $caption = $this->buildCaption($result);

        // Try to send photo
        $photoResult = $this->bot->sendPhoto($chatId, $imageUrl, $caption, 'Markdown');
        $this->recordMessage($photoResult, 'photo');

        if (!($photoResult['ok'] ?? false)) {
            Logger::warning("Failed to send photo, sending as text", [
                'chat_id' => $chatId,
                'error' => $photoResult['description'] ?? 'Unknown'
            ]);

            // Fallback: send as text with download link
            $message = $caption . "\n\n📥 **Download:**\n" . $imageUrl;
            $fallback = $this->bot->sendMessage($chatId, $message, 'Markdown');
            $this->recordMessage($fallback, 'text');
        }
    }

    /**
     * Handle multiple media (videos, images, audio)
     */
    private function handleMultiple($chatId, $result) {
        $medias = $result['medias'] ?? [];

        if (empty($medias)) {
            $this->bot->sendMessage($chatId, "❌ No media found in response");
            return;
        }

        $caption = $this->buildCaption($result);

        // Send caption first
        $captionMessage = $this->bot->sendMessage($chatId, $caption, 'Markdown');
        $this->recordMessage($captionMessage, 'text');

        // Group medias by type
        $videos = [];
        $audios = [];
        $images = [];

        foreach ($medias as $media) {
            $type = $media['type'] ?? 'unknown';

            switch ($type) {
                case 'video':
                    $videos[] = $media;
                    break;
                case 'audio':
                    $audios[] = $media;
                    break;
                case 'image':
                    $images[] = $media;
                    break;
            }
        }

        // Send videos (prioritize HD no watermark)
        if (!empty($videos) && !$this->isAudioRequested()) {
            // Sort videos by quality (hd_no_watermark first)
            usort($videos, function($a, $b) {
                $qualityOrder = ['hd_no_watermark' => 0, 'no_watermark' => 1, 'watermark' => 2];
                $aQuality = $qualityOrder[$a['quality'] ?? 'watermark'] ?? 999;
                $bQuality = $qualityOrder[$b['quality'] ?? 'watermark'] ?? 999;
                return $aQuality - $bQuality;
            });

            $bestVideo = $videos[0];
            $quality = $bestVideo['quality'] ?? 'unknown';
            $size = $this->formatFileSize($bestVideo['data_size'] ?? 0);

            $videoCaption = "🎬 **Quality:** {$quality}\n📦 **Size:** {$size}";

            $videoResult = $this->bot->sendVideo($chatId, $bestVideo['url'], $videoCaption, 'Markdown');
            $this->recordMessage($videoResult, 'video');

            if (!($videoResult['ok'] ?? false)) {
                // Fallback: send all video links
                $message = "📥 **Video Downloads:**\n\n";
                foreach ($videos as $idx => $video) {
                    $quality = $video['quality'] ?? 'unknown';
                    $size = $this->formatFileSize($video['data_size'] ?? 0);
                    $message .= ($idx + 1) . ". [{$quality} - {$size}](" . $video['url'] . ")\n";
                }
                $fallback = $this->bot->sendMessage($chatId, $message, 'Markdown');
                $this->recordMessage($fallback, 'text');
            }
        }

        // Send audio if available
        if (!empty($audios)) {
            $audio = $audios[0];
            $duration = $audio['duration'] ?? 0;
            $audioCaption = "🎵 **Duration:** " . gmdate("i:s", $duration);

            $audioUrl = $audio['url'] ?? null;

            if ($audioUrl) {
                $localFile = null;
                try {
                    $extension = $this->guessExtension($audioUrl, '.m4a');
                    $localFile = $this->downloadMediaToTemp($audioUrl, 'audio_', $extension);

                    if ($localFile) {
                        $audioResult = $this->bot->sendAudio($chatId, $localFile, $audioCaption, 'Markdown');
                    } else {
                        $audioResult = $this->bot->sendAudio($chatId, $audioUrl, $audioCaption, 'Markdown');
                    }

                    if ($audioResult['ok'] ?? false) {
                        $this->recordMessage($audioResult, 'audio');
                    } else {
                        $this->sendAudioLinkFallback($chatId, $audioCaption, $audioUrl, $audioResult['description'] ?? null);
                    }
                } finally {
                    if ($localFile && file_exists($localFile)) {
                        @unlink($localFile);
                    }
                }
            }
        } elseif ($this->isAudioRequested() && empty($audios) && !empty($videos)) {
            // If audio requested but not available, inform user and share video link instead of sending video directly
            $fallbackVideo = $videos[0];
            $quality = $fallbackVideo['quality'] ?? 'unknown';
            $size = $this->formatFileSize($fallbackVideo['data_size'] ?? 0);
            $message = "⚠️ Audio format not available. Providing video link instead:\n\n";
            $message .= "[{$quality} - {$size}]({$fallbackVideo['url']})";
            $fallback = $this->bot->sendMessage($chatId, $message, 'Markdown');
            $this->recordMessage($fallback, 'text');
        }

        // Send images if available
        if (!empty($images)) {
            foreach ($images as $idx => $image) {
                if ($idx >= 3) {
                    // Limit to 3 images to avoid spam
                    $remaining = count($images) - 3;
                    $moreMsg = $this->bot->sendMessage($chatId, "...and {$remaining} more images");
                    $this->recordMessage($moreMsg, 'text');
                    break;
                }

                $photoResult = $this->bot->sendPhoto($chatId, $image['url']);
                $this->recordMessage($photoResult, 'photo');

                if (!($photoResult['ok'] ?? false)) {
                    $fallback = $this->bot->sendMessage($chatId, "📥 Image: " . $image['url']);
                    $this->recordMessage($fallback, 'text');
                }

                // Small delay to avoid rate limits
                if ($idx < count($images) - 1) {
                    usleep(500000); // 0.5 second
                }
            }
        }
    }

    /**
     * Handle default/unknown response
     */
    private function handleDefault($chatId, $result) {
        $caption = $this->buildCaption($result);

        $medias = $result['medias'] ?? [];

        if (empty($medias)) {
            $this->bot->sendMessage($chatId, $caption . "\n\n❌ No downloadable media found");
            return;
        }

        $message = $caption . "\n\n📥 **Download Links:**\n\n";

        foreach ($medias as $idx => $media) {
            $url = $media['url'] ?? '';
            $type = $media['type'] ?? 'unknown';
            $quality = $media['quality'] ?? '';

            if ($url) {
                $message .= ($idx + 1) . ". [{$type}" . ($quality ? " - {$quality}" : "") . "]({$url})\n";
            }
        }

        $response = $this->bot->sendMessage($chatId, $message, 'Markdown');
        $this->recordMessage($response, 'text');
    }

    private function recordMessage($response, $type, array $extra = []) {
        if (!is_array($response) || !($response['ok'] ?? false)) {
            return null;
        }

        $result = $response['result'] ?? [];
        $messageId = $result['message_id'] ?? null;

        if (!$messageId) {
            return null;
        }

        $messageData = [
            'message_id' => $messageId,
            'type' => $extra['type'] ?? $type,
            'file_id' => $extra['file_id'] ?? $this->extractFileId($result, $type)
        ];

        $this->sentMessages[] = $messageData;

        return $messageData;
    }

    private function extractFileId(array $message, $type) {
        switch ($type) {
            case 'video':
                return $message['video']['file_id'] ?? null;
            case 'audio':
                return $message['audio']['file_id'] ?? null;
            case 'photo':
            case 'image':
                if (!empty($message['photo']) && is_array($message['photo'])) {
                    $photos = $message['photo'];
                    $last = end($photos);
                    return $last['file_id'] ?? null;
                }
                return null;
            default:
                return null;
        }
    }

    private function buildDownloadInfo(array $result) {
        $info = [
            'platform' => $result['source'] ?? ($this->downloadContext['platform'] ?? 'unknown'),
            'title' => $result['title'] ?? ($this->downloadContext['title'] ?? null),
            'url' => $this->downloadContext['url'] ?? ($result['url'] ?? null),
            'type' => $result['type'] ?? ($this->downloadContext['type'] ?? null),
            'thumbnail' => $result['thumbnail'] ?? ($this->downloadContext['thumbnail'] ?? null),
            'author' => $result['author'] ?? ($this->downloadContext['author'] ?? null)
        ];

        if (empty($info['url'])) {
            $primaryMedia = $result['medias'][0]['url'] ?? null;
            if ($primaryMedia) {
                $info['url'] = $primaryMedia;
            }
        }

        return $info;
    }

    /**
     * Build caption from result data
     */
    private function buildCaption($result) {
        $lines = [];

        // Title
        if (!empty($result['title'])) {
            $title = $this->truncate($result['title'], 100);
            $lines[] = "📝 **Title:** {$title}";
        }

        // Author
        if (!empty($result['author'])) {
            $lines[] = "👤 **Author:** @{$result['author']}";
        }

        // Source
        if (!empty($result['source'])) {
            $source = ucfirst($result['source']);
            $lines[] = "🌐 **Source:** {$source}";
        }

        // Duration (for videos/audio)
        if (!empty($result['duration'])) {
            $duration = gmdate("i:s", $result['duration']);
            $lines[] = "⏱ **Duration:** {$duration}";
        }

        // Thumbnail
        if (!empty($result['thumbnail'])) {
            // Don't include thumbnail in caption, it's too long
            // $lines[] = "🖼 [Thumbnail](" . $result['thumbnail'] . ")";
        }

        if (empty($lines)) {
            return "✅ **Download Ready**";
        }

        return implode("\n", $lines);
    }

    /**
     * Format file size
     */
    private function formatFileSize($bytes) {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log(1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Truncate text
     */
    private function truncate($text, $length = 100) {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * Determine if user requested audio format explicitly
     */
    private function isAudioRequested() {
        if (!$this->requestedFormat) {
            return false;
        }

        $format = strtolower($this->requestedFormat);
        $audioFormats = ['ytmp3', 'mp3', 'audio', 'spotify'];

        return in_array($format, $audioFormats, true);
    }

    /**
     * Download media to temporary file for uploading to Telegram
     */
    private function downloadMediaToTemp($url, $prefix = 'media_', $extension = '.tmp') {
        $tempDir = __DIR__ . '/../../temp';

        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $tempPath = tempnam($tempDir, $prefix);
        if ($tempPath === false) {
            return null;
        }

        if ($extension) {
            $newPath = $tempPath . $extension;
            if (!@rename($tempPath, $newPath)) {
                $newPath = $tempPath; // Fallback
            }
            $tempPath = $newPath;
        }

        $fp = fopen($tempPath, 'wb');
        if (!$fp) {
            @unlink($tempPath);
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_REFERER => 'https://www.youtube.com/'
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400) {
            Logger::warning('Failed downloading media to temp', [
                'url' => $url,
                'http_code' => $httpCode,
                'error' => $error
            ]);
            @unlink($tempPath);
            return null;
        }

        return $tempPath;
    }

    /**
     * Guess file extension based on URL hints
     */
    private function guessExtension($url, $default = '.tmp') {
        $parsed = parse_url($url);
        if (!empty($parsed['path'])) {
            $ext = pathinfo($parsed['path'], PATHINFO_EXTENSION);
            if ($ext) {
                return '.' . strtolower($ext);
            }
        }

        if (stripos($url, 'audio%2Fmp4') !== false || stripos($url, 'mime=audio/mp4') !== false) {
            return '.m4a';
        }

        if (stripos($url, '.mp3') !== false) {
            return '.mp3';
        }

        return $default;
    }

    /**
     * Send fallback message with audio link
     */
    private function sendAudioLinkFallback($chatId, $caption, $audioUrl, $errorDescription = null) {
        $message = $caption . "\n\n📥 **Audio Download:**\n" . $audioUrl;

        if ($errorDescription) {
            $message .= "\n\n⚠️ _" . $errorDescription . "_";
        }

        $response = $this->bot->sendMessage($chatId, $message, 'Markdown');
        $this->recordMessage($response, 'text');

        return $response;
    }
}
