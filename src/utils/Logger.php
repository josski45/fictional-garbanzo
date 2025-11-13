<?php

namespace JosskiTools\Utils;

/**
 * Logger - Central logging system for errors and events
 */
class Logger {

    private static $logsDir;

    /**
     * Initialize logger
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
     * Log error message
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }

    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Log API request
     */
    public static function apiRequest($api, $endpoint, $params = []) {
        self::log('API_REQUEST', "{$api} - {$endpoint}", $params);
    }

    /**
     * Log API response
     */
    public static function apiResponse($api, $success, $data = []) {
        $level = $success ? 'API_SUCCESS' : 'API_ERROR';
        self::log($level, $api, $data);
    }

    /**
     * Log exception
     */
    public static function exception(\Exception $e, $context = []) {
        $message = sprintf(
            "Exception: %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        self::log('EXCEPTION', $message, $context);
    }

    /**
     * Core logging function
     */
    private static function log($level, $message, $context = []) {
        $logsDir = self::getLogsDir();
        $date = date('Y-m-d');
        $logFile = "{$logsDir}/app-{$date}.log";

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also log to PHP error log for critical errors
        if (in_array($level, ['ERROR', 'EXCEPTION'])) {
            error_log("[JosskiTools] [{$level}] {$message}");
        }
    }

    /**
     * Clean old log files (older than 30 days)
     */
    public static function cleanup($days = 30) {
        $logsDir = self::getLogsDir();
        $cutoffTime = time() - ($days * 86400);

        $files = glob("{$logsDir}/app-*.log");
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Get log file path for today
     */
    public static function getTodayLogFile() {
        $logsDir = self::getLogsDir();
        $date = date('Y-m-d');
        return "{$logsDir}/app-{$date}.log";
    }
}
