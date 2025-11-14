<?php

namespace JosskiTools\Utils;

use PDO;
use PDOException;

/**
 * Database Helper - SQLite database manager for bot data
 * 
 * Handles:
 * - Channel history entries
 * - Share links
 * - Message tracking
 */
class DatabaseHelper {
    private static $db = null;
    private static $dbPath = null;

    /**
     * Initialize database connection
     */
    public static function init($dataDir = null) {
        if ($dataDir === null) {
            $dataDir = __DIR__ . '/../../data';
        }

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        self::$dbPath = $dataDir . '/josski_bot.db';
        self::connect();
        self::createTables();
    }

    /**
     * Get database connection
     */
    private static function connect() {
        try {
            self::$db = new PDO('sqlite:' . self::$dbPath);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error("Database connection failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create database tables
     */
    private static function createTables() {
        $queries = [
            // History entries table (for channel history tokens)
            "CREATE TABLE IF NOT EXISTS history_entries (
                token TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                url TEXT NOT NULL,
                platform TEXT,
                title TEXT,
                thumbnail TEXT,
                author TEXT,
                channel_id TEXT,
                message_id INTEGER,
                created_at INTEGER NOT NULL,
                expires_at INTEGER
            )",

            // Share links table (for /share command)
            "CREATE TABLE IF NOT EXISTS share_links (
                token TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                message_id INTEGER NOT NULL,
                chat_id INTEGER NOT NULL,
                url TEXT NOT NULL,
                platform TEXT,
                title TEXT,
                file_id TEXT,
                file_type TEXT,
                created_at INTEGER NOT NULL,
                expires_at INTEGER
            )",

            // Sent messages tracking (for rememberMessages)
            "CREATE TABLE IF NOT EXISTS sent_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER NOT NULL,
                message_id INTEGER NOT NULL,
                file_type TEXT,
                file_id TEXT,
                url TEXT,
                platform TEXT,
                title TEXT,
                share_token TEXT,
                channel_token TEXT,
                created_at INTEGER NOT NULL
            )",

            // Indexes for performance
            "CREATE INDEX IF NOT EXISTS idx_history_user ON history_entries(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_history_expires ON history_entries(expires_at)",
            "CREATE INDEX IF NOT EXISTS idx_share_user ON share_links(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_share_message ON share_links(chat_id, message_id)",
            "CREATE INDEX IF NOT EXISTS idx_sent_chat ON sent_messages(chat_id)",
            "CREATE INDEX IF NOT EXISTS idx_sent_share_token ON sent_messages(share_token)"
        ];

        try {
            foreach ($queries as $query) {
                self::$db->exec($query);
            }
        } catch (PDOException $e) {
            Logger::error("Failed to create tables", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get database instance
     */
    public static function getDb() {
        if (self::$db === null) {
            self::init();
        }
        return self::$db;
    }

    /**
     * Clean expired entries (run periodically)
     */
    public static function cleanExpired() {
        $db = self::getDb();
        $now = time();

        try {
            // Use prepared statements to prevent SQL injection
            $stmt = $db->prepare("DELETE FROM history_entries WHERE expires_at IS NOT NULL AND expires_at < ?");
            $stmt->execute([$now]);

            $stmt = $db->prepare("DELETE FROM share_links WHERE expires_at IS NOT NULL AND expires_at < ?");
            $stmt->execute([$now]);

            // Clean old sent_messages (older than 30 days)
            $thirtyDaysAgo = $now - (30 * 24 * 60 * 60);
            $stmt = $db->prepare("DELETE FROM sent_messages WHERE created_at < ?");
            $stmt->execute([$thirtyDaysAgo]);

            Logger::info("Database cleanup completed");
        } catch (PDOException $e) {
            Logger::error("Database cleanup failed", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Save history entry
     */
    public static function saveHistoryEntry($token, $data) {
        $db = self::getDb();

        $stmt = $db->prepare("
            INSERT OR REPLACE INTO history_entries 
            (token, user_id, url, platform, title, thumbnail, author, channel_id, message_id, created_at, expires_at)
            VALUES (:token, :user_id, :url, :platform, :title, :thumbnail, :author, :channel_id, :message_id, :created_at, :expires_at)
        ");

        return $stmt->execute([
            ':token' => $token,
            ':user_id' => $data['user_id'] ?? 0,
            ':url' => $data['url'] ?? '',
            ':platform' => $data['platform'] ?? null,
            ':title' => $data['title'] ?? null,
            ':thumbnail' => $data['thumbnail'] ?? null,
            ':author' => $data['author'] ?? null,
            ':channel_id' => $data['channel_id'] ?? null,
            ':message_id' => $data['message_id'] ?? null,
            ':created_at' => $data['created_at'] ?? time(),
            ':expires_at' => $data['expires_at'] ?? null
        ]);
    }

    /**
     * Get history entry by token
     */
    public static function getHistoryEntry($token) {
        $db = self::getDb();
        $stmt = $db->prepare("SELECT * FROM history_entries WHERE token = :token");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Delete history entry
     */
    public static function deleteHistoryEntry($token) {
        $db = self::getDb();
        $stmt = $db->prepare("DELETE FROM history_entries WHERE token = :token");
        return $stmt->execute([':token' => $token]);
    }

    /**
     * Save share link
     */
    public static function saveShareLink($token, $data) {
        $db = self::getDb();

        $stmt = $db->prepare("
            INSERT OR REPLACE INTO share_links
            (token, user_id, message_id, chat_id, url, platform, title, file_id, file_type, created_at, expires_at)
            VALUES (:token, :user_id, :message_id, :chat_id, :url, :platform, :title, :file_id, :file_type, :created_at, :expires_at)
        ");

        return $stmt->execute([
            ':token' => $token,
            ':user_id' => $data['user_id'] ?? 0,
            ':message_id' => $data['message_id'] ?? 0,
            ':chat_id' => $data['chat_id'] ?? 0,
            ':url' => $data['url'] ?? '',
            ':platform' => $data['platform'] ?? null,
            ':title' => $data['title'] ?? null,
            ':file_id' => $data['file_id'] ?? null,
            ':file_type' => $data['file_type'] ?? null,
            ':created_at' => $data['created_at'] ?? time(),
            ':expires_at' => $data['expires_at'] ?? null
        ]);
    }

    /**
     * Get share link by token
     */
    public static function getShareLink($token) {
        $db = self::getDb();
        $stmt = $db->prepare("SELECT * FROM share_links WHERE token = :token");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Get share link by message
     */
    public static function getShareByMessage($chatId, $messageId) {
        $db = self::getDb();
        $stmt = $db->prepare("SELECT * FROM share_links WHERE chat_id = :chat_id AND message_id = :message_id LIMIT 1");
        $stmt->execute([
            ':chat_id' => $chatId,
            ':message_id' => $messageId
        ]);
        return $stmt->fetch();
    }

    /**
     * Save sent message
     */
    public static function saveSentMessage($data) {
        $db = self::getDb();

        $stmt = $db->prepare("
            INSERT INTO sent_messages
            (chat_id, message_id, file_type, file_id, url, platform, title, share_token, channel_token, created_at)
            VALUES (:chat_id, :message_id, :file_type, :file_id, :url, :platform, :title, :share_token, :channel_token, :created_at)
        ");

        return $stmt->execute([
            ':chat_id' => $data['chat_id'] ?? 0,
            ':message_id' => $data['message_id'] ?? 0,
            ':file_type' => $data['file_type'] ?? null,
            ':file_id' => $data['file_id'] ?? null,
            ':url' => $data['url'] ?? null,
            ':platform' => $data['platform'] ?? null,
            ':title' => $data['title'] ?? null,
            ':share_token' => $data['share_token'] ?? null,
            ':channel_token' => $data['channel_token'] ?? null,
            ':created_at' => $data['created_at'] ?? time()
        ]);
    }

    /**
     * Get sent messages by share token
     */
    public static function getMessagesByShareToken($token) {
        $db = self::getDb();
        $stmt = $db->prepare("SELECT * FROM sent_messages WHERE share_token = :token");
        $stmt->execute([':token' => $token]);
        return $stmt->fetchAll();
    }

    /**
     * Get sent messages by channel token
     */
    public static function getMessagesByChannelToken($token) {
        $db = self::getDb();
        $stmt = $db->prepare("SELECT * FROM sent_messages WHERE channel_token = :token");
        $stmt->execute([':token' => $token]);
        return $stmt->fetchAll();
    }
}
