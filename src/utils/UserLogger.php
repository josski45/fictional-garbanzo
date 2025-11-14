<?php

namespace JosskiTools\Utils;

/**
 * UserLogger - Log user activities per user ID
 */
class UserLogger {

    private static $logsDir;

    /**
     * Initialize user logger
     */
    public static function init($logsDir = null) {
        if ($logsDir === null) {
            self::$logsDir = __DIR__ . '/../../logs';
        } else {
            self::$logsDir = $logsDir;
        }

        // Create logs directory if not exists
        if (!is_dir(self::$logsDir)) {
            mkdir(self::$logsDir, 0777, true);
        }
    }

    /**
     * Get logs directory
     */
    private static function getLogsDir() {
        if (self::$logsDir === null) {
            self::init();
        }
        return self::$logsDir;
    }

    /**
     * Log user activity
     */
    public static function log($userId, $action, $details = []) {
        $logsDir = self::getLogsDir();
        $logFile = "{$logsDir}/{$userId}.txt";

        $timestamp = date('Y-m-d H:i:s');
        $detailsStr = !empty($details) ? ' | ' . json_encode($details, JSON_UNESCAPED_UNICODE) : '';

        $logEntry = "[{$timestamp}] {$action}{$detailsStr}\n";

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log command usage
     */
    public static function logCommand($userId, $command, $params = []) {
        self::log($userId, "COMMAND: {$command}", $params);
    }

    /**
     * Log download request
     */
    public static function logDownload($userId, $platform, $url) {
        self::log($userId, "DOWNLOAD", [
            'platform' => $platform,
            'url' => $url
        ]);
    }

    /**
     * Log error encountered by user
     */
    public static function logError($userId, $error, $context = []) {
        self::log($userId, "ERROR: {$error}", $context);
    }

    /**
     * Log HAR extraction
     */
    public static function logHarExtraction($userId, $action, $data = []) {
        self::log($userId, "HAR: {$action}", $data);
    }

    /**
     * Get user log file path
     */
    public static function getUserLogFile($userId) {
        $logsDir = self::getLogsDir();
        return "{$logsDir}/{$userId}.txt";
    }

    /**
     * Get user activity count
     */
    public static function getUserActivityCount($userId) {
        $logFile = self::getUserLogFile($userId);

        if (!file_exists($logFile)) {
            return 0;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return count($lines);
    }

    /**
     * Get recent user activity (last N lines)
     */
    public static function getRecentActivity($userId, $limit = 10) {
        $logFile = self::getUserLogFile($userId);

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($lines, -$limit);
    }

    /**
     * Clean old user logs (older than 90 days)
     */
    public static function cleanup($days = 90) {
        $logsDir = self::getLogsDir();
        $cutoffTime = time() - ($days * 86400);

        $files = glob("{$logsDir}/*.txt");
        $deleted = 0;

        foreach ($files as $file) {
            // Skip app logs
            if (strpos(basename($file), 'app-') === 0) {
                continue;
            }

            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
