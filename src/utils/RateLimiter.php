<?php

namespace JosskiTools\Utils;

/**
 * RateLimiter - Anti-spam and rate limiting system
 */
class RateLimiter {

    private static $dataDir;
    private static $limitsFile;

    /**
     * Rate limit configurations
     */
    private static $limits = [
        'requests_per_minute' => 10,      // Max 10 requests per minute
        'requests_per_hour' => 100,       // Max 100 requests per hour
        'requests_per_day' => 500,        // Max 500 requests per day
        'ban_threshold' => 3,             // Ban after 3 violations
        'ban_duration' => 3600,           // Ban for 1 hour (seconds)
        'temp_ban_duration' => 300,       // Temp ban for 5 minutes
        'cooldown_period' => 2,           // 2 seconds between requests
    ];

    /**
     * Initialize rate limiter
     */
    public static function init($dataDir = null) {
        if ($dataDir === null) {
            self::$dataDir = __DIR__ . '/../../data';
        } else {
            self::$dataDir = $dataDir;
        }

        self::$limitsFile = self::$dataDir . '/rate_limits.json';

        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }

        if (!file_exists(self::$limitsFile)) {
            file_put_contents(self::$limitsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Load rate limit data with file lock
     * Returns array: [data, fileHandle] - Remember to close the handle after use!
     */
    private static function loadDataWithLock() {
        self::init();

        // Open file for reading and writing
        $fp = fopen(self::$limitsFile, 'c+');
        if (!$fp) {
            throw new \RuntimeException("Cannot open rate limits file");
        }

        // Acquire exclusive lock for read-modify-write operation
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new \RuntimeException("Cannot acquire file lock");
        }

        // Read file content
        $content = stream_get_contents($fp);
        $data = json_decode($content, true);

        // Return data and file handle (caller must unlock and close)
        return [is_array($data) ? $data : [], $fp];
    }

    /**
     * Save rate limit data and release lock
     */
    private static function saveDataAndUnlock($data, $fp) {
        // Truncate file and write new data
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));

        // Release lock and close file
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * Legacy method for backward compatibility
     */
    private static function loadData() {
        self::init();
        $content = file_get_contents(self::$limitsFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Legacy method for backward compatibility
     */
    private static function saveData($data) {
        file_put_contents(self::$limitsFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Check if user is rate limited
     * Returns: ['allowed' => bool, 'reason' => string, 'retry_after' => int]
     */
    public static function check($userId) {
        // Use file locking to prevent race conditions
        list($data, $fp) = self::loadDataWithLock();
        $now = time();

        try {

        // Initialize user data if not exists
        if (!isset($data[$userId])) {
            $data[$userId] = [
                'requests' => [],
                'violations' => 0,
                'banned_until' => 0,
                'temp_banned_until' => 0,
                'last_request' => 0
            ];
        }

        $user = &$data[$userId];

        // Check permanent ban
        if ($user['banned_until'] > $now) {
            Logger::warning("User is banned", [
                'user_id' => $userId,
                'banned_until' => date('Y-m-d H:i:s', $user['banned_until'])
            ]);

            // Release lock before returning
            flock($fp, LOCK_UN);
            fclose($fp);

            return [
                'allowed' => false,
                'reason' => 'banned',
                'retry_after' => $user['banned_until'] - $now,
                'message' => "⛔ You are temporarily banned until " . date('H:i:s', $user['banned_until'])
            ];
        }

        // Check temp ban
        if ($user['temp_banned_until'] > $now) {
            // Release lock before returning
            flock($fp, LOCK_UN);
            fclose($fp);

            return [
                'allowed' => false,
                'reason' => 'temp_banned',
                'retry_after' => $user['temp_banned_until'] - $now,
                'message' => "⏳ Slow down! Try again in " . ($user['temp_banned_until'] - $now) . " seconds"
            ];
        }

        // Check cooldown
        if ($user['last_request'] > 0) {
            $timeSinceLastRequest = $now - $user['last_request'];
            if ($timeSinceLastRequest < self::$limits['cooldown_period']) {
                // Release lock before returning
                flock($fp, LOCK_UN);
                fclose($fp);

                return [
                    'allowed' => false,
                    'reason' => 'cooldown',
                    'retry_after' => self::$limits['cooldown_period'] - $timeSinceLastRequest,
                    'message' => "⏱️ Please wait " . (self::$limits['cooldown_period'] - $timeSinceLastRequest) . " seconds"
                ];
            }
        }

        // Clean old requests (older than 1 day)
        $user['requests'] = array_filter($user['requests'], function($timestamp) use ($now) {
            return $timestamp > ($now - 86400);
        });

        // Count requests in different time windows
        $requestsLastMinute = 0;
        $requestsLastHour = 0;
        $requestsLastDay = count($user['requests']);

        foreach ($user['requests'] as $timestamp) {
            if ($timestamp > ($now - 60)) {
                $requestsLastMinute++;
            }
            if ($timestamp > ($now - 3600)) {
                $requestsLastHour++;
            }
        }

        // Check limits
        if ($requestsLastMinute >= self::$limits['requests_per_minute']) {
            $user['violations']++;
            self::handleViolation($user, $userId, 'minute');
            self::saveDataAndUnlock($data, $fp);

            return [
                'allowed' => false,
                'reason' => 'rate_limit_minute',
                'retry_after' => 60,
                'message' => "⚠️ Rate limit: Max " . self::$limits['requests_per_minute'] . " requests per minute"
            ];
        }

        if ($requestsLastHour >= self::$limits['requests_per_hour']) {
            $user['violations']++;
            self::handleViolation($user, $userId, 'hour');
            self::saveDataAndUnlock($data, $fp);

            return [
                'allowed' => false,
                'reason' => 'rate_limit_hour',
                'retry_after' => 3600,
                'message' => "⚠️ Rate limit: Max " . self::$limits['requests_per_hour'] . " requests per hour"
            ];
        }

        if ($requestsLastDay >= self::$limits['requests_per_day']) {
            $user['violations']++;
            self::handleViolation($user, $userId, 'day');
            self::saveDataAndUnlock($data, $fp);

            return [
                'allowed' => false,
                'reason' => 'rate_limit_day',
                'retry_after' => 86400,
                'message' => "⚠️ Daily limit reached: Max " . self::$limits['requests_per_day'] . " requests per day"
            ];
        }

        // All checks passed - record this request
        $user['requests'][] = $now;
        $user['last_request'] = $now;
        self::saveDataAndUnlock($data, $fp);

        return [
            'allowed' => true,
            'reason' => null,
            'retry_after' => 0,
            'message' => null
        ];

        } catch (\Exception $e) {
            // Ensure lock is released even if an error occurs
            if (isset($fp) && is_resource($fp)) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            throw $e;
        }
    }

    /**
     * Handle violation
     */
    private static function handleViolation(&$user, $userId, $type) {
        $now = time();

        Logger::warning("Rate limit violation", [
            'user_id' => $userId,
            'type' => $type,
            'violations' => $user['violations']
        ]);

        // Progressive punishment
        if ($user['violations'] >= self::$limits['ban_threshold']) {
            // Ban user
            $user['banned_until'] = $now + self::$limits['ban_duration'];

            Logger::error("User banned for excessive violations", [
                'user_id' => $userId,
                'banned_until' => date('Y-m-d H:i:s', $user['banned_until'])
            ]);

            // Notify admin
            self::notifyAdmin($userId, 'banned');

        } else {
            // Temp ban
            $user['temp_banned_until'] = $now + self::$limits['temp_ban_duration'];
        }
    }

    /**
     * Notify admin about ban
     */
    private static function notifyAdmin($userId, $action) {
        // This will be called by admin notification system
        Logger::info("Admin notification queued", [
            'user_id' => $userId,
            'action' => $action
        ]);
    }

    /**
     * Reset user violations (admin action)
     */
    public static function resetUser($userId) {
        $data = self::loadData();

        if (isset($data[$userId])) {
            $data[$userId]['violations'] = 0;
            $data[$userId]['banned_until'] = 0;
            $data[$userId]['temp_banned_until'] = 0;
            self::saveData($data);

            Logger::info("User rate limits reset", ['user_id' => $userId]);
            return true;
        }

        return false;
    }

    /**
     * Unban user (admin action)
     */
    public static function unbanUser($userId) {
        $data = self::loadData();

        if (isset($data[$userId])) {
            $data[$userId]['banned_until'] = 0;
            $data[$userId]['temp_banned_until'] = 0;
            $data[$userId]['violations'] = 0;
            self::saveData($data);

            Logger::info("User unbanned", ['user_id' => $userId]);
            return true;
        }

        return false;
    }

    /**
     * Get user rate limit info
     */
    public static function getUserInfo($userId) {
        $data = self::loadData();

        if (!isset($data[$userId])) {
            return [
                'requests_today' => 0,
                'violations' => 0,
                'is_banned' => false,
                'banned_until' => null
            ];
        }

        $user = $data[$userId];
        $now = time();

        // Count requests today
        $requestsToday = 0;
        foreach ($user['requests'] as $timestamp) {
            if ($timestamp > ($now - 86400)) {
                $requestsToday++;
            }
        }

        return [
            'requests_today' => $requestsToday,
            'violations' => $user['violations'],
            'is_banned' => $user['banned_until'] > $now,
            'banned_until' => $user['banned_until'] > $now ? date('Y-m-d H:i:s', $user['banned_until']) : null
        ];
    }

    /**
     * Get all banned users
     */
    public static function getBannedUsers() {
        $data = self::loadData();
        $now = time();
        $banned = [];

        foreach ($data as $userId => $user) {
            if ($user['banned_until'] > $now) {
                $banned[$userId] = [
                    'violations' => $user['violations'],
                    'banned_until' => date('Y-m-d H:i:s', $user['banned_until'])
                ];
            }
        }

        return $banned;
    }

    /**
     * Detect spam patterns
     */
    public static function detectSpamPattern($userId, $message) {
        // Common spam patterns
        $spamPatterns = [
            '/(.)\1{10,}/',                    // Repeated characters
            '/https?:\/\/bit\.ly/i',           // Short URL services (often spam)
            '/https?:\/\/tinyurl/i',
            '/https?:\/\/goo\.gl/i',
            '/(buy|cheap|discount|offer|promo|click here|subscribe)/i',  // Spam keywords
            '/(\$\d+|\d+\$)/i',                // Money signs
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                Logger::warning("Spam pattern detected", [
                    'user_id' => $userId,
                    'pattern' => $pattern,
                    'message' => substr($message, 0, 100)
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Set custom limits (admin)
     */
    public static function setLimits($newLimits) {
        self::$limits = array_merge(self::$limits, $newLimits);
    }

    /**
     * Get current limits
     */
    public static function getLimits() {
        return self::$limits;
    }
}
