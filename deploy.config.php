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
        'public/webhook_maintenance.php',
        'public/clear_cache.php',
        'public/test.php',
        'public/energetic-orange-fox-mascot-giving-thumbs-up.png',
        
        // API
        'src/api/SSSTikProClient.php',
        'src/api/BaseDownloader.php',
        
        // Handlers
        'src/handlers/CallbackHandler.php',
        'src/handlers/CommandHandler.php',
        'src/handlers/DownloadHandler.php',
        'src/handlers/MessageHandler.php',
        'src/handlers/TikTokUserHandler.php',
        
        // Helpers
        'src/helpers/ErrorHelper.php',
        'src/helpers/KeyboardHelper.php',
        
        // Responses
        'src/responses/CapcutResponse.php',
        'src/responses/FacebookResponse.php',
        'src/responses/ResponseHandler.php',
        'src/responses/SpotifyResponse.php',
        'src/responses/TiktokResponse.php',
        'src/responses/YoutubeResponse.php',
        
        // Utils
        'src/utils/Encryption.php',
        'src/utils/HarParser.php',
        'src/utils/QuotesManager.php',
        'src/utils/SessionManager.php',
        'src/utils/StatsManager.php',
        'src/utils/TelegramBot.php',
        
        // Setup
        'setup_webhook.php',
        'update_webhook.php',
        
        // Docs
        'README.md',
        'CHANGES.md',
    ],
    
    // Empty directories to create
    'empty_dirs' => [
        'logs',
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
