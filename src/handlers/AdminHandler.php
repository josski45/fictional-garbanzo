<?php

namespace JosskiTools\Handlers;

use JosskiTools\Utils\TelegramBot;
use JosskiTools\Utils\Logger;
use JosskiTools\Utils\UserLogger;
use JosskiTools\Utils\UserManager;
use JosskiTools\Utils\MaintenanceManager;
use JosskiTools\Helpers\KeyboardHelper;

/**
 * Admin Handler - Handle admin commands and panel
 */
class AdminHandler {

    private $bot;
    private $config;
    private $sessionManager;

    public function __construct($bot, $sessionManager, $config) {
        $this->bot = $bot;
        $this->config = $config;
        $this->sessionManager = $sessionManager;

        Logger::init($config['directories']['logs'] ?? null);
        UserLogger::init($config['directories']['logs'] ?? null);
        UserManager::init();
        MaintenanceManager::init($config['directories']['data'] ?? null);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin($userId) {
        $adminIds = $this->config['admin_ids'] ?? [];
        return in_array((int)$userId, $adminIds);
    }

    /**
     * Show admin panel
     */
    public function showPanel($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            $this->bot->sendMessage($chatId, "❌ Access denied. Admin only.");
            Logger::warning("Non-admin tried to access admin panel", ['user_id' => $userId]);
            return;
        }

        UserLogger::logCommand($userId, '/admin');
        Logger::info("Admin panel accessed", ['user_id' => $userId]);

        $stats = UserManager::getStats();

        $message = "👑 **ADMIN PANEL**\n\n";
        $message .= "📊 **User Statistics:**\n";
        $message .= "• Total Users: {$stats['total_users']}\n";
        $message .= "• Active Users: {$stats['active_users']}\n";
        $message .= "• Blocked Users: {$stats['blocked_users']}\n";
        $message .= "• Total Requests: {$stats['total_requests']}\n\n";
        $message .= "🎛️ **Available Commands:**\n\n";
        $message .= "🔧 **Maintenance Mode:**\n";
        $message .= "/maintenancestatus - Check status\n";
        $message .= "/maintenanceon [duration] [message] - Enable\n";
        $message .= "/maintenanceoff - Disable\n";
        $message .= "/maintenancemsg <message> - Set message\n\n";
        $message .= "📢 **Broadcast:**\n";
        $message .= "/broadcast - Send message to all users\n";
        $message .= "/maintenancebroadcast - Send maintenance notice\n";
        $message .= "/promo - Send promotion message\n\n";
        $message .= "👥 **User Management:**\n";
        $message .= "/userstats - Detailed statistics\n";
        $message .= "/blockuser <id> - Block user\n";
        $message .= "/unblockuser <id> - Unblock user\n";
        $message .= "/exportusers - Export to CSV\n\n";
        $message .= "📝 **Logs:**\n";
        $message .= "/viewlogs - View recent logs\n";
        $message .= "/userlog <id> - View user activity\n\n";
        $message .= "🔧 **System:**\n";
        $message .= "/testapi - Test NekoLabs API\n";
        $message .= "/cleanlogs - Clean old logs";

        $keyboard = $this->getAdminKeyboard();

        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }

    /**
     * Get admin keyboard
     */
    private function getAdminKeyboard() {
        $maintenanceStatus = MaintenanceManager::isEnabled() ? '🔴 ON' : '🟢 OFF';

        return [
            'inline_keyboard' => [
                [
                    ['text' => "🔧 Maintenance ({$maintenanceStatus})", 'callback_data' => 'admin_maintenance']
                ],
                [
                    ['text' => '📢 Broadcast', 'callback_data' => 'admin_broadcast'],
                    ['text' => '📊 Stats', 'callback_data' => 'admin_stats']
                ],
                [
                    ['text' => '👥 Users', 'callback_data' => 'admin_users'],
                    ['text' => '📝 Logs', 'callback_data' => 'admin_logs']
                ],
                [
                    ['text' => '🔧 System', 'callback_data' => 'admin_system'],
                    ['text' => '❌ Close', 'callback_data' => 'admin_close']
                ]
            ]
        ];
    }

    /**
     * Show user statistics
     */
    public function showUserStats($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $stats = UserManager::getStats();
        $allUsers = UserManager::getAllUsers();

        // Calculate additional stats
        $usersToday = 0;
        $usersThisWeek = 0;
        $usersThisMonth = 0;

        $now = time();
        $dayAgo = $now - 86400;
        $weekAgo = $now - (7 * 86400);
        $monthAgo = $now - (30 * 86400);

        foreach ($allUsers as $user) {
            $firstSeen = strtotime($user['first_seen'] ?? '1970-01-01');

            if ($firstSeen >= $dayAgo) $usersToday++;
            if ($firstSeen >= $weekAgo) $usersThisWeek++;
            if ($firstSeen >= $monthAgo) $usersThisMonth++;
        }

        $message = "📊 **DETAILED USER STATISTICS**\n\n";
        $message .= "👥 **Total Users:** {$stats['total_users']}\n";
        $message .= "• Active: {$stats['active_users']}\n";
        $message .= "• Blocked: {$stats['blocked_users']}\n";
        $message .= "• Admins: {$stats['admin_users']}\n\n";
        $message .= "📈 **New Users:**\n";
        $message .= "• Today: {$usersToday}\n";
        $message .= "• This Week: {$usersThisWeek}\n";
        $message .= "• This Month: {$usersThisMonth}\n\n";
        $message .= "📊 **Activity:**\n";
        $message .= "• Total Requests: {$stats['total_requests']}\n";
        $message .= "• Avg per User: " . round($stats['total_requests'] / max(1, $stats['total_users']), 1);

        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Initiate broadcast
     */
    public function initiateBroadcast($chatId, $userId, $type = 'general') {
        if (!$this->isAdmin($userId)) {
            return;
        }

        UserLogger::logCommand($userId, '/broadcast', ['type' => $type]);

        // Set session state
        $this->sessionManager->setState($userId, 'awaiting_broadcast', [
            'type' => $type
        ]);

        $typeEmoji = [
            'general' => '📢',
            'maintenance' => '🔧',
            'promo' => '🎁'
        ];

        $emoji = $typeEmoji[$type] ?? '📢';

        $message = "{$emoji} **BROADCAST MESSAGE**\n\n";
        $message .= "Send the message you want to broadcast to all users.\n\n";
        $message .= "**Type:** " . ucfirst($type) . "\n";
        $message .= "**Target:** All active users\n\n";
        $message .= "You can send:\n";
        $message .= "• Text message\n";
        $message .= "• Photo with caption\n";
        $message .= "• Video with caption\n\n";
        $message .= "Use /cancel to abort.";

        $keyboard = KeyboardHelper::getCancelKeyboard();
        $this->bot->sendMessage($chatId, $message, 'Markdown', $keyboard);
    }

    /**
     * Execute broadcast
     */
    public function executeBroadcast($chatId, $userId, $message, $messageData = null) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $session = $this->sessionManager->getSession($userId);
        $type = $session['data']['type'] ?? 'general';

        Logger::info("Broadcast initiated", [
            'admin_id' => $userId,
            'type' => $type
        ]);

        $userIds = UserManager::getActiveUserIds();
        $totalUsers = count($userIds);

        // Show confirmation first
        $confirmMsg = "📊 **Broadcast Preview**\n\n";
        $confirmMsg .= "**Type:** " . ucfirst($type) . "\n";
        $confirmMsg .= "**Target Users:** {$totalUsers}\n\n";
        $confirmMsg .= "**Message:**\n{$message}\n\n";
        $confirmMsg .= "❓ Confirm broadcast?";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Confirm', 'callback_data' => 'broadcast_confirm'],
                    ['text' => '❌ Cancel', 'callback_data' => 'broadcast_cancel']
                ]
            ]
        ];

        // Store broadcast data in session
        $this->sessionManager->setState($userId, 'pending_broadcast', [
            'type' => $type,
            'message' => $message,
            'messageData' => $messageData,
            'targetUsers' => $userIds
        ]);

        $this->bot->sendMessage($chatId, $confirmMsg, 'Markdown', $keyboard);
    }

    /**
     * Confirm and send broadcast
     */
    public function confirmBroadcast($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $session = $this->sessionManager->getSession($userId);
        $broadcastData = $session['data'] ?? [];

        if (empty($broadcastData['message'])) {
            $this->bot->sendMessage($chatId, "❌ No broadcast data found.");
            return;
        }

        $message = $broadcastData['message'];
        $targetUsers = $broadcastData['targetUsers'] ?? [];
        $type = $broadcastData['type'] ?? 'general';

        $totalUsers = count($targetUsers);

        Logger::info("Broadcast confirmed, sending...", [
            'admin_id' => $userId,
            'type' => $type,
            'target_count' => $totalUsers
        ]);

        // Add broadcast header based on type
        $header = match($type) {
            'maintenance' => "🔧 **MAINTENANCE NOTICE**\n\n",
            'promo' => "🎁 **SPECIAL PROMOTION**\n\n",
            default => "📢 **ANNOUNCEMENT**\n\n"
        };

        $fullMessage = $header . $message;

        // Send progress message
        $progressMsg = $this->bot->sendMessage($chatId, "📤 Sending broadcast...\n\nProgress: 0/{$totalUsers}");
        $progressMsgId = $progressMsg['result']['message_id'] ?? null;

        $sent = 0;
        $failed = 0;

        // Send to all users
        foreach ($targetUsers as $targetUserId) {
            try {
                $result = $this->bot->sendMessage($targetUserId, $fullMessage, 'Markdown');

                if ($result['ok'] ?? false) {
                    $sent++;
                } else {
                    $failed++;
                    Logger::warning("Broadcast failed to user", [
                        'user_id' => $targetUserId,
                        'error' => $result['description'] ?? 'Unknown'
                    ]);
                }

                // Update progress every 10 users
                if ($sent % 10 === 0 && $progressMsgId) {
                    $this->bot->editMessage(
                        $chatId,
                        $progressMsgId,
                        "📤 Sending broadcast...\n\nProgress: {$sent}/{$totalUsers}"
                    );
                }

                // Rate limit: 30 messages per second max
                usleep(50000); // 0.05 second delay

            } catch (\Exception $e) {
                $failed++;
                Logger::exception($e, [
                    'context' => 'broadcast',
                    'user_id' => $targetUserId
                ]);
            }
        }

        // Final report
        $reportMsg = "✅ **Broadcast Complete!**\n\n";
        $reportMsg .= "📊 **Results:**\n";
        $reportMsg .= "• Sent: {$sent}\n";
        $reportMsg .= "• Failed: {$failed}\n";
        $reportMsg .= "• Total: {$totalUsers}\n\n";
        $reportMsg .= "Success Rate: " . round(($sent / $totalUsers) * 100, 1) . "%";

        $this->bot->sendMessage($chatId, $reportMsg, 'Markdown');

        // Clear session
        $this->sessionManager->clearState($userId);

        Logger::info("Broadcast completed", [
            'admin_id' => $userId,
            'sent' => $sent,
            'failed' => $failed,
            'total' => $totalUsers
        ]);
    }

    /**
     * Block user
     */
    public function blockUser($chatId, $userId, $targetUserId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $result = UserManager::blockUser($targetUserId);

        if ($result) {
            $this->bot->sendMessage(
                $chatId,
                "✅ User {$targetUserId} has been blocked."
            );

            Logger::info("User blocked by admin", [
                'admin_id' => $userId,
                'target_user' => $targetUserId
            ]);
        } else {
            $this->bot->sendMessage(
                $chatId,
                "❌ Failed to block user {$targetUserId}. User may not exist."
            );
        }
    }

    /**
     * Unblock user
     */
    public function unblockUser($chatId, $userId, $targetUserId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $result = UserManager::unblockUser($targetUserId);

        if ($result) {
            $this->bot->sendMessage(
                $chatId,
                "✅ User {$targetUserId} has been unblocked."
            );

            Logger::info("User unblocked by admin", [
                'admin_id' => $userId,
                'target_user' => $targetUserId
            ]);
        } else {
            $this->bot->sendMessage(
                $chatId,
                "❌ Failed to unblock user {$targetUserId}. User may not exist."
            );
        }
    }

    /**
     * View user activity log
     */
    public function viewUserLog($chatId, $userId, $targetUserId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $recentActivity = UserLogger::getRecentActivity($targetUserId, 20);
        $activityCount = UserLogger::getUserActivityCount($targetUserId);

        if (empty($recentActivity)) {
            $this->bot->sendMessage(
                $chatId,
                "❌ No activity found for user {$targetUserId}."
            );
            return;
        }

        $message = "📝 **User Activity Log**\n\n";
        $message .= "**User ID:** `{$targetUserId}`\n";
        $message .= "**Total Activities:** {$activityCount}\n\n";
        $message .= "**Recent Activities (20):**\n";
        $message .= "```\n";
        $message .= implode("\n", array_slice($recentActivity, -20));
        $message .= "\n```";

        $this->bot->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Export users to CSV
     */
    public function exportUsers($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        try {
            $filename = UserManager::exportToCsv();

            $this->bot->sendDocument(
                $chatId,
                $filename,
                "📊 Users Export - " . date('Y-m-d H:i:s')
            );

            // Delete file after sending
            @unlink($filename);

            Logger::info("Users exported", ['admin_id' => $userId]);

        } catch (\Exception $e) {
            Logger::exception($e, ['context' => 'export_users']);
            $this->bot->sendMessage(
                $chatId,
                "❌ Failed to export users: " . $e->getMessage()
            );
        }
    }

    /**
     * View recent application logs
     */
    public function viewLogs($chatId, $userId, $lines = 50) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $logFile = Logger::getTodayLogFile();

        if (!file_exists($logFile)) {
            $this->bot->sendMessage($chatId, "❌ No logs found for today.");
            return;
        }

        $logLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recentLogs = array_slice($logLines, -$lines);

        $message = "📝 **Application Logs (Last {$lines} lines)**\n\n";
        $message .= "```\n";
        $message .= implode("\n", $recentLogs);
        $message .= "\n```";

        // Telegram message limit is 4096 chars
        if (strlen($message) > 4000) {
            // Send as file instead
            $tempFile = sys_get_temp_dir() . '/logs-' . time() . '.txt';
            file_put_contents($tempFile, implode("\n", $recentLogs));

            $this->bot->sendDocument(
                $chatId,
                $tempFile,
                "📝 Application Logs - " . date('Y-m-d H:i:s')
            );

            @unlink($tempFile);
        } else {
            $this->bot->sendMessage($chatId, $message, 'Markdown');
        }
    }

    /**
     * Clean old logs
     */
    public function cleanLogs($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        try {
            $appLogsDeleted = Logger::cleanup(30);
            $userLogsDeleted = UserLogger::cleanup(90);

            $message = "🧹 **Logs Cleanup Complete**\n\n";
            $message .= "• App logs deleted: {$appLogsDeleted}\n";
            $message .= "• User logs deleted: {$userLogsDeleted}\n\n";
            $message .= "Old logs have been removed.";

            $this->bot->sendMessage($chatId, $message, 'Markdown');

            Logger::info("Logs cleaned by admin", [
                'admin_id' => $userId,
                'app_logs' => $appLogsDeleted,
                'user_logs' => $userLogsDeleted
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, ['context' => 'clean_logs']);
            $this->bot->sendMessage(
                $chatId,
                "❌ Failed to clean logs: " . $e->getMessage()
            );
        }
    }

    /**
     * Show maintenance status
     */
    public function maintenanceStatus($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $message = MaintenanceManager::getStatusMessage();
        $this->bot->sendMessage($chatId, $message, 'Markdown');

        UserLogger::logCommand($userId, '/maintenancestatus');
    }

    /**
     * Enable maintenance mode
     */
    public function maintenanceOn($chatId, $userId, $args = '') {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $parts = explode(' ', trim($args), 2);
        $duration = null;
        $message = null;

        // Check if first argument is a number (duration in minutes)
        if (!empty($parts[0]) && is_numeric($parts[0])) {
            $duration = (int)$parts[0];
            $message = $parts[1] ?? null;
        } else {
            // No duration, all text is the message
            $message = $args ?: null;
        }

        // Enable maintenance
        MaintenanceManager::enable($userId, $message, $duration);

        // Add current admin to whitelist
        MaintenanceManager::addToWhitelist($userId);

        $responseMsg = "✅ **Maintenance Mode ENABLED**\n\n";

        if ($duration) {
            $responseMsg .= "⏰ Duration: {$duration} minutes\n";
            $endTime = date('H:i', time() + ($duration * 60));
            $responseMsg .= "🕐 Auto-disable at: {$endTime}\n\n";
        } else {
            $responseMsg .= "⏰ Duration: Manual (use /maintenanceoff to disable)\n\n";
        }

        if ($message) {
            $responseMsg .= "📝 Custom message set.\n\n";
        }

        $responseMsg .= "✅ You are whitelisted (can still use bot)\n";
        $responseMsg .= "ℹ️ Other users will see maintenance message";

        $this->bot->sendMessage($chatId, $responseMsg, 'Markdown');

        UserLogger::logCommand($userId, '/maintenanceon', [
            'duration' => $duration,
            'has_custom_message' => $message ? 'yes' : 'no'
        ]);
    }

    /**
     * Disable maintenance mode
     */
    public function maintenanceOff($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        if (!MaintenanceManager::isEnabled()) {
            $this->bot->sendMessage($chatId, "ℹ️ Maintenance mode is already disabled.");
            return;
        }

        MaintenanceManager::disable($userId);

        $message = "✅ **Maintenance Mode DISABLED**\n\n";
        $message .= "Bot is now operational for all users.";

        $this->bot->sendMessage($chatId, $message, 'Markdown');

        UserLogger::logCommand($userId, '/maintenanceoff');
    }

    /**
     * Set maintenance message
     */
    public function maintenanceMessage($chatId, $userId, $args = '') {
        if (!$this->isAdmin($userId)) {
            return;
        }

        if (empty($args)) {
            $this->bot->sendMessage(
                $chatId,
                "❌ Usage: /maintenancemsg <your message here>"
            );
            return;
        }

        MaintenanceManager::setMessage($args);

        $message = "✅ **Maintenance Message Updated**\n\n";
        $message .= "**New message:**\n" . $args . "\n\n";
        $message .= "This will be shown to users when maintenance mode is enabled.";

        $this->bot->sendMessage($chatId, $message, 'Markdown');

        UserLogger::logCommand($userId, '/maintenancemsg');
    }

    /**
     * Send maintenance broadcast
     */
    public function maintenanceBroadcast($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            return;
        }

        // Get all users
        $users = UserManager::getAllUsers();
        $targetUsers = array_filter($users, function($user) {
            return !($user['is_blocked'] ?? false);
        });

        $message = "🔧 **MAINTENANCE NOTICE**\n\n";
        $message .= "Bot akan mengalami maintenance dalam waktu dekat.\n\n";
        $message .= "⏰ **Durasi:** ~30-60 menit\n";
        $message .= "📅 **Waktu:** Segera\n\n";
        $message .= "Mohon maaf atas ketidaknyamanannya.\n";
        $message .= "Terima kasih! 🙏";

        $sent = 0;
        $failed = 0;

        foreach ($targetUsers as $user) {
            try {
                $targetUserId = $user['user_id'];
                $result = $this->bot->sendMessage($targetUserId, $message, 'Markdown');

                if ($result['ok'] ?? false) {
                    $sent++;
                } else {
                    $failed++;
                }

                usleep(50000); // 50ms delay (20 msg/sec)

            } catch (\Exception $e) {
                $failed++;
            }
        }

        $reportMsg = "📢 **Maintenance Broadcast Complete**\n\n";
        $reportMsg .= "✅ Sent: {$sent}\n";
        $reportMsg .= "❌ Failed: {$failed}\n\n";
        $reportMsg .= "Total: " . count($targetUsers);

        $this->bot->sendMessage($chatId, $reportMsg, 'Markdown');

        Logger::info("Maintenance broadcast sent", [
            'admin_id' => $userId,
            'sent' => $sent,
            'failed' => $failed
        ]);

        UserLogger::logCommand($userId, '/maintenancebroadcast', [
            'sent' => $sent,
            'failed' => $failed
        ]);
    }
}
