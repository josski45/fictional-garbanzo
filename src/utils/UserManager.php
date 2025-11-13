<?php

namespace JosskiTools\Utils;

/**
 * UserManager - Manage user data and broadcast lists
 */
class UserManager {

    private static $dataDir;
    private static $usersFile;

    /**
     * Initialize user manager
     */
    public static function init($dataDir = null) {
        if ($dataDir === null) {
            self::$dataDir = __DIR__ . '/../../data';
        } else {
            self::$dataDir = $dataDir;
        }

        self::$usersFile = self::$dataDir . '/users.json';

        // Create data directory if not exists
        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }

        // Create users.json if not exists
        if (!file_exists(self::$usersFile)) {
            self::saveUsers([]);
        }
    }

    /**
     * Get users file path
     */
    private static function getUsersFile() {
        if (self::$usersFile === null) {
            self::init();
        }
        return self::$usersFile;
    }

    /**
     * Load all users
     */
    private static function loadUsers() {
        $usersFile = self::getUsersFile();

        if (!file_exists($usersFile)) {
            return [];
        }

        $content = file_get_contents($usersFile);
        $users = json_decode($content, true);

        return is_array($users) ? $users : [];
    }

    /**
     * Save all users
     */
    private static function saveUsers($users) {
        $usersFile = self::getUsersFile();
        $content = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($usersFile, $content, LOCK_EX);
    }

    /**
     * Add or update user
     */
    public static function addUser($userId, $userData = []) {
        $users = self::loadUsers();

        $now = date('Y-m-d H:i:s');

        if (isset($users[$userId])) {
            // Update existing user
            $users[$userId]['last_seen'] = $now;
            $users[$userId]['request_count'] = ($users[$userId]['request_count'] ?? 0) + 1;

            // Merge additional data
            if (!empty($userData)) {
                $users[$userId] = array_merge($users[$userId], $userData);
            }
        } else {
            // Add new user
            $users[$userId] = array_merge([
                'user_id' => $userId,
                'first_seen' => $now,
                'last_seen' => $now,
                'request_count' => 1,
                'is_blocked' => false,
                'is_admin' => false
            ], $userData);

            Logger::info("New user registered", ['user_id' => $userId]);
        }

        self::saveUsers($users);
        return $users[$userId];
    }

    /**
     * Get user data
     */
    public static function getUser($userId) {
        $users = self::loadUsers();
        return $users[$userId] ?? null;
    }

    /**
     * Get all users
     */
    public static function getAllUsers() {
        return self::loadUsers();
    }

    /**
     * Get user IDs only (for broadcast)
     */
    public static function getUserIds() {
        $users = self::loadUsers();
        return array_keys($users);
    }

    /**
     * Get active user IDs (not blocked)
     */
    public static function getActiveUserIds() {
        $users = self::loadUsers();
        $activeIds = [];

        foreach ($users as $userId => $userData) {
            if (!($userData['is_blocked'] ?? false)) {
                $activeIds[] = $userId;
            }
        }

        return $activeIds;
    }

    /**
     * Block user
     */
    public static function blockUser($userId) {
        $users = self::loadUsers();

        if (isset($users[$userId])) {
            $users[$userId]['is_blocked'] = true;
            $users[$userId]['blocked_at'] = date('Y-m-d H:i:s');
            self::saveUsers($users);

            Logger::warning("User blocked", ['user_id' => $userId]);
            return true;
        }

        return false;
    }

    /**
     * Unblock user
     */
    public static function unblockUser($userId) {
        $users = self::loadUsers();

        if (isset($users[$userId])) {
            $users[$userId]['is_blocked'] = false;
            $users[$userId]['unblocked_at'] = date('Y-m-d H:i:s');
            self::saveUsers($users);

            Logger::info("User unblocked", ['user_id' => $userId]);
            return true;
        }

        return false;
    }

    /**
     * Set user as admin
     */
    public static function setAdmin($userId, $isAdmin = true) {
        $users = self::loadUsers();

        if (isset($users[$userId])) {
            $users[$userId]['is_admin'] = $isAdmin;
            self::saveUsers($users);
            return true;
        }

        return false;
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin($userId) {
        $user = self::getUser($userId);
        return $user['is_admin'] ?? false;
    }

    /**
     * Check if user is blocked
     */
    public static function isBlocked($userId) {
        $user = self::getUser($userId);
        return $user['is_blocked'] ?? false;
    }

    /**
     * Update user metadata
     */
    public static function updateUser($userId, $data) {
        $users = self::loadUsers();

        if (isset($users[$userId])) {
            $users[$userId] = array_merge($users[$userId], $data);
            self::saveUsers($users);
            return true;
        }

        return false;
    }

    /**
     * Get total user count
     */
    public static function getTotalUsers() {
        $users = self::loadUsers();
        return count($users);
    }

    /**
     * Get active user count
     */
    public static function getActiveUserCount() {
        return count(self::getActiveUserIds());
    }

    /**
     * Get statistics
     */
    public static function getStats() {
        $users = self::loadUsers();

        $totalUsers = count($users);
        $activeUsers = 0;
        $blockedUsers = 0;
        $adminUsers = 0;
        $totalRequests = 0;

        foreach ($users as $user) {
            if (!($user['is_blocked'] ?? false)) {
                $activeUsers++;
            } else {
                $blockedUsers++;
            }

            if ($user['is_admin'] ?? false) {
                $adminUsers++;
            }

            $totalRequests += $user['request_count'] ?? 0;
        }

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'blocked_users' => $blockedUsers,
            'admin_users' => $adminUsers,
            'total_requests' => $totalRequests
        ];
    }

    /**
     * Export users to CSV
     */
    public static function exportToCsv($filename = null) {
        if ($filename === null) {
            $filename = self::$dataDir . '/users-' . date('Y-m-d') . '.csv';
        }

        $users = self::loadUsers();

        $fp = fopen($filename, 'w');

        // Write header
        fputcsv($fp, ['User ID', 'First Seen', 'Last Seen', 'Request Count', 'Is Blocked', 'Is Admin']);

        // Write data
        foreach ($users as $user) {
            fputcsv($fp, [
                $user['user_id'] ?? '',
                $user['first_seen'] ?? '',
                $user['last_seen'] ?? '',
                $user['request_count'] ?? 0,
                ($user['is_blocked'] ?? false) ? 'Yes' : 'No',
                ($user['is_admin'] ?? false) ? 'Yes' : 'No'
            ]);
        }

        fclose($fp);

        return $filename;
    }
}
