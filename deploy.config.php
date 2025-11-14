<?php
/**
 * Deploy Configuration
 * Edit file ini untuk customize deployment
 * 
 * CARA EDIT VIA SETUP.PHP:
 * 1. Buka: https://yourdomain.com/setup.php
 * 2. Login dengan password
 * 3. Pilih menu "Deployment Config"
 * 4. Edit konfigurasi sesuai kebutuhan
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
        
        // Main entry point
        'index.php',
        'autoload.php',

        // Public
        'public/webhook.php',
        'public/setup.php',
        'public/view_logs.php',
        'public/test_autoload.php',
        'public/.htaccess',
        'public/qrisku.jpg',
        'public/energetic-orange-fox-mascot-giving-thumbs-up.png',

        // API
        'src/api/NekoLabsClient.php',
        'src/api/SSSTikProClient.php',

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
        'src/utils/DatabaseHelper.php',
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

        // Docs
        'README.md',
        'CHANGES.md',
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
