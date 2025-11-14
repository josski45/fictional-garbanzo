<?php

namespace JosskiTools\Utils;

/**
 * MaintenanceManager - Manage bot maintenance mode
 *
 * Features:
 * - Enable/disable maintenance mode
 * - Custom maintenance message
 * - Whitelist admins (can use bot during maintenance)
 * - Schedule maintenance
 */
class MaintenanceManager {
    private static $maintenanceFile = null;

    /**
     * Initialize maintenance manager
     */
    public static function init($dataDir = null) {
        if ($dataDir === null) {
            $dataDir = __DIR__ . '/../../data';
        }

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        self::$maintenanceFile = $dataDir . '/maintenance.json';

        // Create default file if not exists
        if (!file_exists(self::$maintenanceFile)) {
            self::saveStatus([
                'enabled' => false,
                'message' => '🔧 Bot sedang dalam maintenance.\n\nMohon tunggu beberapa saat.',
                'enabled_at' => null,
                'enabled_by' => null,
                'whitelist' => [], // Admin IDs yang bisa akses saat maintenance
                'scheduled_end' => null
            ]);
        }
    }

    /**
     * Load maintenance status
     */
    private static function loadStatus() {
        if (!file_exists(self::$maintenanceFile)) {
            return [
                'enabled' => false,
                'message' => '🔧 Bot sedang dalam maintenance.\n\nMohon tunggu beberapa saat.',
                'enabled_at' => null,
                'enabled_by' => null,
                'whitelist' => [],
                'scheduled_end' => null
            ];
        }

        $data = json_decode(file_get_contents(self::$maintenanceFile), true);
        return $data ?? [];
    }

    /**
     * Save maintenance status
     */
    private static function saveStatus($data) {
        file_put_contents(self::$maintenanceFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Check if maintenance mode is enabled
     */
    public static function isEnabled() {
        $status = self::loadStatus();

        // Check if scheduled end time has passed
        if ($status['enabled'] && $status['scheduled_end']) {
            if (time() >= $status['scheduled_end']) {
                // Auto-disable maintenance
                self::disable('System (scheduled end)');
                return false;
            }
        }

        return $status['enabled'] ?? false;
    }

    /**
     * Check if user can bypass maintenance mode
     */
    public static function canBypass($userId) {
        $status = self::loadStatus();

        // Check if user is in whitelist (admins)
        return in_array($userId, $status['whitelist'] ?? []);
    }

    /**
     * Enable maintenance mode
     */
    public static function enable($adminId, $message = null, $durationMinutes = null) {
        $status = self::loadStatus();
        $status['enabled'] = true;

        if ($message) {
            $status['message'] = $message;
        }

        $status['enabled_at'] = date('Y-m-d H:i:s');
        $status['enabled_by'] = $adminId;

        // Set scheduled end time if duration provided
        if ($durationMinutes) {
            $status['scheduled_end'] = time() + ($durationMinutes * 60);
        } else {
            $status['scheduled_end'] = null;
        }

        self::saveStatus($status);

        Logger::info("Maintenance mode enabled", [
            'admin_id' => $adminId,
            'duration_minutes' => $durationMinutes,
            'scheduled_end' => $status['scheduled_end'] ? date('Y-m-d H:i:s', $status['scheduled_end']) : 'manual'
        ]);

        return true;
    }

    /**
     * Disable maintenance mode
     */
    public static function disable($adminId) {
        $status = self::loadStatus();
        $status['enabled'] = false;
        $status['scheduled_end'] = null;

        self::saveStatus($status);

        Logger::info("Maintenance mode disabled", ['admin_id' => $adminId]);

        return true;
    }

    /**
     * Get maintenance message
     */
    public static function getMessage() {
        $status = self::loadStatus();
        $message = $status['message'] ?? '🔧 Bot sedang dalam maintenance.';

        // Add scheduled end time info if available
        if ($status['scheduled_end']) {
            $endTime = date('H:i', $status['scheduled_end']);
            $endDate = date('Y-m-d', $status['scheduled_end']);
            $message .= "\n\n⏰ Estimasi selesai: {$endTime}";

            if ($endDate !== date('Y-m-d')) {
                $message .= " ({$endDate})";
            }
        }

        return $message;
    }

    /**
     * Set custom maintenance message
     */
    public static function setMessage($message) {
        $status = self::loadStatus();
        $status['message'] = $message;
        self::saveStatus($status);

        return true;
    }

    /**
     * Add user to whitelist (can bypass maintenance)
     */
    public static function addToWhitelist($userId) {
        $status = self::loadStatus();

        if (!in_array($userId, $status['whitelist'])) {
            $status['whitelist'][] = $userId;
            self::saveStatus($status);
        }

        return true;
    }

    /**
     * Remove user from whitelist
     */
    public static function removeFromWhitelist($userId) {
        $status = self::loadStatus();

        $status['whitelist'] = array_values(array_filter(
            $status['whitelist'],
            function($id) use ($userId) {
                return $id !== $userId;
            }
        ));

        self::saveStatus($status);

        return true;
    }

    /**
     * Get maintenance status info
     */
    public static function getStatus() {
        return self::loadStatus();
    }

    /**
     * Get formatted status message for admins
     */
    public static function getStatusMessage() {
        $status = self::loadStatus();

        $message = "🔧 **MAINTENANCE MODE STATUS**\n\n";

        if ($status['enabled']) {
            $message .= "**Status:** 🔴 ENABLED\n";
            $message .= "**Enabled at:** " . ($status['enabled_at'] ?? 'Unknown') . "\n";
            $message .= "**Enabled by:** " . ($status['enabled_by'] ?? 'Unknown') . "\n";

            if ($status['scheduled_end']) {
                $endTime = date('Y-m-d H:i:s', $status['scheduled_end']);
                $remaining = $status['scheduled_end'] - time();
                $remainingMin = ceil($remaining / 60);
                $message .= "**Scheduled end:** {$endTime}\n";
                $message .= "**Remaining:** ~{$remainingMin} minutes\n";
            } else {
                $message .= "**Duration:** Manual (no auto-end)\n";
            }
        } else {
            $message .= "**Status:** 🟢 DISABLED\n";
            $message .= "\nBot is operational.\n";
        }

        $message .= "\n**Message:**\n" . ($status['message'] ?? 'Default message') . "\n";

        $whitelistCount = count($status['whitelist'] ?? []);
        $message .= "\n**Whitelisted admins:** {$whitelistCount}";

        return $message;
    }
}
