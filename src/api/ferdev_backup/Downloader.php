<?php

namespace JosskiTools\Api\Ferdev;

require_once __DIR__ . '/FerdevClient.php';

/**
 * Ferdev Downloader API
 * Support: TikTok, Instagram, Facebook, YouTube, dan banyak lagi
 */
class Downloader extends \JosskiTools\Api\FerdevClient {
    
    /**
     * Capcut Downloader
     */
    public function capcut($url) {
        return $this->request('/downloader/capcut', ['link' => $url]);
    }
    
    /**
     * Douyin Downloader
     */
    public function douyin($url) {
        return $this->request('/downloader/douyin', ['link' => $url]);
    }
    
    /**
     * Facebook Downloader
     */
    public function facebook($url) {
        return $this->request('/downloader/facebook', ['link' => $url]);
    }
    
    /**
     * GetModsApk Downloader
     */
    public function getmodsapk($url) {
        return $this->request('/downloader/getmodsapk', ['link' => $url]);
    }
    
    /**
     * FDroid Downloader
     */
    public function fdroid($packageName) {
        return $this->request('/downloader/fdroid', ['package' => $packageName]);
    }
    
    /**
     * Google Drive Downloader
     */
    public function gdrive($url) {
        return $this->request('/downloader/gdrive', ['link' => $url]);
    }
    
    /**
     * GitHub Downloader
     */
    public function github($url) {
        return $this->request('/downloader/github', ['link' => $url]);
    }
    
    /**
     * Instagram Story Downloader
     */
    public function igstory($username) {
        return $this->request('/downloader/igstory', ['username' => $username]);
    }
    
    /**
     * Instagram Downloader
     */
    public function instagram($url) {
        return $this->request('/downloader/instagram', ['link' => $url]);
    }
    
    /**
     * MediaFire Downloader
     */
    public function mediafire($url) {
        return $this->request('/downloader/mediafire', ['link' => $url]);
    }
    
    /**
     * Pinterest Downloader
     */
    public function pinterest($url) {
        return $this->request('/downloader/pinterest', ['link' => $url]);
    }
    
    /**
     * Pixeldrain Downloader
     */
    public function pixeldrain($url) {
        return $this->request('/downloader/pixeldrain', ['link' => $url]);
    }
    
    /**
     * Spotify Downloader
     */
    public function spotify($url) {
        return $this->request('/downloader/spotify', ['link' => $url]);
    }
    
    /**
     * SnackVideo Downloader
     */
    public function snackvideo($url) {
        return $this->request('/downloader/snackvideo', ['link' => $url]);
    }
    
    /**
     * SoundCloud Downloader
     */
    public function soundcloud($url) {
        return $this->request('/downloader/soundcloud', ['link' => $url]);
    }
    
    /**
     * Terabox Downloader
     */
    public function terabox($url) {
        return $this->request('/downloader/terabox', ['link' => $url]);
    }
    
    /**
     * TikTok Downloader
     */
    public function tiktok($url) {
        return $this->request('/downloader/tiktok', ['link' => $url]);
    }
    
    /**
     * Threads Downloader
     */
    public function threads($url) {
        return $this->request('/downloader/threads', ['link' => $url]);
    }
    
    /**
     * Twitter/X Downloader
     */
    public function twitter($url) {
        return $this->request('/downloader/twitter', ['link' => $url]);
    }
    
    /**
     * YouTube Shorts Downloader
     */
    public function ytshorts($url) {
        return $this->request('/downloader/ytshorts', ['link' => $url]);
    }
    
    /**
     * YouTube MP3 Downloader
     */
    public function ytmp3($url) {
        return $this->request('/downloader/ytmp3', ['link' => $url]);
    }
    
    /**
     * YouTube MP4 Downloader
     */
    public function ytmp4($url, $quality = '360p') {
        return $this->request('/downloader/ytmp4', [
            'link' => $url,
            'quality' => $quality
        ]);
    }
    
    /**
     * Download from any supported platform (auto detect)
     */
    public function auto($url) {
        // Detect platform and call appropriate method
        if (strpos($url, 'tiktok.com') !== false) {
            return $this->tiktok($url);
        } elseif (strpos($url, 'instagram.com') !== false) {
            return $this->instagram($url);
        } elseif (strpos($url, 'facebook.com') !== false || strpos($url, 'fb.watch') !== false) {
            return $this->facebook($url);
        } elseif (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            if (strpos($url, 'shorts') !== false) {
                return $this->ytshorts($url);
            }
            return $this->ytmp4($url);
        } elseif (strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false) {
            return $this->twitter($url);
        } elseif (strpos($url, 'spotify.com') !== false) {
            return $this->spotify($url);
        } elseif (strpos($url, 'pinterest.com') !== false) {
            return $this->pinterest($url);
        } elseif (strpos($url, 'mediafire.com') !== false) {
            return $this->mediafire($url);
        } elseif (strpos($url, 'drive.google.com') !== false) {
            return $this->gdrive($url);
        } elseif (strpos($url, 'github.com') !== false) {
            return $this->github($url);
        } elseif (strpos($url, 'capcut.com') !== false) {
            return $this->capcut($url);
        } elseif (strpos($url, 'douyin.com') !== false) {
            return $this->douyin($url);
        } elseif (strpos($url, 'threads.net') !== false) {
            return $this->threads($url);
        } elseif (strpos($url, 'soundcloud.com') !== false) {
            return $this->soundcloud($url);
        } elseif (strpos($url, 'terabox.com') !== false) {
            return $this->terabox($url);
        } elseif (strpos($url, 'snackvideo.com') !== false) {
            return $this->snackvideo($url);
        } elseif (strpos($url, 'pixeldrain.com') !== false) {
            return $this->pixeldrain($url);
        }
        
        return [
            'status' => false,
            'message' => 'Platform not supported or URL not recognized'
        ];
    }
}
