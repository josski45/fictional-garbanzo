<?php

namespace JosskiTools\Utils;

/**
 * ChannelHistory - Use Telegram Channel as visual database for download history
 * User dapat langsung klik untuk re-download dari channel
 */
class ChannelHistory {

    private static $bot;
    private static $config;
    private static $dataDir;
    private static $userChannelsFile;

    /**
     * Initialize channel history
     */
    public static function init($bot, $config) {
        self::$bot = $bot;
        self::$config = $config;

        self::$dataDir = $config['directories']['data'] ?? __DIR__ . '/../../data';
        self::$userChannelsFile = self::$dataDir . '/user_channels.json';

        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }

        if (!file_exists(self::$userChannelsFile)) {
            file_put_contents(self::$userChannelsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Load user channels data
     */
    private static function loadUserChannels() {
        if (!file_exists(self::$userChannelsFile)) {
            return [];
        }

        $content = file_get_contents(self::$userChannelsFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save user channels data
     */
    private static function saveUserChannels($data) {
        file_put_contents(self::$userChannelsFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Setup user's history channel
     * User perlu create private channel dan add bot sebagai admin
     */
    public static function setupChannel($userId, $channelId) {
        $userChannels = self::loadUserChannels();

        // Verify bot has access to channel
        try {
            $chatInfo = self::$bot->request('getChat', ['chat_id' => $channelId]);

            if (!($chatInfo['ok'] ?? false)) {
                return [
                    'success' => false,
                    'message' => "❌ Bot cannot access this channel. Make sure the bot is admin!"
                ];
            }

            // Check if bot is admin
            $admins = self::$bot->request('getChatAdministrators', ['chat_id' => $channelId]);
            $botId = self::$bot->getMe()['result']['id'] ?? null;

            $isAdmin = false;
            if ($admins['ok'] ?? false) {
                foreach ($admins['result'] as $admin) {
                    if ($admin['user']['id'] == $botId) {
                        $isAdmin = true;
                        break;
                    }
                }
            }

            if (!$isAdmin) {
                return [
                    'success' => false,
                    'message' => "❌ Bot must be admin in the channel with 'Post Messages' permission!"
                ];
            }

            // Save channel for user
            $userChannels[$userId] = [
                'channel_id' => $channelId,
                'channel_title' => $chatInfo['result']['title'] ?? 'History Channel',
                'setup_date' => date('Y-m-d H:i:s'),
                'message_count' => 0
            ];

            self::saveUserChannels($userChannels);

            Logger::info("User history channel setup", [
                'user_id' => $userId,
                'channel_id' => $channelId
            ]);

            return [
                'success' => true,
                'message' => "✅ History channel setup complete!\n\n" .
                             "📺 Channel: {$chatInfo['result']['title']}\n\n" .
                             "Your downloads will now be forwarded to this channel as visual history!"
            ];

        } catch (\Exception $e) {
            Logger::exception($e, ['context' => 'setup_channel', 'user_id' => $userId]);

            return [
                'success' => false,
                'message' => "❌ Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get user's channel ID
     */
    public static function getUserChannel($userId) {
        $userChannels = self::loadUserChannels();
        return $userChannels[$userId] ?? null;
    }

    /**
     * Check if user has channel setup
     */
    public static function hasChannel($userId) {
        return self::getUserChannel($userId) !== null;
    }

    /**
     * Forward download to user's history channel
     */
    public static function forwardToChannel($userId, $messageId, $chatId, $downloadInfo = []) {
        $channel = self::getUserChannel($userId);

        if (!$channel) {
            // User hasn't setup channel yet
            return false;
        }

        $channelId = $channel['channel_id'];

        try {
            // Forward the message to channel
            $forwardResult = self::$bot->request('forwardMessage', [
                'chat_id' => $channelId,
                'from_chat_id' => $chatId,
                'message_id' => $messageId
            ]);

            if (!($forwardResult['ok'] ?? false)) {
                Logger::warning("Failed to forward to channel", [
                    'user_id' => $userId,
                    'channel_id' => $channelId,
                    'error' => $forwardResult['description'] ?? 'Unknown'
                ]);
                return false;
            }

            $forwardedMsgId = $forwardResult['result']['message_id'] ?? null;

            // Add caption/buttons to forwarded message
            if ($forwardedMsgId && !empty($downloadInfo)) {
                $caption = self::buildHistoryCaption($downloadInfo);
                $keyboard = self::buildHistoryKeyboard($downloadInfo);

                // Try to edit caption (if media message)
                self::$bot->request('editMessageCaption', [
                    'chat_id' => $channelId,
                    'message_id' => $forwardedMsgId,
                    'caption' => $caption,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode($keyboard)
                ]);
            }

            // Update message count
            $userChannels = self::loadUserChannels();
            $userChannels[$userId]['message_count']++;
            $userChannels[$userId]['last_forward'] = date('Y-m-d H:i:s');
            self::saveUserChannels($userChannels);

            Logger::debug("Forwarded to history channel", [
                'user_id' => $userId,
                'message_id' => $forwardedMsgId
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'forward_to_channel',
                'user_id' => $userId
            ]);

            return false;
        }
    }

    /**
     * Send download info to channel with inline button
     */
    public static function sendToChannel($userId, $mediaUrl, $mediaType, $downloadInfo = []) {
        $channel = self::getUserChannel($userId);

        if (!$channel) {
            return false;
        }

        $channelId = $channel['channel_id'];
        $caption = self::buildHistoryCaption($downloadInfo);
        $keyboard = self::buildHistoryKeyboard($downloadInfo);

        try {
            $result = null;

            // Send based on media type
            switch ($mediaType) {
                case 'video':
                    $result = self::$bot->sendVideo($channelId, $mediaUrl, $caption, 'Markdown', $keyboard);
                    break;

                case 'audio':
                    $result = self::$bot->sendAudio($channelId, $mediaUrl, $caption, 'Markdown', $keyboard);
                    break;

                case 'photo':
                case 'image':
                    $result = self::$bot->sendPhoto($channelId, $mediaUrl, $caption, 'Markdown', $keyboard);
                    break;

                default:
                    // Send as text with link
                    $result = self::$bot->sendMessage($channelId, $caption . "\n\n📥 " . $mediaUrl, 'Markdown', $keyboard);
                    break;
            }

            if ($result['ok'] ?? false) {
                // Update message count
                $userChannels = self::loadUserChannels();
                $userChannels[$userId]['message_count']++;
                $userChannels[$userId]['last_forward'] = date('Y-m-d H:i:s');
                self::saveUserChannels($userChannels);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'send_to_channel',
                'user_id' => $userId
            ]);

            return false;
        }
    }

    /**
     * Build caption for history message
     */
    private static function buildHistoryCaption($downloadInfo) {
        $platform = $downloadInfo['platform'] ?? 'unknown';
        $title = $downloadInfo['title'] ?? 'No title';
        $url = $downloadInfo['url'] ?? '';
        $date = date('Y-m-d H:i:s');

        $caption = "📥 **Download History**\n\n";
        $caption .= "🌐 Platform: {$platform}\n";
        $caption .= "📝 Title: {$title}\n";
        $caption .= "📅 Date: {$date}\n\n";
        $caption .= "🔗 Original URL:\n`{$url}`";

        return $caption;
    }

    /**
     * Build inline keyboard for history message
     */
    private static function buildHistoryKeyboard($downloadInfo) {
        $url = $downloadInfo['url'] ?? '';

        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Re-download', 'callback_data' => 'redownload_' . base64_encode($url)],
                    ['text' => '⭐ Add to Favorites', 'callback_data' => 'fav_' . base64_encode($url)]
                ],
                [
                    ['text' => '🗑️ Delete from History', 'callback_data' => 'del_history_' . base64_encode($url)]
                ]
            ]
        ];
    }

    /**
     * Get setup instructions
     */
    public static function getSetupInstructions() {
        $message = "📺 **SETUP HISTORY CHANNEL**\n\n";
        $message .= "Your downloads will be saved to a private Telegram channel as visual history!\n\n";
        $message .= "**How to Setup:**\n\n";
        $message .= "1️⃣ Create a new Telegram Channel\n";
        $message .= "   • Open Telegram → New Channel\n";
        $message .= "   • Make it **Private**\n";
        $message .= "   • Name it anything you want\n\n";
        $message .= "2️⃣ Add this bot as admin\n";
        $message .= "   • Channel Settings → Administrators\n";
        $message .= "   • Add @YourBotUsername\n";
        $message .= "   • Give 'Post Messages' permission\n\n";
        $message .= "3️⃣ Forward any message from channel to this bot\n";
        $message .= "   • Bot will detect channel ID\n";
        $message .= "   • Setup complete!\n\n";
        $message .= "**Benefits:**\n";
        $message .= "• ✅ Visual history with thumbnails\n";
        $message .= "• ✅ Click to re-download\n";
        $message .= "• ✅ Never lose your downloads\n";
        $message .= "• ✅ Free unlimited storage\n\n";
        $message .= "💡 Or use: `/setupchannel @channelname`";

        return $message;
    }

    /**
     * Remove channel setup
     */
    public static function removeChannel($userId) {
        $userChannels = self::loadUserChannels();

        if (isset($userChannels[$userId])) {
            $channelInfo = $userChannels[$userId];
            unset($userChannels[$userId]);
            self::saveUserChannels($userChannels);

            Logger::info("User channel removed", [
                'user_id' => $userId,
                'channel_id' => $channelInfo['channel_id']
            ]);

            return [
                'success' => true,
                'message' => "✅ History channel removed.\n\n" .
                             "Use /setupchannel to setup again."
            ];
        }

        return [
            'success' => false,
            'message' => "❌ No channel setup found."
        ];
    }

    /**
     * Get user channel info
     */
    public static function getChannelInfo($userId) {
        $channel = self::getUserChannel($userId);

        if (!$channel) {
            return "❌ No history channel setup.\n\nUse /setupchannel to get started!";
        }

        $message = "📺 **Your History Channel**\n\n";
        $message .= "📝 Title: {$channel['channel_title']}\n";
        $message .= "🆔 ID: `{$channel['channel_id']}`\n";
        $message .= "📅 Setup Date: {$channel['setup_date']}\n";
        $message .= "📊 Total Messages: {$channel['message_count']}\n";

        if (isset($channel['last_forward'])) {
            $message .= "🕐 Last Forward: {$channel['last_forward']}\n";
        }

        $message .= "\n💡 Commands:\n";
        $message .= "/channelinfo - View this info\n";
        $message .= "/removechannel - Remove channel setup";

        return $message;
    }

    /**
     * Get all users with channels (admin)
     */
    public static function getAllUsersWithChannels() {
        $userChannels = self::loadUserChannels();

        $stats = [
            'total_users' => count($userChannels),
            'total_messages' => 0,
            'users' => []
        ];

        foreach ($userChannels as $userId => $channel) {
            $stats['total_messages'] += $channel['message_count'];
            $stats['users'][$userId] = [
                'channel_title' => $channel['channel_title'],
                'message_count' => $channel['message_count'],
                'setup_date' => $channel['setup_date']
            ];
        }

        return $stats;
    }
}
