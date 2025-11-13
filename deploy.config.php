<?php
/**
 * Deploy Configuration
 * Edit file ini untuk customize deployment
 */

return [
    // Server Configuration
    'deploy_url' => 'https://tl.fsu.my.id/deploy.php?route=deploy&target=jh&token=aliza',
    'zip_name' => 'josski-bot-update.zip',

    // Files to include (relative to root)
    'files' => [
        // Config
        'config/config.php',
        '.env',

        // Public
        'public/webhook.php',
        'public/energetic-orange-fox-mascot-giving-thumbs-up.png',

        // API
        'src/api/NekoLabsClient.php',

        // Handlers
        'src/handlers/AdminHandler.php',
        'src/handlers/BulkDownloadHandler.php',
        'src/handlers/CallbackHandler.php',
        'src/handlers/CommandHandler.php',
        'src/handlers/DownloadHandler.php',
        'src/handlers/MessageHandler.php',
        'src/handlers/TikTokUserHandler.php',

        // Helpers
        'src/helpers/ErrorHelper.php',
        'src/helpers/KeyboardHelper.php',

        // Responses
        'src/responses/NekoLabsResponseHandler.php',

        // Utils
        'src/utils/AdvancedStats.php',
        'src/utils/ChannelHistory.php',
        'src/utils/DonationManager.php',
        'src/utils/DownloadHistory.php',
        'src/utils/Encryption.php',
        'src/utils/HarParser.php',
        'src/utils/Logger.php',
        'src/utils/MaintenanceManager.php',
        'src/utils/QuotesManager.php',
        'src/utils/RateLimiter.php',
        'src/utils/SessionManager.php',
        'src/utils/StatsManager.php',
        'src/utils/TelegramBot.php',
        'src/utils/UserLogger.php',
        'src/utils/UserManager.php',

        // Setup
        'setup_webhook.php',
        'update_webhook.php',

        // Docs
        'README.md',
        'CHANGES.md',
        'CHANNEL_HISTORY_INTEGRATION.md',
        'MAINTENANCE_MODE_GUIDE.md',
        'NEW_FEATURES_v2.3.0.md',
        'RELEASE_NOTES_v2.3.1.md',
    ],

    // Empty directories to create
    'empty_dirs' => [
        'logs',
        'data',
        'data/cache',
        'data/stats',
        'sessions',
        'temp',
    ],

    // Folders to exclude
    'exclude_folders' => [
        'temp',
        'temp_deploy',
        'logs',
        'data/cache',
        'sessions',
        '.git',
        'node_modules',
        'vendor',
    ],

    // Options
    'delete_zip_after_deploy' => true,
    'create_htaccess' => true,
    'timeout' => 300, // 5 minutes
];
