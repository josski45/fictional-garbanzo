<?php

namespace JosskiTools\Utils;

/**
 * DownloadHistory - Track user download history and favorites
 */
class DownloadHistory {

    private static $dataDir;
    private static $historyFile;
    private static $favoritesFile;

    /**
     * Initialize download history
     */
    public static function init($dataDir = null) {
        if ($dataDir === null) {
            self::$dataDir = __DIR__ . '/../../data';
        } else {
            self::$dataDir = $dataDir;
        }

        self::$historyFile = self::$dataDir . '/download_history.json';
        self::$favoritesFile = self::$dataDir . '/favorites.json';

        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }

        if (!file_exists(self::$historyFile)) {
            file_put_contents(self::$historyFile, json_encode([], JSON_PRETTY_PRINT));
        }

        if (!file_exists(self::$favoritesFile)) {
            file_put_contents(self::$favoritesFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Load history data
     */
    private static function loadHistory() {
        self::init();
        $content = file_get_contents(self::$historyFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Load favorites data
     */
    private static function loadFavorites() {
        self::init();
        $content = file_get_contents(self::$favoritesFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save history data
     */
    private static function saveHistory($data) {
        file_put_contents(self::$historyFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Save favorites data
     */
    private static function saveFavorites($data) {
        file_put_contents(self::$favoritesFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Add download to history
     */
    public static function addDownload($userId, $url, $platform, $title = null, $mediaType = null) {
        $history = self::loadHistory();

        if (!isset($history[$userId])) {
            $history[$userId] = [];
        }

        $download = [
            'url' => $url,
            'platform' => $platform,
            'title' => $title,
            'media_type' => $mediaType,
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s')
        ];

        // Add to beginning of array
        array_unshift($history[$userId], $download);

        // Limit to last 100 downloads per user
        $history[$userId] = array_slice($history[$userId], 0, 100);

        self::saveHistory($history);

        Logger::debug("Download added to history", [
            'user_id' => $userId,
            'platform' => $platform
        ]);
    }

    /**
     * Get user download history
     */
    public static function getHistory($userId, $limit = 20) {
        $history = self::loadHistory();

        if (!isset($history[$userId])) {
            return [];
        }

        return array_slice($history[$userId], 0, $limit);
    }

    /**
     * Search history
     */
    public static function searchHistory($userId, $query) {
        $history = self::loadHistory();

        if (!isset($history[$userId])) {
            return [];
        }

        $query = strtolower($query);
        $results = [];

        foreach ($history[$userId] as $download) {
            $searchText = strtolower(($download['title'] ?? '') . ' ' . ($download['platform'] ?? '') . ' ' . ($download['url'] ?? ''));

            if (strpos($searchText, $query) !== false) {
                $results[] = $download;
            }

            if (count($results) >= 10) {
                break;
            }
        }

        return $results;
    }

    /**
     * Clear user history
     */
    public static function clearHistory($userId) {
        $history = self::loadHistory();

        if (isset($history[$userId])) {
            $count = count($history[$userId]);
            unset($history[$userId]);
            self::saveHistory($history);

            Logger::info("User history cleared", [
                'user_id' => $userId,
                'items_removed' => $count
            ]);

            return $count;
        }

        return 0;
    }

    /**
     * Add to favorites
     */
    public static function addFavorite($userId, $url, $platform, $title = null, $thumbnail = null) {
        $favorites = self::loadFavorites();

        if (!isset($favorites[$userId])) {
            $favorites[$userId] = [];
        }

        // Check if already in favorites
        foreach ($favorites[$userId] as $fav) {
            if ($fav['url'] === $url) {
                return ['success' => false, 'message' => 'Already in favorites'];
            }
        }

        $favorite = [
            'url' => $url,
            'platform' => $platform,
            'title' => $title,
            'thumbnail' => $thumbnail,
            'added_at' => time(),
            'date' => date('Y-m-d H:i:s')
        ];

        $favorites[$userId][] = $favorite;

        // Limit to 50 favorites per user
        if (count($favorites[$userId]) > 50) {
            array_shift($favorites[$userId]);
        }

        self::saveFavorites($favorites);

        Logger::info("Added to favorites", [
            'user_id' => $userId,
            'platform' => $platform
        ]);

        UserLogger::log($userId, "Added to favorites", ['url' => $url, 'platform' => $platform]);

        return ['success' => true, 'message' => '⭐ Added to favorites'];
    }

    /**
     * Remove from favorites
     */
    public static function removeFavorite($userId, $url) {
        $favorites = self::loadFavorites();

        if (!isset($favorites[$userId])) {
            return ['success' => false, 'message' => 'No favorites found'];
        }

        $originalCount = count($favorites[$userId]);

        $favorites[$userId] = array_filter($favorites[$userId], function($fav) use ($url) {
            return $fav['url'] !== $url;
        });

        // Re-index array
        $favorites[$userId] = array_values($favorites[$userId]);

        if (count($favorites[$userId]) < $originalCount) {
            self::saveFavorites($favorites);

            Logger::info("Removed from favorites", [
                'user_id' => $userId,
                'url' => $url
            ]);

            return ['success' => true, 'message' => '❌ Removed from favorites'];
        }

        return ['success' => false, 'message' => 'Not found in favorites'];
    }

    /**
     * Get user favorites
     */
    public static function getFavorites($userId) {
        $favorites = self::loadFavorites();

        if (!isset($favorites[$userId])) {
            return [];
        }

        return $favorites[$userId];
    }

    /**
     * Check if URL is in favorites
     */
    public static function isFavorite($userId, $url) {
        $favorites = self::loadFavorites();

        if (!isset($favorites[$userId])) {
            return false;
        }

        foreach ($favorites[$userId] as $fav) {
            if ($fav['url'] === $url) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get statistics
     */
    public static function getStats($userId) {
        $history = self::loadHistory();
        $favorites = self::loadFavorites();

        $historyItems = $history[$userId] ?? [];
        $favoriteItems = $favorites[$userId] ?? [];

        // Platform breakdown
        $platformStats = [];
        foreach ($historyItems as $item) {
            $platform = $item['platform'] ?? 'unknown';
            if (!isset($platformStats[$platform])) {
                $platformStats[$platform] = 0;
            }
            $platformStats[$platform]++;
        }

        // Sort by count
        arsort($platformStats);

        // Get recent activity (last 7 days)
        $sevenDaysAgo = time() - (7 * 86400);
        $recentCount = 0;

        foreach ($historyItems as $item) {
            if (($item['timestamp'] ?? 0) > $sevenDaysAgo) {
                $recentCount++;
            }
        }

        return [
            'total_downloads' => count($historyItems),
            'total_favorites' => count($favoriteItems),
            'downloads_this_week' => $recentCount,
            'platform_breakdown' => $platformStats,
            'most_used_platform' => !empty($platformStats) ? array_key_first($platformStats) : 'none'
        ];
    }

    /**
     * Export history to text
     */
    public static function exportHistory($userId) {
        $history = self::getHistory($userId, 100);

        if (empty($history)) {
            return null;
        }

        $text = "📊 Download History Export\n";
        $text .= "User ID: {$userId}\n";
        $text .= "Export Date: " . date('Y-m-d H:i:s') . "\n";
        $text .= "Total Downloads: " . count($history) . "\n\n";
        $text .= str_repeat('=', 50) . "\n\n";

        foreach ($history as $idx => $item) {
            $num = $idx + 1;
            $text .= "{$num}. [{$item['platform']}] {$item['title']}\n";
            $text .= "   URL: {$item['url']}\n";
            $text .= "   Date: {$item['date']}\n";
            $text .= "\n";
        }

        return $text;
    }

    /**
     * Get global statistics (all users)
     */
    public static function getGlobalStats() {
        $history = self::loadHistory();
        $favorites = self::loadFavorites();

        $totalDownloads = 0;
        $totalFavorites = 0;
        $platformStats = [];
        $activeUsers = 0;

        // History stats
        foreach ($history as $userId => $userHistory) {
            if (!empty($userHistory)) {
                $activeUsers++;
                $totalDownloads += count($userHistory);

                foreach ($userHistory as $item) {
                    $platform = $item['platform'] ?? 'unknown';
                    if (!isset($platformStats[$platform])) {
                        $platformStats[$platform] = 0;
                    }
                    $platformStats[$platform]++;
                }
            }
        }

        // Favorites stats
        foreach ($favorites as $userFavorites) {
            $totalFavorites += count($userFavorites);
        }

        arsort($platformStats);

        return [
            'total_downloads' => $totalDownloads,
            'total_favorites' => $totalFavorites,
            'active_users' => $activeUsers,
            'platform_breakdown' => $platformStats,
            'most_popular_platform' => !empty($platformStats) ? array_key_first($platformStats) : 'none'
        ];
    }

    /**
     * Cleanup old history (older than 90 days)
     */
    public static function cleanup($days = 90) {
        $history = self::loadHistory();
        $cutoffTime = time() - ($days * 86400);
        $removed = 0;

        foreach ($history as $userId => &$userHistory) {
            $originalCount = count($userHistory);

            $userHistory = array_filter($userHistory, function($item) use ($cutoffTime) {
                return ($item['timestamp'] ?? 0) > $cutoffTime;
            });

            // Re-index
            $userHistory = array_values($userHistory);

            $removed += $originalCount - count($userHistory);
        }

        self::saveHistory($history);

        Logger::info("Download history cleaned", [
            'days' => $days,
            'items_removed' => $removed
        ]);

        return $removed;
    }
}
