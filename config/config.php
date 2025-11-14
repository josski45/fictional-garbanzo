<?php

// Function to load .env file
function loadEnvFile() {
    // Try multiple possible locations for .env file
    $possiblePaths = [
        __DIR__ . '/../.env',                           // from config/ → root
        dirname(__DIR__) . '/.env',                     // from any subdirectory
        dirname(dirname(__FILE__)) . '/.env',           // alternative method
    ];
    
    // If running on web server, add more paths
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $possiblePaths[] = $_SERVER['DOCUMENT_ROOT'] . '/../.env';
        $possiblePaths[] = dirname($_SERVER['DOCUMENT_ROOT']) . '/.env';
    }
    
    // Find the first existing .env file
    $envFile = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $envFile = $path;
            break;
        }
    }
    
    // If no .env file found, return empty array
    if ($envFile === null) {
        return [];
    }
    
    // Read and parse .env file
    $envVars = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (strlen($value) >= 2) {
                if (($value[0] === '"' && $value[strlen($value)-1] === '"') ||
                    ($value[0] === "'" && $value[strlen($value)-1] === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            
            // Store in multiple places for compatibility
            if (!empty($key)) {
                $envVars[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    return $envVars;
}

// Load environment variables
$GLOBALS['envVars'] = loadEnvFile();

// Helper function to get env value with fallback
function env($key, $default = null) {
    // Check in order: $GLOBALS['envVars'], getenv, $_ENV, $_SERVER
    if (isset($GLOBALS['envVars'][$key])) {
        return $GLOBALS['envVars'][$key];
    }
    
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    
    return $default;
}

    function sanitizeVersionValue($value, $default) {
        if ($value === null) {
            return $default;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return $default;
        }

        $hashPos = strpos($stringValue, '#');
        if ($hashPos !== false) {
            $stringValue = substr($stringValue, 0, $hashPos);
        }

        $stringValue = trim($stringValue);

        if ($stringValue === '') {
            return $default;
        }

        return $stringValue;
    }

    $defaultApiVersion = sanitizeVersionValue(env('NEKOLABS_API_VERSION', 'v5'), 'v5');
    $youtubeApiVersion = sanitizeVersionValue(env('NEKOLABS_YOUTUBE_VERSION', 'v1'), 'v1');
    $aioApiVersion = sanitizeVersionValue(env('NEKOLABS_AIO_VERSION', $defaultApiVersion), $defaultApiVersion);

return [
    // Telegram Bot Token
    'bot_token' => env('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE'),
    
    // Webhook URL (e.g., https://yourdomain.com/webhook.php)
    'webhook_url' => env('WEBHOOK_URL', 'https://yourdomain.com/webhook.php'),
    
    // Secret Key untuk command /ekstrakhar
    'secret_key' => env('SECRET_KEY', 'JSK'),
    
    // Default encryption key untuk HAR decryption
    'default_encryption_key' => env('DEFAULT_ENCRYPTION_KEY', 'Match&Ocean'),
    
    // Ferdev API Key (DEPRECATED - Use NekoLabs instead)
    'FERDEV_API_KEY' => env('FERDEV_API_KEY', ''),

    // NekoLabs API Settings
    'NEKOLABS_API_VERSION' => $defaultApiVersion,
    'NEKOLABS_YOUTUBE_VERSION' => $youtubeApiVersion,
    'NEKOLABS_AIO_VERSION' => $aioApiVersion,

    // Admin User IDs (comma separated)
    'admin_ids' => array_filter(array_map('intval', explode(',', env('ADMIN_IDS', '')))),

    // Directories
    'directories' => [
        'temp' => __DIR__ . '/../' . env('TEMP_DIR', 'temp'),
        'downloads' => __DIR__ . '/../downloads',
        'results' => __DIR__ . '/../results',
        'sessions' => __DIR__ . '/../' . env('SESSIONS_DIR', 'sessions'),
        'logs' => __DIR__ . '/../logs',
        'data' => __DIR__ . '/../data'
    ],
    
    // File size limits (in bytes)
    'limits' => [
        'max_file_size' => (int) env('MAX_FILE_SIZE', 50 * 1024 * 1024),  // Default 50MB
        'max_har_size' => 100 * 1024 * 1024   // 100MB
    ],
    
    // Session settings
    'session' => [
        'timeout' => 30 * 60,  // 30 minutes
        'cleanup_interval' => 60 * 60  // 1 hour
    ]
];