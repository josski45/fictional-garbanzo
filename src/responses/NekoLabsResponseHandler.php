<?php

namespace JosskiTools\Responses;

use JosskiTools\Utils\Logger;

/**
 * NekoLabs Response Handler - Handle responses from NekoLabs API
 */
class NekoLabsResponseHandler {

    private $bot;

    public function __construct($bot) {
        $this->bot = $bot;
    }

    /**
     * Handle response from NekoLabs API
     */
    public function handle($chatId, $result, $loadingMsgId = null) {
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
        }
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

        if (!($videoResult['ok'] ?? false)) {
            Logger::warning("Failed to send video, sending as text", [
                'chat_id' => $chatId,
                'error' => $videoResult['description'] ?? 'Unknown'
            ]);

            // Fallback: send as text with download link
            $message = $caption . "\n\n📥 **Download:**\n" . $videoUrl;
            $this->bot->sendMessage($chatId, $message, 'Markdown');
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

        // Try to send audio
        $audioResult = $this->bot->sendAudio($chatId, $audioUrl, $caption, 'Markdown');

        if (!($audioResult['ok'] ?? false)) {
            Logger::warning("Failed to send audio, sending as text", [
                'chat_id' => $chatId,
                'error' => $audioResult['description'] ?? 'Unknown'
            ]);

            // Fallback: send as text with download link
            $message = $caption . "\n\n📥 **Download:**\n" . $audioUrl;
            $this->bot->sendMessage($chatId, $message, 'Markdown');
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

        if (!($photoResult['ok'] ?? false)) {
            Logger::warning("Failed to send photo, sending as text", [
                'chat_id' => $chatId,
                'error' => $photoResult['description'] ?? 'Unknown'
            ]);

            // Fallback: send as text with download link
            $message = $caption . "\n\n📥 **Download:**\n" . $imageUrl;
            $this->bot->sendMessage($chatId, $message, 'Markdown');
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
        $this->bot->sendMessage($chatId, $caption, 'Markdown');

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
        if (!empty($videos)) {
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

            if (!($videoResult['ok'] ?? false)) {
                // Fallback: send all video links
                $message = "📥 **Video Downloads:**\n\n";
                foreach ($videos as $idx => $video) {
                    $quality = $video['quality'] ?? 'unknown';
                    $size = $this->formatFileSize($video['data_size'] ?? 0);
                    $message .= ($idx + 1) . ". [{$quality} - {$size}](" . $video['url'] . ")\n";
                }
                $this->bot->sendMessage($chatId, $message, 'Markdown');
            }
        }

        // Send audio if available
        if (!empty($audios)) {
            $audio = $audios[0];
            $duration = $audio['duration'] ?? 0;
            $audioCaption = "🎵 **Duration:** " . gmdate("i:s", $duration);

            $audioResult = $this->bot->sendAudio($chatId, $audio['url'], $audioCaption, 'Markdown');

            if (!($audioResult['ok'] ?? false)) {
                $message = "📥 **Audio Download:**\n" . $audio['url'];
                $this->bot->sendMessage($chatId, $message);
            }
        }

        // Send images if available
        if (!empty($images)) {
            foreach ($images as $idx => $image) {
                if ($idx >= 3) {
                    // Limit to 3 images to avoid spam
                    $remaining = count($images) - 3;
                    $this->bot->sendMessage($chatId, "...and {$remaining} more images");
                    break;
                }

                $photoResult = $this->bot->sendPhoto($chatId, $image['url']);

                if (!($photoResult['ok'] ?? false)) {
                    $this->bot->sendMessage($chatId, "📥 Image: " . $image['url']);
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

        $this->bot->sendMessage($chatId, $message, 'Markdown');
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
}
