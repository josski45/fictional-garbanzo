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
    private static $historyIndexFile;
    private static $shareIndexFile;
    private static $botUsername;

    /**
     * Initialize channel history
     */
    public static function init($bot, $config) {
        self::$bot = $bot;
        self::$config = $config;

        self::$dataDir = $config['directories']['data'] ?? __DIR__ . '/../../data';
        self::$userChannelsFile = self::$dataDir . '/user_channels.json';
        self::$historyIndexFile = self::$dataDir . '/history_index.json';
        self::$shareIndexFile = self::$dataDir . '/share_index.json';

        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }

        if (!file_exists(self::$userChannelsFile)) {
            file_put_contents(self::$userChannelsFile, json_encode([], JSON_PRETTY_PRINT));
        }

        if (!file_exists(self::$historyIndexFile)) {
            file_put_contents(self::$historyIndexFile, json_encode([], JSON_PRETTY_PRINT));
        }

        if (!file_exists(self::$shareIndexFile)) {
            file_put_contents(self::$shareIndexFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    private static function getBotUsername() {
        if (!empty(self::$config['bot_username'])) {
            return ltrim(self::$config['bot_username'], '@');
        }

        if (self::$botUsername !== null) {
            return self::$botUsername;
        }

        try {
            $response = self::$bot ? self::$bot->getMe() : null;
            if (is_array($response) && ($response['ok'] ?? false)) {
                $username = $response['result']['username'] ?? '';
                self::$botUsername = $username ? ltrim($username, '@') : '';
            } else {
                self::$botUsername = '';
            }
        } catch (\Exception $e) {
            self::$botUsername = '';
        }

        return self::$botUsername;
    }

    public static function generateShareLink($token) {
        $token = trim((string)$token);
        if ($token === '') {
            return null;
        }

        $username = self::getBotUsername();
        if ($username === '') {
            return null;
        }

        return "https://t.me/{$username}?start=share_" . rawurlencode($token);
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

    private static function loadHistoryIndex() {
        if (!file_exists(self::$historyIndexFile)) {
            return [];
        }

        $content = file_get_contents(self::$historyIndexFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private static function saveHistoryIndex($data) {
        file_put_contents(self::$historyIndexFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function loadShareIndex() {
        if (!file_exists(self::$shareIndexFile)) {
            return [];
        }

        $content = file_get_contents(self::$shareIndexFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private static function saveShareIndex($data) {
        file_put_contents(self::$shareIndexFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
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

            $message = "✅ History channel setup complete!\n\n";
            $message .= "📺 Channel: {$chatInfo['result']['title']}\n\n";
            $message .= "Your downloads will now be forwarded to this channel as visual history!\n\n";
            $message .= "🔗 Setiap item punya ID unik dan tombol Bagikan untuk dibuka temanmu.";

            return [
                'success' => true,
                'message' => $message
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

    public static function rememberMessages($userId, array $messages, array $downloadInfo, array $options = []) {
        if (empty($messages)) {
            return;
        }

        foreach ($messages as $message) {
            $messageId = $message['message_id'] ?? null;
            if (!$messageId) {
                continue;
            }

            $entryOptions = $options;
            if (isset($message['type'])) {
                $entryOptions['media_type'] = $message['type'];
            }
            if (isset($message['file_id'])) {
                $entryOptions['file_id'] = $message['file_id'];
            }

            self::rememberMessage($userId, $messageId, $downloadInfo, $entryOptions);
        }
    }

    private static function rememberMessage($userId, $messageId, array $downloadInfo, array $options = []) {
        $index = self::loadShareIndex();

        $userKey = (string)$userId;
        $messageKey = (string)$messageId;

        if (!isset($index[$userKey])) {
            $index[$userKey] = [];
        }

        $sanitizedDownload = [
            'platform' => $downloadInfo['platform'] ?? 'unknown',
            'title' => $downloadInfo['title'] ?? null,
            'url' => $downloadInfo['url'] ?? null,
            'type' => $downloadInfo['type'] ?? null,
            'thumbnail' => $downloadInfo['thumbnail'] ?? null,
            'author' => $downloadInfo['author'] ?? null
        ];

        $entry = $index[$userKey][$messageKey] ?? [];
        $entry['download'] = $sanitizedDownload;
        $entry['media_type'] = $options['media_type'] ?? ($entry['media_type'] ?? null);

        if (isset($options['file_id'])) {
            $entry['file_id'] = $options['file_id'];
        }

        if (!empty($options['channel_token'])) {
            $entry['channel_token'] = $options['channel_token'];
        }

        if (!empty($options['share_token'])) {
            $entry['share_token'] = $options['share_token'];
        }

        $entry['updated_at'] = date('c');
        if (empty($entry['created_at'])) {
            $entry['created_at'] = date('c');
        }

        $index[$userKey][$messageKey] = $entry;

        self::saveShareIndex($index);
    }

    public static function createShareFromMessage($userId, $messageId) {
        $index = self::loadShareIndex();
        $userKey = (string)$userId;
        $messageKey = (string)$messageId;

        if (!isset($index[$userKey][$messageKey])) {
            return [
                'success' => false,
                'message' => '❌ Data file tidak ditemukan. Coba download ulang lalu gunakan /share.'
            ];
        }

        $entry = $index[$userKey][$messageKey];
        $downloadInfo = $entry['download'] ?? [];

        if (empty($downloadInfo['url'])) {
            return [
                'success' => false,
                'message' => '❌ URL unduhan tidak ditemukan sehingga link berbagi tidak bisa dibuat.'
            ];
        }

        $existingToken = $entry['share_token'] ?? ($entry['channel_token'] ?? null);
        if ($existingToken && self::getHistoryEntry($existingToken)) {
            $link = self::generateShareLink($existingToken);
            if ($link) {
                if (empty($entry['share_token'])) {
                    $entry['share_token'] = $existingToken;
                    $entry['updated_at'] = date('c');
                    $index[$userKey][$messageKey] = $entry;
                    self::saveShareIndex($index);
                }

                return [
                    'success' => true,
                    'token' => $existingToken,
                    'link' => $link
                ];
            }
        }

        $downloadInfo['media_type'] = $entry['media_type'] ?? null;
        $downloadInfo['file_id'] = $entry['file_id'] ?? null;
        $downloadInfo['share_source'] = 'command';

        $token = self::createHistoryEntry($userId, $downloadInfo);
        $link = self::generateShareLink($token);

        if (!$link) {
            return [
                'success' => false,
                'message' => '⚠️ Tidak dapat membuat link berbagi karena username bot belum tersedia.'
            ];
        }

        $entry['share_token'] = $token;
        $entry['updated_at'] = date('c');
        $index[$userKey][$messageKey] = $entry;
        self::saveShareIndex($index);

        return [
            'success' => true,
            'token' => $token,
            'link' => $link
        ];
    }

    /**
     * Forward download to user's history channel
     */
    public static function forwardToChannel($userId, $messageId, $chatId, $downloadInfo = []) {
        $channel = self::getUserChannel($userId);

        if (!$channel) {
            return ['success' => false, 'message' => 'Channel not configured'];
        }

        $channelId = $channel['channel_id'];
        $token = !empty($downloadInfo) ? self::createHistoryEntry($userId, $downloadInfo) : null;

        try {
            $copyParams = [
                'chat_id' => $channelId,
                'from_chat_id' => $chatId,
                'message_id' => $messageId
            ];

            if (!empty($downloadInfo)) {
                $copyParams['caption'] = self::buildHistoryCaption($downloadInfo, $token);
                $copyParams['parse_mode'] = 'Markdown';

                $keyboard = self::buildHistoryKeyboard($token);
                if (!empty($keyboard)) {
                    $copyParams['reply_markup'] = json_encode($keyboard);
                }
            }

            $copyResult = self::$bot->request('copyMessage', $copyParams);

            if (!($copyResult['ok'] ?? false)) {
                if ($token) {
                    self::removeHistoryEntry($token);
                }
                Logger::warning("Failed to copy message to channel", [
                    'user_id' => $userId,
                    'channel_id' => $channelId,
                    'error' => $copyResult['description'] ?? 'Unknown'
                ]);
                return ['success' => false, 'message' => $copyResult['description'] ?? 'Failed to copy'];
            }

            $forwardedMsgId = $copyResult['result']['message_id'] ?? null;

            if ($token && $forwardedMsgId) {
                self::attachMessageToHistory($token, $channelId, $forwardedMsgId);
            }

            $userChannels = self::loadUserChannels();
            $userChannels[$userId]['message_count']++;
            $userChannels[$userId]['last_forward'] = date('Y-m-d H:i:s');
            self::saveUserChannels($userChannels);

            Logger::debug("Copied download to history channel", [
                'user_id' => $userId,
                'message_id' => $forwardedMsgId
            ]);

            return [
                'success' => true,
                'token' => $token,
                'message_id' => $forwardedMsgId,
                'channel_id' => $channelId
            ];

        } catch (\Exception $e) {
            if ($token) {
                self::removeHistoryEntry($token);
            }
            Logger::exception($e, [
                'context' => 'forward_to_channel',
                'user_id' => $userId
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send download info to channel with inline button
     */
    public static function sendToChannel($userId, $mediaUrl, $mediaType, $downloadInfo = []) {
        $channel = self::getUserChannel($userId);

        if (!$channel) {
            return ['success' => false, 'message' => 'Channel not configured'];
        }

        $channelId = $channel['channel_id'];
        $token = !empty($downloadInfo) ? self::createHistoryEntry($userId, $downloadInfo) : null;
        $caption = self::buildHistoryCaption($downloadInfo, $token);
        $keyboard = self::buildHistoryKeyboard($token);

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
                    $result = self::$bot->sendMessage($channelId, $caption . "\n\n📥 " . $mediaUrl, 'Markdown', $keyboard);
                    break;
            }

            if ($result['ok'] ?? false) {
                $userChannels = self::loadUserChannels();
                $userChannels[$userId]['message_count']++;
                $userChannels[$userId]['last_forward'] = date('Y-m-d H:i:s');
                self::saveUserChannels($userChannels);

                $messageId = $result['result']['message_id'] ?? null;
                if ($token && $messageId) {
                    self::attachMessageToHistory($token, $channelId, $messageId);
                }

                return [
                    'success' => true,
                    'token' => $token,
                    'message_id' => $messageId,
                    'channel_id' => $channelId
                ];
            }

            if ($token) {
                self::removeHistoryEntry($token);
            }

            return ['success' => false, 'message' => $result['description'] ?? 'Failed to send'];

        } catch (\Exception $e) {
            if ($token) {
                self::removeHistoryEntry($token);
            }
            Logger::exception($e, [
                'context' => 'send_to_channel',
                'user_id' => $userId
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private static function generateHistoryToken() {
        try {
            // Generate UUIDv4
            $data = random_bytes(16);
            // Set version to 0100 (UUID v4)
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            // Set bits 6-7 to 10
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            
            return strtolower(sprintf(
                '%s-%s-%s-%s-%s',
                bin2hex(substr($data, 0, 4)),
                bin2hex(substr($data, 4, 2)),
                bin2hex(substr($data, 6, 2)),
                bin2hex(substr($data, 8, 2)),
                bin2hex(substr($data, 10, 6))
            ));
        } catch (\Exception $e) {
            // Fallback UUID v4 using mt_rand
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
    }

    private static function createHistoryEntry($userId, array $downloadInfo) {
        $index = self::loadHistoryIndex();

        $token = self::generateHistoryToken();
        while (isset($index[$token])) {
            $token = self::generateHistoryToken();
        }

        $index[$token] = [
            'token' => $token,
            'user_id' => $userId,
            'url' => $downloadInfo['url'] ?? null,
            'platform' => $downloadInfo['platform'] ?? null,
            'title' => $downloadInfo['title'] ?? null,
            'created_at' => date('c'),
            'message_id' => null,
            'channel_id' => null,
            'extra' => $downloadInfo
        ];

        self::saveHistoryIndex($index);

        return $token;
    }

    private static function attachMessageToHistory($token, $channelId, $messageId) {
        $index = self::loadHistoryIndex();

        if (!isset($index[$token])) {
            return;
        }

        $index[$token]['message_id'] = $messageId;
        $index[$token]['channel_id'] = $channelId;
        $index[$token]['updated_at'] = date('c');

        self::saveHistoryIndex($index);
    }

    private static function removeHistoryEntry($token) {
        $index = self::loadHistoryIndex();

        if (isset($index[$token])) {
            unset($index[$token]);
            self::saveHistoryIndex($index);
        }
    }

    public static function getHistoryEntry($token) {
        $index = self::loadHistoryIndex();
        return $index[$token] ?? null;
    }

    public static function deleteHistoryEntry($token) {
        $entry = self::getHistoryEntry($token);

        if (!$entry) {
            return [
                'success' => false,
                'message' => 'Data riwayat tidak ditemukan.'
            ];
        }

        try {
            if (!empty($entry['channel_id']) && !empty($entry['message_id'])) {
                self::$bot->deleteMessage($entry['channel_id'], $entry['message_id']);
            }
        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'delete_history_message',
                'token' => $token
            ]);
        }

        self::removeHistoryEntry($token);

        return [
            'success' => true,
            'message' => 'Riwayat berhasil dihapus.'
        ];
    }

    /**
     * Build caption for history message
     */
    private static function buildHistoryCaption($downloadInfo, $token = null) {
        $platform = $downloadInfo['platform'] ?? 'unknown';
        $title = $downloadInfo['title'] ?? 'No title';
        $url = $downloadInfo['url'] ?? '';
        $date = date('Y-m-d H:i:s');

        $caption = "📥 **Download History**\n\n";
        $caption .= "🌐 Platform: {$platform}\n";
        $caption .= "📝 Title: {$title}\n";
        $caption .= "📅 Date: {$date}\n\n";
        $caption .= "🔗 Original URL:\n`{$url}`";

        if ($token) {
            $caption .= "\n\n🆔 ID: `{$token}`";
            $shareLink = self::generateShareLink($token);
            if ($shareLink) {
                $caption .= "\n📤 Bagikan: {$shareLink}";
            }
        }

        return $caption;
    }

    /**
     * Build inline keyboard for history message
     */
    private static function buildHistoryKeyboard($token) {
        if (!$token) {
            return [];
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Re-download', 'callback_data' => 'redl_' . $token],
                    ['text' => '⭐ Favoritkan', 'callback_data' => 'fav_' . $token]
                ],
                [
                    ['text' => '🗑️ Hapus', 'callback_data' => 'del_' . $token]
                ]
            ]
        ];

        $shareLink = self::generateShareLink($token);
        if ($shareLink) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '📤 Bagikan Link', 'url' => $shareLink]
            ];
        }

        return $keyboard;
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
